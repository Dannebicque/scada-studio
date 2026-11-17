import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildChart, sampleRows, summarize } from '../app/utils/historyChart.ts'

const t0 = Date.parse('2026-11-17T10:00:00Z')
const pt = (s: number, avg: number, min = avg, max = avg, n = 1) => ({ t: new Date(t0 + s * 1000).toISOString(), avg, min, max, n })
const from = new Date(t0).toISOString()
const to = new Date(t0 + 3600_000).toISOString()

test('aucune donnée : pas de tracé ni de résumé', () => {
  assert.equal(buildChart([], from, to, 30), null)
  assert.equal(summarize([]), null)
})

test('le tracé suit le temps et tient dans la zone', () => {
  const chart = buildChart([pt(0, 10), pt(1800, 20), pt(3600, 30)], from, to, 1800)!
  const coords = [...chart.line.matchAll(/[ML]([\d.]+) ([\d.]+)/g)].map(m => [Number(m[1]), Number(m[2])])
  assert.equal(coords.length, 3)
  assert.ok(coords.every(([x, y]) => x >= 0 && x <= 300 && y >= 0 && y <= 100))
  assert.equal(coords[1]![0], 150, 'le point de mi-parcours est au milieu de l’axe du temps')
  assert.ok(coords[0]![1]! > coords[2]![1]!, 'une valeur plus grande est plus haut (y plus petit)')
})

test('un trou dans les données interrompt la ligne', () => {
  const chart = buildChart([pt(0, 1), pt(30, 2), pt(1800, 3), pt(1830, 4)], from, to, 30)!
  assert.equal((chart.line.match(/M/g) ?? []).length, 2)
  assert.equal((chart.band.match(/M/g) ?? []).length, 2)
})

test('série constante ou point unique : axe non dégénéré', () => {
  const flat = buildChart([pt(0, 5), pt(60, 5)], from, to, 60)!
  assert.ok(flat.yMax > flat.yMin)
  const one = buildChart([pt(100, 7)], from, to, 60)!
  assert.equal(one.band, '')
  assert.ok(one.last)
})

test('résumé : extrema et moyenne pondérée', () => {
  const s = summarize([pt(0, 10, 8, 12, 1), pt(60, 20, 18, 25, 3)])!
  assert.equal(s.min, 8)
  assert.equal(s.max, 25)
  assert.equal(s.count, 4)
  assert.equal(s.mean, (10 * 1 + 20 * 3) / 4)
})

test('échantillon du tableau : borné, premier et dernier conservés', () => {
  const many = Array.from({ length: 100 }, (_, i) => pt(i * 30, i))
  const rows = sampleRows(many, 12)
  assert.equal(rows.length, 12)
  assert.equal(rows[0], many[0])
  assert.equal(rows.at(-1), many.at(-1))
  assert.equal(sampleRows(many.slice(0, 5), 12).length, 5)
})

test('axe fixe (booléen) : 0 en bas, 1 en haut, sans marge', () => {
  const chart = buildChart([pt(0, 0), pt(60, 1)], from, to, 60, 300, 100, [0, 1])!
  assert.equal(chart.yMin, 0)
  assert.equal(chart.yMax, 1)
  const ys = [...chart.line.matchAll(/[ML][\d.]+ ([\d.]+)/g)].map(m => Number(m[1]))
  assert.equal(ys[0], 96)
  assert.equal(ys[1], 4)
})
