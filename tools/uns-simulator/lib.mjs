// Moteur de simulation UNS : aucune dépendance MQTT, entièrement testable.

const SEGMENT_RE = /^[A-Za-z0-9_-]{1,100}$/;

export function mulberry32(seed) {
  let a = seed >>> 0;
  return () => {
    a = (a + 0x6d2b79f5) >>> 0;
    let t = a;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

function gauss(rng) {
  const u = Math.max(rng(), Number.EPSILON);
  return Math.sqrt(-2 * Math.log(u)) * Math.cos(2 * Math.PI * rng());
}

const clamp = (v, min, max) => Math.min(max ?? Infinity, Math.max(min ?? -Infinity, v));
const round = (v, d) => Number(v.toFixed(d));

/** Segment de topic sûr, compatible avec MqttMessageParser côté API. */
export function sanitizeSegment(s) {
  return String(s).replace(/[^A-Za-z0-9_-]/g, '-').slice(0, 100);
}

export function validatePlant(plant) {
  const errors = [];
  const check = (cond, msg) => cond || errors.push(msg);
  check(plant && typeof plant === 'object', 'config invalide');
  check(SEGMENT_RE.test(plant?.enterprise ?? ''), 'enterprise invalide');
  check(SEGMENT_RE.test(plant?.site ?? ''), 'site invalide');
  check(Array.isArray(plant?.assets) && plant.assets.length > 0, 'assets requis');
  for (const asset of plant?.assets ?? []) {
    const segs = String(asset.path ?? '').split('/');
    check(segs.every((s) => SEGMENT_RE.test(s)), `asset.path invalide: ${asset.path}`);
    check(Array.isArray(asset.tags) && asset.tags.length > 0, `tags requis: ${asset.path}`);
    const names = new Set();
    for (const tag of asset.tags ?? []) {
      check(SEGMENT_RE.test(tag.name ?? ''), `tag.name invalide: ${asset.path}/${tag.name}`);
      check(!names.has(tag.name), `tag dupliqué: ${asset.path}/${tag.name}`);
      names.add(tag.name);
      check(['number', 'boolean', 'string', 'enum'].includes(tag.type), `type inconnu: ${tag.name}`);
      if (tag.type === 'enum' || (tag.type === 'string' && tag.values)) {
        check(Array.isArray(tag.values) && tag.values.length > 0, `values requis: ${tag.name}`);
      }
    }
  }
  if (errors.length) throw new Error(`Configuration invalide :\n - ${errors.join('\n - ')}`);
  return plant;
}

function pickWeighted(values, weights, rng) {
  if (!weights) return values[Math.floor(rng() * values.length)];
  const total = weights.reduce((a, b) => a + b, 0);
  let r = rng() * total;
  for (let i = 0; i < values.length; i++) {
    r -= weights[i] ?? 0;
    if (r <= 0) return values[i];
  }
  return values[values.length - 1];
}

function initialValue(tag, rng) {
  switch (tag.type) {
    case 'number': return tag.shape === 'counter' ? (tag.initial ?? 0) : (tag.base ?? tag.min ?? 0);
    case 'boolean': return tag.initial ?? false;
    case 'enum':
    case 'string': return tag.values?.[0] ?? '';
    default: return null;
  }
}

function nextValue(st, t, rng) {
  const tag = st.tag;
  switch (tag.type) {
    case 'number': {
      const d = tag.decimals ?? 2;
      if (tag.shape === 'counter') {
        st.raw += (tag.step ?? 1) * (0.5 + rng());
        return round(st.raw, d);
      }
      if (tag.shape === 'walk') {
        const base = tag.base ?? 0;
        st.raw += gauss(rng) * (tag.step ?? 1) + (base - st.raw) * 0.05;
        st.raw = clamp(st.raw, tag.min, tag.max);
        return round(st.raw, d);
      }
      const v = (tag.base ?? 0)
        + (tag.amplitude ?? 0) * Math.sin((2 * Math.PI * t) / (tag.period ?? 60) + st.phase)
        + gauss(rng) * (tag.noise ?? 0);
      return round(clamp(v, tag.min, tag.max), d);
    }
    case 'boolean':
      if (rng() < (tag.toggleProb ?? 0.01)) st.raw = !st.raw;
      return st.raw;
    case 'enum':
    case 'string':
      if (rng() < (tag.changeProb ?? 0.02)) st.raw = pickWeighted(tag.values, tag.weights, rng);
      return st.raw;
    default:
      return null;
  }
}

/**
 * Construit les topics et payloads d'un tick.
 * profile "uns"   : {enterprise}/{site}/{…chemin asset}/{tag}
 * profile "scada" : scada/{workspace}/{source}/{tag}  (contrat actuel de MqttMessageParser)
 * mode "tag" : un topic par tag ; mode "asset" : un topic par asset (profil uns uniquement).
 */
export class Simulation {
  constructor(plant, { profile = 'uns', workspace = 'atelier-demo', mode = 'tag', faults = false, seed = 42 } = {}) {
    validatePlant(plant);
    if (!['uns', 'scada'].includes(profile)) throw new Error(`profil inconnu: ${profile}`);
    if (!['tag', 'asset'].includes(mode)) throw new Error(`mode inconnu: ${mode}`);
    if (mode === 'asset' && profile !== 'uns') throw new Error('le mode "asset" requiert le profil "uns"');
    this.plant = plant;
    this.profile = profile;
    this.workspace = sanitizeSegment(workspace);
    this.mode = mode;
    this.faults = faults;
    this.rng = mulberry32(seed);
    this.tick = 0;
    this.lastMs = new Map();
    this.assets = plant.assets.map((asset) => ({
      asset,
      base: this.#assetTopic(asset),
      tags: asset.tags.map((tag) => ({
        tag,
        raw: initialValue(tag, this.rng),
        phase: this.rng() * 2 * Math.PI,
        badUntil: -1,
        staleUntil: -1,
        last: initialValue(tag, this.rng),
      })),
    }));
  }

  #assetTopic(asset) {
    const segs = asset.path.split('/');
    if (this.profile === 'uns') return [this.plant.enterprise, this.plant.site, ...segs];
    const source = asset.source ?? sanitizeSegment(segs.join('-'));
    return ['scada', this.workspace, source];
  }

  /** Liste statique des topics (utile pour documenter / vérifier). */
  topics() {
    if (this.mode === 'asset') return this.assets.map((a) => a.base.join('/'));
    return this.assets.flatMap((a) => a.tags.map((s) => [...a.base, s.tag.name].join('/')));
  }

  statusTopic() {
    return this.profile === 'uns' ? `${this.plant.enterprise}/${this.plant.site}/_status` : null;
  }

  #observedAt(key, now) {
    let ms = now.getTime();
    const prev = this.lastMs.get(key);
    if (prev !== undefined && ms <= prev) ms = prev + 1; // l'API exige des horodatages strictement croissants
    this.lastMs.set(key, ms);
    return new Date(ms).toISOString();
  }

  /** @returns {{topic:string,payload:string}[]} messages à publier pour ce tick */
  step(now = new Date(), dtSec = 1) {
    const t = this.tick * dtSec;
    const out = [];
    for (const { asset, base, tags } of this.assets) {
      const batch = {};
      for (const st of tags) {
        const { tag } = st;
        const value = nextValue(st, t, this.rng);
        if (this.faults) {
          if (this.tick > st.badUntil && this.tick > st.staleUntil) {
            const r = this.rng();
            if (r < 0.004) st.badUntil = this.tick + 5;
            else if (r < 0.008) st.staleUntil = this.tick + 10;
          }
        }
        if (this.tick <= st.staleUntil) continue; // silence : la valeur devient « stale » côté lecture
        if (this.tick % (tag.every ?? 1) !== 0) continue;
        const quality = this.tick <= st.badUntil ? 'bad' : 'good';
        if (quality === 'good') st.last = value;
        const shown = quality === 'good' ? value : st.last;
        const entry = { value: shown };
        if (tag.unit) entry.unit = tag.unit;
        entry.quality = quality;
        if (this.mode === 'asset') {
          batch[tag.name] = entry;
        } else {
          const topic = [...base, tag.name].join('/');
          const payload = { value: shown, observedAt: this.#observedAt(topic, now) };
          if (tag.unit) payload.unit = tag.unit;
          payload.quality = quality;
          payload.valueType = tag.type === 'enum' ? 'string' : tag.type;
          out.push({ topic, payload: JSON.stringify(payload) });
        }
      }
      if (this.mode === 'asset' && Object.keys(batch).length) {
        const topic = base.join('/');
        out.push({ topic, payload: JSON.stringify({ observedAt: this.#observedAt(topic, now), tags: batch }) });
      }
    }
    this.tick += 1;
    return out;
  }
}
