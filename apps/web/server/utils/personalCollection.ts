import { createError, defineEventHandler, getRequestHeader, setResponseHeader } from 'h3'
export function personalCollection<T>(path: '/api/me/dashboards' | '/api/me/tags') {
  return defineEventHandler(async (event): Promise<T> => {
    setResponseHeader(event, 'Cache-Control', 'private, no-store')
    const cookie = getRequestHeader(event, 'cookie')
    const base = useRuntimeConfig().apiBaseUrl.replace(/\/$/, '')
    try {
      return await $fetch<T>(base + path, {
        headers: cookie ? { cookie } : {},
        retry: 0,
        timeout: 3000,
      })
    } catch (error: unknown) {
      const status = (error as { response?: { status?: number } })?.response?.status
      throw createError({ statusCode: status === 401 || status === 403 ? status : 503, statusMessage: 'Personal data unavailable' })
    }
  })
}
