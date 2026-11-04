import { defineEventHandler, getRequestHeader, getRouterParam, appendResponseHeader, createError } from 'h3'
export default defineEventHandler(async (event): Promise<{ token: string }> => {
  const id = getRouterParam(event, 'id')
  if (!id || !/^[0-9a-f-]{36}$/i.test(id)) throw createError({ statusCode: 400 })
  const base = useRuntimeConfig().apiBaseUrl.replace(/\/$/, '')
  const cookie = getRequestHeader(event, 'cookie')
  const response = await fetch(`${base}/api/workspaces/${id}/members/csrf`, { headers: cookie ? { cookie } : {} })
  if (!response.ok) throw createError({ statusCode: response.status })
  for (const value of response.headers.getSetCookie()) appendResponseHeader(event, 'Set-Cookie', value)
  return await response.json() as { token: string }
})
