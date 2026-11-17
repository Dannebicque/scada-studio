import { test } from 'node:test'
import assert from 'node:assert/strict'
import { measureState } from '../app/utils/measureState.ts'

const now = Date.parse('2026-11-17T10:00:00Z')
const at = (offsetSeconds: number) => new Date(now + offsetSeconds * 1000).toISOString()

test('aucune mesure : vide', () => assert.equal(measureState(undefined, now), 'empty'))

test('fraîche : la qualité déclarée s’applique', () => {
  assert.equal(measureState({ observedAt: at(-5) }, now), 'good')
  assert.equal(measureState({ observedAt: at(-5), quality: 'good' }, now), 'good')
  assert.equal(measureState({ observedAt: at(-5), quality: 'uncertain' }, now), 'uncertain')
  assert.equal(measureState({ observedAt: at(-5), quality: 'bad' }, now), 'bad')
})

test('trop ancienne : périmée, même déclarée bonne ou mauvaise', () => {
  assert.equal(measureState({ observedAt: at(-31), quality: 'good' }, now), 'stale')
  assert.equal(measureState({ observedAt: at(-31), quality: 'bad' }, now), 'stale')
  assert.equal(measureState({ observedAt: at(-31), quality: 'stale' }, now), 'stale')
  assert.equal(measureState({ observedAt: at(-60) }, now, 120), 'good', 'seuil configurable')
})

test('horodatage invalide ou dans le futur : périmée', () => {
  assert.equal(measureState({ observedAt: 'n’importe quoi' }, now), 'stale')
  assert.equal(measureState({ observedAt: at(3) }, now), 'good', 'tolérance de 5 s')
  assert.equal(measureState({ observedAt: at(30) }, now), 'stale')
})
