import { createError, defineEventHandler } from 'h3'

type Collection = { member?: unknown[]; 'hydra:member'?: unknown[] }
type Workspace = { id: string; name: string; slug: string }
type Source = { id: string; name: string; key: string; connectorType: string; enabled: boolean }
type Unit = { code: string; symbol: string }
type Tag = { id: string; name: string; key: string; valueType: string; readOnly: boolean; unit?: Unit | string | null }

function members<T>(response: Collection | T[]): T[] {
  if (Array.isArray(response)) return response as T[]
  const list = response.member ?? response['hydra:member']
  if (!Array.isArray(list)) throw new Error('Invalid API collection')
  return list as T[]
}

export default defineEventHandler(async () => {
  const base = useRuntimeConfig().apiBaseUrl.replace(/\/$/, '')
  try {
    const [workspaces, sources, tags] = await Promise.all([
      $fetch<Collection | Workspace[]>(`${base}/api/workspaces`, { timeout: 3000, retry: 0 }),
      $fetch<Collection | Source[]>(`${base}/api/data_sources`, { timeout: 3000, retry: 0 }),
      $fetch<Collection | Tag[]>(`${base}/api/tags`, { timeout: 3000, retry: 0 }),
    ])
    return {
      workspaces: members<Workspace>(workspaces).map(({ id, name, slug }) => ({ id, name, slug })),
      sources: members<Source>(sources).map(({ id, name, key, connectorType, enabled }) => ({ id, name, key, connectorType, enabled })),
      tags: members<Tag>(tags).map(({ id, name, key, valueType, readOnly, unit }) => ({ id, name, key, valueType, readOnly, unit: typeof unit === 'object' && unit ? unit.symbol : null })),
    }
  } catch {
    throw createError({ statusCode: 503, statusMessage: 'Catalog API unavailable' })
  }
})
