import { test } from 'node:test'
import assert from 'node:assert/strict'
import { matchTag, parseInferredPage, toPersonalWidgets } from '../app/utils/unsImport.ts'

const el = (id: string, type: string, topic?: string, extra: object = {}) => ({
  id, type, properties: { label: id, ...(topic ? { topic } : {}) }, bindings: {}, layout: { x: 0, y: 0, width: 3, height: 2, ...extra },
})
const page = (elements: object[], extra: object = {}) => JSON.stringify({ schemaVersion: 1, id: 'p', kind: 'dashboard', title: 'T', elements, ...extra })
const tags = [
  { tagId: 'a', path: 'atelier-demo/automate-01/temperature', name: 'temperature' },
  { tagId: 'b', path: 'atelier-demo/automate-01/humidite', name: 'humidite' },
  { tagId: 'c', path: 'atelier-demo/automate-02/temperature', name: 'temperature' },
]

test('rejette JSON invalide, schéma invalide, kind synoptic, doublons, taille', () => {
  assert.equal(parseInferredPage('{').error, 'invalidJson')
  assert.equal(parseInferredPage('[]').error, 'invalidShape')
  assert.equal(parseInferredPage(page([], { schemaVersion: 2 })).error, 'invalidShape')
  assert.equal(parseInferredPage(page([], { kind: 'synoptic' })).error, 'unsupportedKind')
  assert.equal(parseInferredPage(page([el('x', 'core.value'), el('x', 'core.value')])).error, 'duplicateId')
  assert.equal(parseInferredPage(page([{ id: 'x', type: 'core.value' }])).error, 'invalidShape')
  assert.equal(parseInferredPage('x'.repeat(1_000_001)).error, 'tooLarge')
  assert.equal(parseInferredPage(page(Array.from({ length: 301 }, (_, i) => el('e' + i, 'core.value')))).error, 'tooManyElements')
})

test('normalise la mise en page et trie dans l\'ordre de lecture', () => {
  const r = parseInferredPage(page([el('b', 'core.value', undefined, { x: 3, y: 2 }), el('a', 'core.value', undefined, { x: 99, y: -5, width: 500 })]))
  assert.deepEqual(r.page?.elements.map(e => e.id), ['a', 'b'])
  assert.deepEqual(r.page?.elements[0]?.layout, { x: 11, y: 0, width: 12, height: 2 })
})

test('association par asset/tag ; ambigu ou inconnu = non associé', () => {
  assert.equal(matchTag('acme/troyes/atelier/automate-01/temperature', tags)?.tagId, 'a')
  assert.equal(matchTag('scada/atelier-demo/automate-01/humidite', tags)?.tagId, 'b')
  assert.equal(matchTag('x/y/temperature', tags), null) // un seul segment d'asset ne correspond à rien
  assert.equal(matchTag('acme/line1/filler/temperature', tags), null)
  assert.equal(matchTag('temperature', tags), null)
  assert.equal(matchTag(null, tags), null)
  const ambiguous = [...tags, { tagId: 'd', path: 'other-ws/automate-01/temperature', name: 'temperature' }]
  assert.equal(matchTag('acme/automate-01/temperature', ambiguous), null)
})

test('conversion vers les widgets personnels (types supportés, sections ignorées, doublons écartés)', () => {
  const { page: p } = parseInferredPage(page([
    el('s', 'core.section'),
    el('t', 'core.timeseries', 'a/b/atelier/automate-01/temperature'),
    el('t2', 'core.value', 'z/automate-01/temperature'),
    el('h', 'core.gauge', 'a/automate-01/humidite'),
    el('u', 'core.alarm', 'a/automate-01/inconnu'),
    el('w', 'vendor.custom', 'a/automate-01/humidite'),
  ]))
  const r = toPersonalWidgets(p!, tags)
  assert.deepEqual(r.widgets, [
    { type: 'chart', tagId: 'a', title: 't' },
    { type: 'gauge', tagId: 'b', title: 'h' },
  ])
  assert.equal(r.duplicates, 1)
  assert.deepEqual(r.unmatched.map(e => e.id).sort(), ['u', 'w'])
})
