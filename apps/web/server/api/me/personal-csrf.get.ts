import { createError, getRequestHeader, appendResponseHeader, setResponseHeader } from 'h3'
export default defineEventHandler(async (event) => {
  setResponseHeader(event, 'Cache-Control', 'private, no-store')
  const cookie = getRequestHeader(event, 'cookie')
  const base = useRuntimeConfig().apiBaseUrl.replace(/\/$/, '')
  const response = await fetch(base + '/api/me/personal-csrf', { headers: cookie ? { cookie } : {} })
    .catch(() => { throw createError({ statusCode: 503 }) })
  if (!response.ok) throw createError({ statusCode: response.status === 401 ? 401 : 502 })
  for (const value of response.headers.getSetCookie()) appendResponseHeader(event, 'set-cookie', value)
  return response.json() as Promise<{ token: string }>
})
