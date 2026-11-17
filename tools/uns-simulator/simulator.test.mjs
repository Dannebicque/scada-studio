import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { Simulation, validatePlant } from './lib.mjs';

const load = (f) => JSON.parse(readFileSync(new URL(f, import.meta.url), 'utf8'));
const plant = load('./plant.json');
// Même regex que apps/api/src/Telemetry/MqttMessageParser.php
const SEG = /^[a-zA-Z0-9_-]{1,100}$/;

test('profil uns : tick 0 publie tout', () => {
  const sim = new Simulation(plant);
  assert.equal(sim.step().length, sim.topics().length);
  for (const t of sim.topics()) {
    assert.ok(t.startsWith('acme/troyes/'));
    assert.ok(t.split('/').every((s) => SEG.test(s)), t);
  }
});

test('payload : value + observedAt ISO UTC, unit/quality/valueType', () => {
  const sim = new Simulation(plant);
  const m = sim.step(new Date('2026-11-17T18:00:00Z')).find((x) => x.topic.endsWith('filler/temperature'));
  const p = JSON.parse(m.payload);
  assert.equal(p.observedAt, '2026-11-17T18:00:00.000Z');
  assert.equal(p.unit, '°C');
  assert.equal(p.quality, 'good');
  assert.equal(p.valueType, 'number');
  assert.equal(typeof p.value, 'number');
});

test('observedAt strictement croissant même si l\'horloge est figée', () => {
  const sim = new Simulation(plant);
  const now = new Date('2026-11-17T18:00:00Z');
  const a = JSON.parse(sim.step(now).find((x) => x.topic.endsWith('boiler/pressure')).payload).observedAt;
  const b = JSON.parse(sim.step(now).find((x) => x.topic.endsWith('boiler/pressure')).payload).observedAt;
  assert.ok(b > a);
});

test('profil scada : 4 segments scada/{workspace}/{source}/{tag}', () => {
  const sim = new Simulation(load('./plant-demo.json'), { profile: 'scada', workspace: 'atelier-demo' });
  assert.deepEqual(sim.topics().sort(), [
    'scada/atelier-demo/automate-01/convoyeur_actif',
    'scada/atelier-demo/automate-01/humidite',
    'scada/atelier-demo/automate-01/pieces_produites',
    'scada/atelier-demo/automate-01/temperature',
  ]);
  const sim2 = new Simulation(plant, { profile: 'scada' });
  for (const t of sim2.topics()) {
    const parts = t.split('/');
    assert.equal(parts.length, 4, t);
    assert.ok(parts.every((s) => SEG.test(s)), t);
  }
});

test('mode asset : un JSON par asset avec tags', () => {
  const sim = new Simulation(plant, { mode: 'asset' });
  const msgs = sim.step();
  assert.equal(msgs.length, plant.assets.length);
  const p = JSON.parse(msgs[0].payload);
  assert.ok(p.tags.state && p.tags.temperature);
  assert.throws(() => new Simulation(plant, { mode: 'asset', profile: 'scada' }));
});

test('reproductible avec la même graine', () => {
  const run = (seed) => { const s = new Simulation(plant, { seed }); s.step(new Date(0)); return s.step(new Date(1000)).map((m) => m.payload); };
  assert.deepEqual(run(7), run(7));
  assert.notDeepEqual(run(7), run(8));
});

test('faults : produit des qualités bad et des silences sur la durée', () => {
  const sim = new Simulation(plant, { faults: true, seed: 1 });
  let bad = 0; let total = 0;
  for (let i = 0; i < 600; i++) {
    const msgs = sim.step(new Date(i * 1000));
    total += msgs.length;
    bad += msgs.filter((m) => JSON.parse(m.payload).quality === 'bad').length;
  }
  assert.ok(bad > 0, 'au moins une trame bad');
  assert.ok(total < 600 * sim.topics().length, 'des tags restent silencieux');
});

test('valeurs bornées et compteurs monotones', () => {
  const sim = new Simulation(plant);
  let last = -1;
  for (let i = 0; i < 300; i++) {
    for (const m of sim.step(new Date(i * 1000))) {
      const p = JSON.parse(m.payload);
      if (m.topic.endsWith('filler/temperature')) assert.ok(p.value >= 0 && p.value <= 100);
      if (m.topic.endsWith('filler/produced_count')) { assert.ok(p.value >= last); last = p.value; }
    }
  }
});

test('validation de configuration', () => {
  assert.throws(() => validatePlant({ enterprise: 'a b', site: 's', assets: [] }));
  assert.throws(() => validatePlant({ enterprise: 'a', site: 's', assets: [{ path: 'x/y', tags: [{ name: 't', type: 'zzz' }] }] }));
});
