import { test } from 'node:test'
import assert from 'node:assert/strict'
import { assetLabel, buildSelectedPage, discoveryErrorKind } from '../app/utils/unsDiscovery.ts'
import { parseInferredPage, toPersonalWidgets } from '../app/utils/unsImport.ts'

// Comme l'inférence : le binding est le chemin relatif à la racine, séparé par des points.
const el = (id: string, type: string, topic: string) => ({
  id, type, properties: { label: id, topic },
  bindings: type === 'core.section' ? {} : { value: topic.replace('acme/troyes/', '').replaceAll('/', '.') }, layout: { x: 0, y: 0, width: 3, height: 2 },
})
const result = {
  prefix: 'acme/troyes', frames: 10, truncated: false,
  model: { root: 'acme/troyes', frames: 10, assets: [
    { topic: 'acme/troyes/packaging/line1/filler', path: ['packaging', 'line1', 'filler'], tags: [] },
    { topic: 'acme/troyes/packaging/line1/filler/aux', path: ['packaging', 'line1', 'filler', 'aux'], tags: [] },
    { topic: 'acme/troyes/utilities/boiler', path: ['utilities', 'boiler'], tags: [] },
  ] },
  pages: [
    { schemaVersion: 1, id: 'a', kind: 'dashboard', title: 'Packaging', elements: [
      el('s1', 'core.section', 'acme/troyes/packaging/line1/filler'),
      el('t1', 'core.timeseries', 'acme/troyes/packaging/line1/filler/temperature'),
      // tag « aux » de l'asset filler, dont le topic est aussi celui de l'asset filler/aux (ambiguïté à lever par le type)
      el('t2', 'core.value', 'acme/troyes/packaging/line1/filler/aux'),
      el('s2', 'core.section', 'acme/troyes/packaging/line1/filler/aux'),
      el('t3', 'core.value', 'acme/troyes/packaging/line1/filler/aux/mode'),
    ] },
    { schemaVersion: 1, id: 'b', kind: 'dashboard', title: 'Utilities', elements: [
      el('s3', 'core.section', 'acme/troyes/utilities/boiler'),
      el('t4', 'core.gauge', 'acme/troyes/utilities/boiler/level'),
    ] },
  ],
}

test('ne garde que les éléments des assets retenus', () => {
  const page = JSON.parse(buildSelectedPage(result, ['acme/troyes/utilities/boiler'], 'Chaudière'))
  assert.deepEqual(page.elements.map((e: { id: string }) => e.id), ['s3', 't4'])
  assert.equal(page.title, 'Chaudière')
  assert.equal(page.kind, 'dashboard')
})

test('une section suit son propre topic, un widget son asset parent', () => {
  const filler = JSON.parse(buildSelectedPage(result, ['acme/troyes/packaging/line1/filler'], 'x'))
  assert.deepEqual(filler.elements.map((e: { id: string }) => e.id), ['s1', 't1', 't2'])
  const aux = JSON.parse(buildSelectedPage(result, ['acme/troyes/packaging/line1/filler/aux'], 'x'))
  assert.deepEqual(aux.elements.map((e: { id: string }) => e.id), ['s2', 't3'])
})

test('aucune sélection : page vide', () => {
  assert.deepEqual(JSON.parse(buildSelectedPage(result, [], 'x')).elements, [])
})

test('la page assemblée est acceptée par l’import et associée aux tags du catalogue', () => {
  const parsed = parseInferredPage(buildSelectedPage(result, result.model.assets.map(a => a.topic), 'Tout'))
  assert.equal(parsed.error, undefined)
  const tags = [{ tagId: 'z', path: 'atelier/utilities-boiler/level', name: 'level' }]
  const plan = toPersonalWidgets(parsed.page!, tags)
  assert.equal(plan.widgets.length, 1)
  assert.equal(plan.widgets[0].type, 'gauge')
})

test('libellés et statuts d’erreur', () => {
  assert.equal(assetLabel(result.model.assets[2]), 'utilities / boiler')
  assert.equal(assetLabel({ topic: 'a/b/c', path: [], tags: [] }), 'c')
  assert.equal(discoveryErrorKind(502), 'broker')
  assert.equal(discoveryErrorKind(403), 'forbidden')
  assert.equal(discoveryErrorKind(undefined), 'unavailable')
})
