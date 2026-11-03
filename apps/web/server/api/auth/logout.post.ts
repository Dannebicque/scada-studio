import { defineEventHandler, getRequestHeader, appendResponseHeader, createError } from 'h3'

export default defineEventHandler(async (event): Promise<{ authenticated: boolean }> => {
  const base = useRuntimeConfig().apiBaseUrl.replace(/\/$/, '')
  const cookie = getRequestHeader(event, 'cookie')
  const response = await fetch(`${base}/api/auth/logout`, {
    method: 'POST',
    headers: cookie ? { cookie } : {},
    redirect: 'manual',
  }).catch(() => { throw createError({ statusCode: 503, statusMessage: 'API unavailable' }) })
  for (const value of response.headers.getSetCookie()) appendResponseHeader(event, 'Set-Cookie', value)
  return { authenticated: false }
})
