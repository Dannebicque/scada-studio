import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import Ajv2020 from 'ajv/dist/2020.js';
import { Simulation } from '../uns-simulator/lib.mjs';
import { Collector, buildModel, buildPages, parseFrame } from './lib.mjs';

const plant = JSON.parse(readFileSync(new URL('../uns-simulator/plant.json', import.meta.url), 'utf8'));
const schema = JSON.parse(readFileSync(new URL('../../packages/contracts/schemas/page.schema.json', import.meta.url), 'utf8'));
const validate = new Ajv2020({ strict: false }).compile(schema);

function learn(opts, ticks = 120) {
  const sim = new Simulation(plant, opts);
  const c = new Collector();
  for (let i = 0; i < ticks; i++) for (const m of sim.step(new Date(1_800_000_000_000 + i * 1000))) c.addFrame(m.topic, m.payload, 0);
  return buildModel(c);
}
const find = (model, assetTopic, tag) => model.assets.find((a) => a.topic.endsWith(assetTopic)).tags.find((t) => t.name === tag);

test('racine déduite et assets reconstruits depuis le simulateur', () => {
  const m = learn({});
  assert.equal(m.root, 'acme/troyes');
  assert.deepEqual(m.assets.map((a) => a.path.join('/')), [
    'packaging/line1/conveyor', 'packaging/line1/filler', 'utilities/boiler', 'utilities/compressor']);
});

test('choix de widget selon le type et le comportement des tags', () => {
  const m = learn({});
  const w = (a, t) => find(m, a, t).widget;
  assert.equal(w('filler', 'running'), 'core.status');
  assert.equal(w('filler', 'alarm_overtemp'), 'core.alarm');
  assert.equal(w('filler', 'state'), 'core.state');
  assert.equal(w('filler', 'temperature'), 'core.timeseries');
  assert.equal(w('filler', 'produced_count'), 'core.value');
  assert.equal(find(m, 'filler', 'produced_count').kind, 'counter');
  assert.equal(w('boiler', 'water_level'), 'core.gauge');
  assert.deepEqual(find(m, 'filler', 'temperature').unit, '°C');
});

test('cadence apprise : stale = 3 × période (min 5 s)', () => {
  const m = learn({});
  assert.equal(find(m, 'filler', 'temperature').intervalSeconds, 1);
  assert.equal(find(m, 'filler', 'temperature').staleAfterSeconds, 5);
  assert.equal(find(m, 'filler', 'batch_id').intervalSeconds, 5); // every: 5
  assert.equal(find(m, 'filler', 'batch_id').staleAfterSeconds, 15);
});

test('pages conformes à page.schema.json, ids uniques, bindings par tag', () => {
  const pages = buildPages(learn({}));
  assert.deepEqual(pages.map((p) => p.title).sort(), ['Packaging', 'Utilities']);
  for (const p of pages) {
    assert.ok(validate(p), JSON.stringify(validate.errors));
    const ids = p.elements.map((e) => e.id);
    assert.equal(new Set(ids).size, ids.length);
    for (const e of p.elements) {
      assert.ok(e.layout.x + e.layout.width <= 12, `déborde de la grille: ${e.id}`);
    }
  }
  const el = pages.find((p) => p.title === 'Packaging').elements.find((e) => e.bindings.value === 'packaging.line1.filler.temperature');
  assert.equal(el.type, 'core.timeseries');
  assert.equal(el.properties.unit, '°C');
});

test('pas de chevauchement dans la grille', () => {
  for (const p of buildPages(learn({}))) {
    const r = p.elements.map((e) => e.layout);
    for (let i = 0; i < r.length; i++) for (let j = i + 1; j < r.length; j++) {
      const a = r[i]; const b = r[j];
      const overlap = a.x < b.x + b.width && b.x < a.x + a.width && a.y < b.y + b.height && b.y < a.y + a.height;
      assert.ok(!overlap, `${p.id}: éléments ${i} et ${j} se chevauchent`);
    }
  }
});

test('mode asset et mode tag donnent les mêmes widgets', () => {
  const sig = (m) => m.assets.flatMap((a) => a.tags.map((t) => `${a.topic}/${t.name}:${t.widget}`)).sort();
  assert.deepEqual(sig(learn({ mode: 'asset' })), sig(learn({ mode: 'tag' })));
});

test('profil scada (4 segments) : racine = scada/workspace, assets = sources', () => {
  const m = learn({ profile: 'scada', workspace: 'atelier-demo' });
  assert.equal(m.root, 'scada/atelier-demo');
  assert.ok(m.assets.every((a) => a.path.length === 1));
});

test('qualité bad ignorée pour les plages ; _status ignoré ; payloads bruts', () => {
  assert.deepEqual(parseFrame('acme/troyes/_status', 'online'), []);
  const [s] = parseFrame('a/b/line/m1/temp', '21.5', 1);
  assert.deepEqual([s.path, s.tag, s.value], [['a', 'b', 'line', 'm1'], 'temp', 21.5]);
  const c = new Collector();
  c.addFrame('x/y/z/p', JSON.stringify({ value: 10 }), 0);
  c.addFrame('x/y/z/p', JSON.stringify({ value: 9999, quality: 'bad' }), 1000);
  c.addFrame('x/y/z/p', JSON.stringify({ value: 20 }), 2000);
  const t = [...c.tags.values()][0];
  assert.equal(t.max, 20);
  assert.equal(t.bad, 1);
});
