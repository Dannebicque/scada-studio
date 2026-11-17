import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildSynopticTree, visibleTree, parseOverrides, humanize, widgetKind, emptyOverrides, countTags, type UnsPayload, type UnsTag } from '../app/utils/synopticModel.ts'

const tag = (id: string, topic: string | null, name: string, extra: Partial<UnsTag> = {}): UnsTag => ({ tagId: id, sourceId: 's', path: `ws/src/${name}`, name, valueType: 'float', unit: null, topic, ...extra })
const uns = (tags: UnsTag[], assets: UnsPayload['assets'] = [], bindings: UnsPayload['bindings'] = []): UnsPayload => ({ assets, bindings, tags })

test('hiérarchie dérivée du préfixe de topic UNS', () => {
  const tree = buildSynopticTree(uns([
    tag('1', 'acme/paris/atelier-1/presse', 'temperature'),
    tag('2', 'acme/paris/atelier-1/presse', 'running', { valueType: 'boolean' }),
    tag('3', 'acme/paris/atelier-2/four', 'temperature'),
  ]))
  assert.equal(tree.length, 1)
  assert.equal(tree[0]!.id, 'acme')
  const paris = tree[0]!.children[0]!
  assert.deepEqual(paris.children.map(c => c.label), ['Atelier 1', 'Atelier 2'])
  assert.equal(paris.children[0]!.children[0]!.id, 'acme/paris/atelier-1/presse')
  assert.equal(paris.children[0]!.children[0]!.tags.length, 2)
  assert.equal(countTags(tree), 3)
})

test('repli sans topic : chemin logique sans le nom du tag', () => {
  const tree = buildSynopticTree(uns([tag('1', null, 'temperature', { path: 'atelier-demo/automate-01/temperature' })]))
  assert.equal(tree[0]!.id, 'atelier-demo')
  assert.equal(tree[0]!.children[0]!.tags[0]!.name, 'temperature')
})

test('les actifs rattachés priment sur le topic', () => {
  const assets = [
    { id: 'a1', parentId: null, key: 'usine', name: 'Usine de Troyes', type: 'site' },
    { id: 'a2', parentId: 'a1', key: 'presse', name: 'Presse 1', type: 'machine' },
  ]
  const tree = buildSynopticTree(uns([tag('1', 'x/y', 'temperature'), tag('2', 'x/y', 'pression')], assets, [{ assetId: 'a2', tagId: '1' }]))
  const ids = tree.map(n => n.id).sort()
  assert.deepEqual(ids, ['usine', 'x'])
  const usine = tree.find(n => n.id === 'usine')!
  assert.equal(usine.label, 'Usine de Troyes')
  assert.equal(usine.children[0]!.label, 'Presse 1')
  assert.equal(usine.children[0]!.tags[0]!.tagId, '1')
})

test('cycle d’actifs : pas de boucle infinie', () => {
  const assets = [{ id: 'a', parentId: 'b', key: 'a', name: 'A', type: 't' }, { id: 'b', parentId: 'a', key: 'b', name: 'B', type: 't' }]
  const tree = buildSynopticTree(uns([tag('1', null, 'x')], assets, [{ assetId: 'a', tagId: '1' }]))
  assert.ok(countTags(tree) === 1)
})

test('surcharges : libellé, ordre, masquage, rejouées sur le modèle généré', () => {
  const payload = uns([tag('1', 'a/m1', 't'), tag('2', 'a/m2', 't'), tag('3', 'a/m2', 'p')])
  const base = buildSynopticTree(payload)
  assert.deepEqual(base[0]!.children.map(c => c.id), ['a/m1', 'a/m2'])
  const o = parseOverrides({ version: 1, nodes: { 'a/m2': { label: 'Four principal', order: 0 }, 'a/m1': { hidden: true } }, tags: { '3': { hidden: true, label: 'Pression' } } })
  const tree = buildSynopticTree(payload, o)
  assert.deepEqual(tree[0]!.children.map(c => c.label), ['Four principal', 'M1'])
  const shown = visibleTree(tree)
  assert.deepEqual(shown[0]!.children.map(c => c.id), ['a/m2'])
  assert.deepEqual(shown[0]!.children[0]!.tags.map(t => t.tagId), ['2'])
})

test('une branche entièrement masquée disparaît', () => {
  const tree = buildSynopticTree(uns([tag('1', 'a/m', 't')]), parseOverrides({ tags: { '1': { hidden: true } } }))
  assert.deepEqual(visibleTree(tree), [])
})

test('parseOverrides rejette le contenu non fiable', () => {
  assert.deepEqual(parseOverrides(null), emptyOverrides())
  assert.deepEqual(parseOverrides('x'), emptyOverrides())
  const o = parseOverrides({ nodes: { a: { label: '   ', hidden: 'yes', order: 'x' }, b: 3, c: { label: 'x'.repeat(200) } }, tags: [] })
  assert.deepEqual(Object.keys(o.nodes), ['c'])
  assert.equal(o.nodes.c!.label!.length, 80)
})

test('type de widget proposé', () => {
  assert.equal(widgetKind({ valueType: 'boolean', unit: null }), 'status')
  assert.equal(widgetKind({ valueType: 'float', unit: '%' }), 'gauge')
  assert.equal(widgetKind({ valueType: 'float', unit: '°C' }), 'value')
  assert.equal(widgetKind({ valueType: 'string', unit: '%' }), 'value')
  assert.equal(humanize('automate-01'), 'Automate 01')
})
