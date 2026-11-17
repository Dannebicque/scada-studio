import { createError, getRequestHeader, getRouterParam, readBody, appendResponseHeader } from 'h3'
import type { H3Event } from 'h3'

const UUID = /^[0-9a-f-]{36}$/i
const FORWARDED = [400, 401, 403, 404, 422]

/** Relaie une requête d'administration UNS vers l'API Symfony (cookie de session + jeton CSRF). */
export async function unsAdminRequest<T>(event: H3Event, path: 'discovery/csrf' | 'discover' | 'provision', timeoutMs: number): Promise<T> {
  const id = getRouterParam(event, 'id')
  if (!id || !UUID.test(id)) throw createError({ statusCode: 400 })
  const isToken = path === 'discovery/csrf'
  const csrf = getRequestHeader(event, 'x-csrf-token')
  if (!isToken && !csrf) throw createError({ statusCode: 403 })
  const cookie = getRequestHeader(event, 'cookie')
  const base = useRuntimeConfig().apiBaseUrl.replace(/\/$/, '')
  const body = isToken ? undefined : JSON.stringify(await readBody(event))
  const response = await fetch(`${base}/api/workspaces/${id}/uns/${path}`, {
    method: isToken ? 'GET' : 'POST',
    headers: {
      ...(cookie ? { cookie } : {}),
      ...(csrf ? { 'X-CSRF-Token': csrf } : {}),
      ...(body ? { 'Content-Type': 'application/json' } : {}),
    },
    body,
    signal: AbortSignal.timeout(timeoutMs),
  }).catch(() => { throw createError({ statusCode: 503 }) })
  if (!response.ok) throw createError({ statusCode: FORWARDED.includes(response.status) ? response.status : 502 })
  for (const value of response.headers.getSetCookie()) appendResponseHeader(event, 'Set-Cookie', value)
  return await response.json() as T
}
