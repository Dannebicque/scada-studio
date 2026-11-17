// Base de synoptique dérivée du Unified Namespace : arbre site → zone → machine → mesures.
// Source de la hiérarchie, par ordre de priorité : actifs rattachés (bindings), puis préfixe de topic UNS,
// puis clé de source. Les surcharges (libellé, masquage, ordre) restent séparées du modèle généré :
// elles se rejouent sur un modèle régénéré sans jamais le modifier.

export type UnsTag = { tagId: string; sourceId: string; path: string; name: string; valueType: string; unit: string | null; topic?: string | null }
export type UnsAsset = { id: string; parentId: string | null; key: string; name: string; type: string }
export type UnsPayload = { assets: UnsAsset[]; bindings: { assetId: string; tagId: string }[]; tags: UnsTag[] }

export type Override = { label?: string; hidden?: boolean; order?: number }
export type SynopticOverrides = { version: 1; nodes: Record<string, Override>; tags: Record<string, Override> }
export const emptyOverrides = (): SynopticOverrides => ({ version: 1, nodes: {}, tags: {} })

export type WidgetKind = 'value' | 'status' | 'gauge'
export type SynopticTag = { tagId: string; name: string; label: string; hidden: boolean; kind: WidgetKind; unit: string | null }
export type SynopticNode = { id: string; label: string; hidden: boolean; children: SynopticNode[]; tags: SynopticTag[] }

const MAX_DEPTH = 12

/** « automate-01 » → « Automate 01 » : lisible sans prétendre connaître le vocabulaire du site. */
export function humanize(key: string): string {
  const text = key.replace(/[-_]+/g, ' ').trim()
  return text ? text.charAt(0).toUpperCase() + text.slice(1) : key
}

export function widgetKind(tag: Pick<UnsTag, 'valueType' | 'unit'>): WidgetKind {
  if (tag.valueType === 'boolean') return 'status'
  if (tag.valueType !== 'string' && tag.unit === '%') return 'gauge'
  return 'value'
}

function segmentsOf(tag: UnsTag): string[] {
  if (tag.topic) return tag.topic.split('/').filter(Boolean)
  const parts = tag.path.split('/').filter(Boolean)
  return parts.slice(0, -1) // sans le nom du tag
}

/** Valide des surcharges lues depuis un stockage non fiable (localStorage, import). */
export function parseOverrides(raw: unknown): SynopticOverrides {
  const result = emptyOverrides()
  if (!raw || typeof raw !== 'object') return result
  const source = raw as Record<string, unknown>
  for (const group of ['nodes', 'tags'] as const) {
    const entries = source[group]
    if (!entries || typeof entries !== 'object') continue
    for (const [id, value] of Object.entries(entries as Record<string, unknown>).slice(0, 2000)) {
      if (!value || typeof value !== 'object') continue
      const v = value as Record<string, unknown>
      const clean: Override = {}
      if (typeof v.label === 'string' && v.label.trim()) clean.label = v.label.trim().slice(0, 80)
      if (v.hidden === true) clean.hidden = true
      if (typeof v.order === 'number' && Number.isFinite(v.order)) clean.order = v.order
      if (Object.keys(clean).length) Object.assign(result[group], { [id]: clean })
    }
  }
  return result
}

type MutableNode = { id: string; label: string; children: Map<string, MutableNode>; tags: UnsTag[] }

export function buildSynopticTree(uns: UnsPayload, overrides: SynopticOverrides = emptyOverrides()): SynopticNode[] {
  const root: MutableNode = { id: '', label: '', children: new Map(), tags: [] }
  const descend = (from: MutableNode, key: string, label: string): MutableNode => {
    let child = from.children.get(key)
    if (!child) {
      child = { id: from.id ? from.id + '/' + key : key, label, children: new Map(), tags: [] }
      from.children.set(key, child)
    }
    return child
  }

  const assetById = new Map(uns.assets.map(asset => [asset.id, asset]))
  const assetOfTag = new Map(uns.bindings.map(binding => [binding.tagId, binding.assetId]))
  for (const tag of uns.tags) {
    let node = root
    const assetId = assetOfTag.get(tag.tagId)
    const asset = assetId ? assetById.get(assetId) : undefined
    if (asset) {
      const chain: UnsAsset[] = []
      for (let current: UnsAsset | undefined = asset; current && chain.length < MAX_DEPTH; current = current.parentId ? assetById.get(current.parentId) : undefined) chain.unshift(current)
      for (const item of chain) node = descend(node, item.key, item.name || humanize(item.key))
    } else {
      for (const segment of segmentsOf(tag).slice(0, MAX_DEPTH)) node = descend(node, segment, humanize(segment))
    }
    node.tags.push(tag)
  }

  const byOrder = <T extends { label: string }>(items: T[], orderOf: (item: T) => number | undefined) =>
    [...items].sort((a, b) => (orderOf(a) ?? Number.MAX_SAFE_INTEGER) - (orderOf(b) ?? Number.MAX_SAFE_INTEGER) || a.label.localeCompare(b.label, undefined, { numeric: true }))

  const finish = (node: MutableNode): SynopticNode => {
    const own = overrides.nodes[node.id]
    const tags = byOrder(node.tags.map((tag): SynopticTag => {
      const o = overrides.tags[tag.tagId]
      return { tagId: tag.tagId, name: tag.name, label: o?.label ?? tag.name, hidden: o?.hidden === true, kind: widgetKind(tag), unit: tag.unit }
    }), tag => overrides.tags[tag.tagId]?.order)
    const children = byOrder([...node.children.values()].map(finish), child => overrides.nodes[child.id]?.order)
    return { id: node.id, label: own?.label ?? node.label, hidden: own?.hidden === true, children, tags }
  }
  return byOrder([...root.children.values()].map(finish), node => overrides.nodes[node.id]?.order)
}

/** Retire ce qui est masqué (un nœud masqué masque tout son sous-arbre) et les branches devenues vides. */
export function visibleTree(nodes: SynopticNode[]): SynopticNode[] {
  const result: SynopticNode[] = []
  for (const node of nodes) {
    if (node.hidden) continue
    const children = visibleTree(node.children)
    const tags = node.tags.filter(tag => !tag.hidden)
    if (children.length || tags.length) result.push({ ...node, children, tags })
  }
  return result
}

export function countTags(nodes: SynopticNode[]): number {
  return nodes.reduce((sum, node) => sum + node.tags.length + countTags(node.children), 0)
}
