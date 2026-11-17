// Inférence d'un modèle UNS et de pages dashboard à partir de trames MQTT. Sans dépendance MQTT.

const SKIP_SEGMENT = /^[_$]/; // _status, $SYS… : métadonnées de transport, pas des tags
const ALARM_HINT = /alarm|alarme|fault|defaut|défaut|trip|alert/i;
const COUNTER_HINT = /count|total|produced|pieces|piece|cumul|energy|kwh|volume_total/i;

export function parsePayload(text) {
  let v;
  try { v = JSON.parse(text); } catch {
    const s = String(text).trim();
    if (s !== '' && Number.isFinite(Number(s))) return { value: Number(s) };
    if (s === 'true' || s === 'false') return { value: s === 'true' };
    return s ? { value: s } : null;
  }
  if (v !== null && typeof v === 'object' && !Array.isArray(v)) return v;
  return { value: v };
}

/**
 * Transforme une trame en échantillons {path, tag, value, unit, quality, ts}.
 * - mode tag   : topic = {…asset}/{tag},  payload {value,…} ou scalaire
 * - mode asset : topic = {…asset},        payload {tags:{nom:{value,…}}}
 */
export function parseFrame(topic, text, receivedAt = Date.now()) {
  const segs = topic.split('/').filter(Boolean);
  if (segs.length < 2 || segs.some((s) => SKIP_SEGMENT.test(s))) return [];
  const p = parsePayload(text);
  if (!p) return [];
  const ts = Date.parse(p.observedAt ?? p.timestamp ?? '') || receivedAt;
  const sample = (path, tag, o, t) => ({
    path, tag, value: o.value, unit: o.unit, quality: o.quality ?? 'good', ts: t,
  });
  if (p.tags && typeof p.tags === 'object') {
    return Object.entries(p.tags)
      .filter(([, o]) => o && typeof o === 'object' && 'value' in o)
      .map(([name, o]) => sample(segs, name, o, ts));
  }
  if (!('value' in p)) return [];
  return [sample(segs.slice(0, -1), segs.at(-1), p, ts)];
}

export class Collector {
  constructor() { this.tags = new Map(); this.frames = 0; }

  add(s) {
    if (s.value === null || s.value === undefined) return;
    const key = [...s.path, s.tag].join('/');
    let t = this.tags.get(key);
    if (!t) {
      t = { path: s.path, tag: s.tag, unit: undefined, n: 0, bad: 0, types: new Set(), min: Infinity, max: -Infinity,
        inc: 0, dec: 0, last: undefined, distinct: new Set(), tsList: [] };
      this.tags.set(key, t);
    }
    t.n += 1;
    if (s.unit) t.unit = s.unit;
    if (s.quality !== 'good') { t.bad += 1; return; } // une valeur douteuse ne doit pas fausser plages/types
    const type = typeof s.value;
    t.types.add(type);
    if (type === 'number' && Number.isFinite(s.value)) {
      t.min = Math.min(t.min, s.value); t.max = Math.max(t.max, s.value);
      if (typeof t.last === 'number') { if (s.value > t.last) t.inc += 1; else if (s.value < t.last) t.dec += 1; }
    }
    if (type !== 'number' && t.distinct.size <= 20) t.distinct.add(String(s.value));
    t.last = s.value;
    if (t.tsList.length < 200) t.tsList.push(s.ts);
  }

  addFrame(topic, text, receivedAt) {
    this.frames += 1;
    for (const s of parseFrame(topic, text, receivedAt)) this.add(s);
  }
}

const median = (a) => { const s = [...a].sort((x, y) => x - y); return s.length ? s[Math.floor(s.length / 2)] : undefined; };

function intervalSeconds(t) {
  const d = [];
  for (let i = 1; i < t.tsList.length; i++) { const dt = t.tsList[i] - t.tsList[i - 1]; if (dt > 0) d.push(dt); }
  const m = median(d);
  return m === undefined ? undefined : Math.round(m) / 1000;
}

export function classify(t) {
  const interval = intervalSeconds(t);
  const staleAfterSeconds = interval ? Math.max(5, Math.ceil(interval * 3)) : 30;
  const base = { name: t.tag, unit: t.unit, samples: t.n, badSamples: t.bad, intervalSeconds: interval, staleAfterSeconds };
  const mixed = t.types.size > 1;
  const type = mixed ? 'mixed' : [...t.types][0];
  if (type === 'boolean') {
    return { ...base, kind: 'boolean', widget: ALARM_HINT.test(t.tag) ? 'core.alarm' : 'core.status' };
  }
  if (type === 'string' || type === 'mixed') {
    const values = [...t.distinct].sort();
    const isState = values.length > 0 && values.length <= 12;
    return { ...base, kind: 'string', values, widget: isState ? 'core.state' : 'core.value' };
  }
  if (type === 'number') {
    const range = { min: t.min, max: t.max };
    const monotonic = t.dec === 0 && t.inc >= 3 && (t.n >= 8 || COUNTER_HINT.test(t.tag));
    if (monotonic) return { ...base, kind: 'counter', ...range, widget: 'core.value', trend: 'counter' };
    if (t.min === t.max) return { ...base, kind: 'constant', ...range, widget: 'core.value' };
    if (t.unit === '%') return { ...base, kind: 'number', ...range, scale: { min: 0, max: 100 }, widget: 'core.gauge' };
    return { ...base, kind: 'number', ...range, widget: 'core.timeseries' };
  }
  return { ...base, kind: 'unknown', widget: 'core.value' };
}

