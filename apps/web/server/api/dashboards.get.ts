import { createError, defineEventHandler } from 'h3'

type Dashboard = { id: string; name: string; slug: string; widgets: { type: string; tagId: string; title: string }[] }
type Collection = { member?: Dashboard[]; 'hydra:member'?: Dashboard[] }

export default defineEventHandler(async () => {
  const base = useRuntimeConfig().apiBaseUrl.replace(/\/$/, '')
  try {
    const response = await $fetch<Collection | Dashboard[]>(`${base}/api/dashboards`, { timeout: 3000, retry: 0 })
    const dashboards = Array.isArray(response) ? response : (response.member ?? response['hydra:member'])
    if (!Array.isArray(dashboards)) throw new Error('Invalid dashboards response')
    return { dashboards: dashboards.map(({ id, name, slug, widgets }) => ({ id, name, slug, widgets })) }
  } catch {
    throw createError({ statusCode: 503, statusMessage: 'Dashboard API unavailable' })
  }
})
