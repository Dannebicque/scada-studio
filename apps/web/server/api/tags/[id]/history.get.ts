import { createError, getQuery, getRequestHeader, getRouterParam, setResponseHeader } from 'h3'

const RANGES = ['15m', '1h', '6h', '24h', '7d']

export type TagHistoryResponse = {
  tagId: string
  valueType: string
  unit: string | null
  range: string
  from: string
  to: string
  supported: boolean
  retentionDays: number
  bucketSeconds: number
  points: { t: string; avg: number; min: number; max: number; n: number }[]
}

export default defineEventHandler(async (event): Promise<TagHistoryResponse> => {
  const id = getRouterParam(event, 'id')
  if (!id || !/^[0-9a-f-]{36}$/i.test(id)) throw createError({ statusCode: 400 })
  const query = getQuery(event)
  const range = typeof query.range === 'string' && RANGES.includes(query.range) ? query.range : '1h'
  const points = typeof query.points === 'string' && /^\d{1,3}$/.test(query.points) ? query.points : '120'
  setResponseHeader(event, 'Cache-Control', 'private, no-store')
  const cookie = getRequestHeader(event, 'cookie')
  const base = useRuntimeConfig().apiBaseUrl.replace(/\/$/, '')
  try {
    return await $fetch<TagHistoryResponse>(`${base}/api/tags/${id}/history`, {
      query: { range, points }, headers: cookie ? { cookie } : {}, retry: 0, timeout: 8000,
    })
  } catch (error: unknown) {
    const status = (error as { response?: { status?: number } })?.response?.status
    throw createError({ statusCode: status === 401 || status === 403 || status === 404 || status === 422 ? status : 503 })
  }
})
