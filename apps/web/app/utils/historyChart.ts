// Géométrie et résumés d'une série d'historique (réponse de GET /api/tags/{id}/history).

export type HistoryPoint = { t: string; avg: number; min: number; max: number; n: number }
export type ChartGeometry = {
  width: number
  height: number
  yMin: number
  yMax: number
  line: string
  band: string
  last: { x: number; y: number } | null
}

const pad = 4

/** Un trou plus grand que ce multiple de l'intervalle d'agrégation interrompt le tracé. */
const GAP_FACTOR = 3

/**
 * Convertit les points en chemins SVG. L'abscisse suit le temps (pas l'indice) : une coupure de
 * collecte reste visible comme un trou. Retourne null sans point.
 */
export function buildChart(points: HistoryPoint[], fromIso: string, toIso: string, bucketSeconds: number, width = 300, height = 100, fixedRange: [number, number] | null = null): ChartGeometry | null {
  if (!points.length) return null
  const from = Date.parse(fromIso)
  const span = Math.max(1, Date.parse(toIso) - from)
  let lo = Math.min(...points.map(p => p.min))
  let hi = Math.max(...points.map(p => p.max))
  if (fixedRange) { lo = fixedRange[0]; hi = fixedRange[1] } // ex. booléen : axe 0–1
  else if (lo === hi) { lo -= 1; hi += 1 } // série constante : axe centré sur la valeur
  const margin = fixedRange ? 0 : (hi - lo) * 0.05
  const yMin = lo - margin
  const yMax = hi + margin
  const x = (iso: string) => pad + ((Date.parse(iso) - from) / span) * (width - 2 * pad)
  const y = (value: number) => pad + (1 - (value - yMin) / (yMax - yMin)) * (height - 2 * pad)
  const f = (n: number) => n.toFixed(1)

  const segments: HistoryPoint[][] = []
  let current: HistoryPoint[] = []
  for (const p of points) {
    const previous = current.at(-1)
    if (previous && Date.parse(p.t) - Date.parse(previous.t) > bucketSeconds * 1000 * GAP_FACTOR) { segments.push(current); current = [] }
    current.push(p)
  }
  segments.push(current)

  const line = segments.map(seg => seg.map((p, i) => `${i ? 'L' : 'M'}${f(x(p.t))} ${f(y(p.avg))}`).join(' ')).join(' ')
  const band = segments.filter(seg => seg.length > 1).map(seg =>
    'M' + seg.map(p => `${f(x(p.t))} ${f(y(p.max))}`).join(' L')
    + ' L' + [...seg].reverse().map(p => `${f(x(p.t))} ${f(y(p.min))}`).join(' L') + ' Z').join(' ')
  const lastPoint = points.at(-1)!
  return { width, height, yMin, yMax, line, band, last: { x: x(lastPoint.t), y: y(lastPoint.avg) } }
}

export type HistorySummary = { min: number; max: number; mean: number; count: number }

/** Minimum, maximum et moyenne pondérée par le nombre d'échantillons de chaque intervalle. */
export function summarize(points: HistoryPoint[]): HistorySummary | null {
  if (!points.length) return null
  const count = points.reduce((sum, p) => sum + p.n, 0)
  return {
    min: Math.min(...points.map(p => p.min)),
    max: Math.max(...points.map(p => p.max)),
    mean: points.reduce((sum, p) => sum + p.avg * p.n, 0) / Math.max(1, count),
    count,
  }
}

/** Au plus `limit` lignes réparties sur la série, pour le tableau de données alternatif. */
export function sampleRows(points: HistoryPoint[], limit = 12): HistoryPoint[] {
  if (points.length <= limit) return points
  const step = (points.length - 1) / (limit - 1)
  return Array.from({ length: limit }, (_, i) => points[Math.round(i * step)]!)
}
