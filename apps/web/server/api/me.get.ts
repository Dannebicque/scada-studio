import { defineEventHandler, getRequestHeader, setResponseHeader, createError } from 'h3'

export default defineEventHandler(async (event) => {
  const base = useRuntimeConfig().apiBaseUrl.replace(/\/$/, '')
  const cookie = getRequestHeader(event, 'cookie')
  try {
    return await $fetch(`${base}/api/me`, {
      headers: cookie ? { cookie } : {},
      timeout: 3000,
      retry: 0,
    })
  } catch {
    setResponseHeader(event, 'Cache-Control', 'no-store')
    throw createError({ statusCode: 503, statusMessage: 'Account API unavailable' })
  }
})
