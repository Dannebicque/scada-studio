import { createError, defineEventHandler } from 'h3'

type Health = { status: 'ok'; service: string; timestamp: string }

export default defineEventHandler(async () => {
  const config = useRuntimeConfig()
  const apiBaseUrl = config.apiBaseUrl.replace(/\/$/, '')
  try {
    const response = await $fetch<Health>(`${apiBaseUrl}/api/health`, {
      timeout: 3000,
      retry: 0,
    })
    if (response.status !== 'ok' || typeof response.service !== 'string' || typeof response.timestamp !== 'string') {
      throw new Error('Unexpected health response')
    }
    return response
  } catch {
    throw createError({ statusCode: 503, statusMessage: 'Backend API unavailable' })
  }
})