/** Détermine le préfixe commun (entreprise/site…) en gardant au moins un segment d'asset. */
export function commonRoot(assetPaths, override) {
  if (override) return override.split('/').filter(Boolean);
  if (!assetPaths.length) return [];
  const minLen = Math.min(...assetPaths.map((p) => p.length));
  const root = [];
  for (let i = 0; i < minLen - 1; i++) {
    if (assetPaths.every((p) => p[i] === assetPaths[0][i])) root.push(assetPaths[0][i]); else break;
  }
  return root;
}

export const label = (s) => {
  const t = String(s).replace(/[_-]+/g, ' ').trim();
  return t.charAt(0).toUpperCase() + t.slice(1);
};
const slug = (parts) => parts.join('-').toLowerCase().replace(/[^a-z0-9-]/g, '-');

export function buildModel(collector, { root } = {}) {
  const byAsset = new Map();
  for (const t of collector.tags.values()) {
    const key = t.path.join('/');
    if (!byAsset.has(key)) byAsset.set(key, { path: t.path, tags: [] });
    byAsset.get(key).tags.push(t);
  }
  const rootSegs = commonRoot([...byAsset.values()].map((a) => a.path), root);
  const assets = [...byAsset.values()]
    .map((a) => ({
      topic: a.path.join('/'),
      path: a.path.slice(rootSegs.length),
      tags: a.tags.map(classify).sort((x, y) => x.name.localeCompare(y.name)),
    }))
    .sort((a, b) => a.topic.localeCompare(b.topic));
  return { root: rootSegs.join('/'), frames: collector.frames, assets };
}

const SIZE = {
  'core.status': [3, 2], 'core.alarm': [3, 2], 'core.state': [3, 2], 'core.value': [3, 2],
  'core.gauge': [3, 3], 'core.timeseries': [6, 3],
};
const ORDER = ['core.alarm', 'core.state', 'core.status', 'core.value', 'core.gauge', 'core.timeseries'];
const GRID = 12;

/** Une page dashboard par area (premier segment sous la racine), conforme à page.schema.json. */
export function buildPages(model) {
  const groups = new Map();
  for (const a of model.assets) {
    const area = a.path.length > 1 ? a.path[0] : 'overview';
    if (!groups.has(area)) groups.set(area, []);
    groups.get(area).push(a);
  }
  return [...groups.entries()].map(([area, assets]) => {
    const elements = [];
    let y = 0;
    for (const a of assets) {
      const relPath = area === 'overview' ? a.path : a.path.slice(1);
      const title = relPath.length ? relPath.map(label).join(' / ') : label(area);
      elements.push({
        id: slug([area, ...relPath, 'section']), type: 'core.section',
        properties: { label: title, topic: a.topic }, bindings: {}, layout: { x: 0, y, width: GRID, height: 1 },
      });
      y += 1;
      let x = 0; let rowH = 0;
      const tags = [...a.tags].sort((p, q) => ORDER.indexOf(p.widget) - ORDER.indexOf(q.widget) || p.name.localeCompare(q.name));
      for (const t of tags) {
        const [w, h] = SIZE[t.widget];
        if (x + w > GRID) { y += rowH; x = 0; rowH = 0; }
        const tagPath = [...a.path, t.name];
        const properties = { label: label(t.name), topic: `${a.topic}/${t.name}`, staleAfterSeconds: t.staleAfterSeconds };
        if (t.unit) properties.unit = t.unit;
        if (t.kind === 'string' && t.widget === 'core.state') properties.values = t.values;
        if (t.scale) { properties.min = t.scale.min; properties.max = t.scale.max; }
        if (t.trend) properties.trend = t.trend;
        elements.push({
          id: slug([area, ...tagPath.slice(area === 'overview' ? 0 : 1)]), type: t.widget, properties,
          bindings: { value: tagPath.join('.') }, layout: { x, y, width: w, height: h },
        });
        x += w; rowH = Math.max(rowH, h);
      }
      y += rowH;
    }
    return { schemaVersion: 1, id: `inferred-${slug([area])}`, kind: 'dashboard', title: label(area), elements };
  });
}
