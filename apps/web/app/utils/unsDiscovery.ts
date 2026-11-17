// Types et logique pure de l'écran de découverte UNS (réponse de POST /uns/discover).

export type DiscoveryTag = { name: string; unit?: string; widget: string; kind: string }
export type DiscoveryAsset = { topic: string; path: string[]; tags: DiscoveryTag[] }
export type DiscoveryPageElement = { id: string; type: string; properties?: { topic?: string }; [key: string]: unknown }
export type DiscoveryPage = { schemaVersion: number; id: string; kind: string; title: string; elements: DiscoveryPageElement[] }
export type DiscoveryResult = {
  prefix: string
  frames: number
  truncated: boolean
  model: { root: string; frames: number; assets: DiscoveryAsset[] }
  pages: DiscoveryPage[]
}

const SECTION = 'core.section'
const parent = (topic: string) => topic.slice(0, Math.max(0, topic.lastIndexOf('/')))

/** Libellé d'un asset : son chemin sous la racine commune, ou son dernier segment à défaut. */
export function assetLabel(asset: DiscoveryAsset): string {
  return asset.path.length ? asset.path.join(' / ') : asset.topic.split('/').at(-1) ?? asset.topic
}

/**
 * Assemble en une seule page les éléments proposés pour les assets retenus. Une section appartient à
 * l'asset de même topic ; un widget appartient à l'asset parent de son topic (asset/tag).
 * La grille d'origine n'est pas conservée : un tableau personnel suit l'ordre de lecture.
 */
export function buildSelectedPage(result: DiscoveryResult, selectedTopics: Iterable<string>, title: string): string {
  const selected = new Set(selectedTopics)
  const elements = result.pages.flatMap(page => page.elements).filter((el) => {
    const topic = el.properties?.topic
    if (typeof topic !== 'string') return false
    return selected.has(el.type === SECTION ? topic : parent(topic))
  })
  return JSON.stringify({ schemaVersion: 1, id: 'discovery', kind: 'dashboard', title, elements })
}

export type DiscoveryError = 'forbidden' | 'notFound' | 'invalid' | 'broker' | 'unavailable' | 'unauthenticated'

export function discoveryErrorKind(status: number | undefined): DiscoveryError {
  switch (status) {
    case 401: return 'unauthenticated'
    case 403: return 'forbidden'
    case 404: return 'notFound'
    case 400: case 422: return 'invalid'
    case 502: return 'broker'
    default: return 'unavailable'
  }
}
