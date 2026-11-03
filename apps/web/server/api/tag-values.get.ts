import { createError, defineEventHandler } from 'h3'

type Snapshot = { values: { tagId: string; value: number | string | boolean; observedAt: string }[] }

export default defineEventHandler(async () => {
  const base = useRuntimeConfig().apiBaseUrl.replace(/\/$/, '')
  try {
    return await $fetch<Snapshot>(`${base}/api/tag-values`, { timeout: 3000, retry: 0 })
  } catch {
    throw createError({ statusCode: 503, statusMessage: 'Telemetry API unavailable' })
  }
})
