import { createError, getRequestHeader, readBody } from 'h3'
import type { H3Event } from 'h3'

export async function personalMutation(event: H3Event, method: 'POST' | 'PUT' | 'PATCH' | 'DELETE', path: string): Promise<unknown> {
  const token = getRequestHeader(event, 'x-csrf-token')
  if (!token) throw createError({ statusCode: 403 })
  const cookie = getRequestHeader(event, 'cookie')
  const body = method === 'DELETE' ? undefined : JSON.stringify(await readBody(event))
  const base = useRuntimeConfig().apiBaseUrl.replace(/\/$/, '')
  const response = await fetch(base + path, {
    method,
    headers: { ...(cookie ? { cookie } : {}), 'X-CSRF-Token': token, ...(body ? { 'Content-Type': 'application/json' } : {}) },
    body,
  }).catch(() => { throw createError({ statusCode: 503 }) })
  if (!response.ok) throw createError({ statusCode: [400, 401, 403, 404, 409, 422].includes(response.status) ? response.status : 502 })
  return response.json()
}
