// Import d'une page dashboard inférée depuis le UNS (voir tools/uns-infer).
// Fonctions pures, sans dépendance Nuxt : testables avec `node --test`.

export const MAX_IMPORT_BYTES = 1_000_000
export const MAX_IMPORT_ELEMENTS = 300
export const MAX_PERSONAL_WIDGETS = 100 // limite imposée par l'API (PersonalDashboardWriteController)

export type ImportLayout = { x: number; y: number; width: number; height: number }
export type ImportElement = { id: string; type: string; label: string; topic: string | null; binding: string | null; unit: string | null; layout: ImportLayout }
export type ImportPage = { id: string; title: string; elements: ImportElement[] }
export type ImportError = 'tooLarge' | 'invalidJson' | 'invalidShape' | 'unsupportedKind' | 'tooManyElements' | 'duplicateId'
export type ImportTag = { tagId: string; path: string; name: string }
export type PersonalWidget = { type: 'value' | 'gauge' | 'status' | 'chart'; tagId: string; title: string }

export const SECTION_TYPE = 'core.section'
const WIDGET_TYPES: Record<string, PersonalWidget['type']> = {
  'core.value': 'value',
  'core.gauge': 'gauge',
  'core.status': 'status',
  'core.alarm': 'status',
  'core.state': 'status',
  'core.timeseries': 'chart',
}

const isRecord = (v: unknown): v is Record<string, unknown> => typeof v === 'object' && v !== null && !Array.isArray(v)
const text = (v: unknown, max: number): string | null => (typeof v === 'string' && v.length > 0 ? v.slice(0, max) : null)
const int = (v: unknown, min: number, max: number, fallback: number): number =>
  typeof v === 'number' && Number.isFinite(v) ? Math.min(max, Math.max(min, Math.round(v))) : fallback

export function parseInferredPage(source: string): { page: ImportPage; error?: undefined } | { page?: undefined; error: ImportError } {
  if (source.length > MAX_IMPORT_BYTES) return { error: 'tooLarge' }
  let raw: unknown
  try { raw = JSON.parse(source) } catch { return { error: 'invalidJson' } }
  if (!isRecord(raw) || raw.schemaVersion !== 1 || !Array.isArray(raw.elements)) return { error: 'invalidShape' }
  if (raw.kind !== 'dashboard') return { error: 'unsupportedKind' }
  const title = text(raw.title, 120)
  const id = text(raw.id, 200)
  if (!title || !id) return { error: 'invalidShape' }
  if (raw.elements.length > MAX_IMPORT_ELEMENTS) return { error: 'tooManyElements' }

  const seen = new Set<string>()
  const elements: ImportElement[] = []
  for (const item of raw.elements) {
    if (!isRecord(item)) return { error: 'invalidShape' }
    const elementId = text(item.id, 200)
    const type = text(item.type, 64)
    if (!elementId || !type || !isRecord(item.properties) || !isRecord(item.bindings)) return { error: 'invalidShape' }
    if (seen.has(elementId)) return { error: 'duplicateId' }
    seen.add(elementId)
    const layout = isRecord(item.layout) ? item.layout : {}
    const width = int(layout.width, 1, 12, 3)
    elements.push({
      id: elementId,
      type,
      label: text(item.properties.label, 120) ?? elementId,
      topic: text(item.properties.topic, 512),
      binding: text(item.bindings.value, 512),
      unit: text(item.properties.unit, 20),
      layout: { x: int(layout.x, 0, 11, 0), y: int(layout.y, 0, 10_000, 0), width, height: int(layout.height, 1, 12, 2) },
    })
  }
  // Ordre DOM = ordre de lecture (haut→bas, gauche→droite) pour les lecteurs d'écran.
  elements.sort((a, b) => a.layout.y - b.layout.y || a.layout.x - b.layout.x)
  return { page: { id, title, elements } }
}

/**
 * Associe un élément à un tag du catalogue, sans jamais deviner. Deux règles, par ordre :
 *  1. les deux derniers segments du topic (équipement/tag) égalent source/tag ;
 *  2. le binding « chemin.relatif.de.l.asset.tag » égale source/tag, où la source est le chemin relatif
 *     de l'asset en minuscules joint par « - » (règle de nommage de `app:uns:provision`).
 * Une correspondance ambiguë ou absente donne null.
 */
export function matchTag(topic: string | null, tags: ImportTag[], binding: string | null = null): ImportTag | null {
  const unique = (found: ImportTag[]) => (found.length === 1 ? found[0]! : null)
  if (topic) {
    const tail = topic.split('/').filter(Boolean).slice(-2).join('/')
    if (tail.includes('/')) {
      const found = tags.filter(tag => tag.path === topic || tag.path.endsWith('/' + tail))
      if (found.length) return unique(found)
    }
  }
  if (binding) {
    const parts = binding.split('.').filter(Boolean)
    if (parts.length >= 2) {
      const tail = parts.slice(0, -1).join('-').toLowerCase() + '/' + parts[parts.length - 1]
      return unique(tags.filter(tag => tag.path.endsWith('/' + tail)))
    }
  }
  return null
}

export function toPersonalWidgets(page: ImportPage, tags: ImportTag[]): { widgets: PersonalWidget[]; unmatched: ImportElement[]; duplicates: number } {
  const widgets: PersonalWidget[] = []
  const unmatched: ImportElement[] = []
  const used = new Set<string>()
  let duplicates = 0
  for (const el of page.elements) {
    const type = WIDGET_TYPES[el.type]
    if (!type) { if (el.type !== SECTION_TYPE) unmatched.push(el); continue }
    const tag = matchTag(el.topic, tags, el.binding)
    if (!tag) { unmatched.push(el); continue }
    if (used.has(tag.tagId)) { duplicates += 1; continue }
    used.add(tag.tagId)
    widgets.push({ type, tagId: tag.tagId, title: el.label.slice(0, 120) })
  }
  return { widgets, unmatched, duplicates }
}
