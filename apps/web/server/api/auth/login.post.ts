import { defineEventHandler, readBody, createError, appendResponseHeader, setResponseHeader } from 'h3'

export default defineEventHandler(async (event): Promise<{ authenticated: boolean }> => {
  const payload = await readBody(event)
  const base = useRuntimeConfig().apiBaseUrl.replace(/\/$/, '')
  const response = await fetch(`${base}/api/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
    redirect: 'manual',
  }).catch(() => { throw createError({ statusCode: 503, statusMessage: 'API unavailable' }) })
  if (!response.ok) throw createError({ statusCode: 401, statusMessage: 'Authentication failed' })
  for (const cookie of response.headers.getSetCookie()) {
    appendResponseHeader(event, 'Set-Cookie', cookie)
  }
  setResponseHeader(event, 'Cache-Control', 'no-store')
  return { authenticated: true }
})
