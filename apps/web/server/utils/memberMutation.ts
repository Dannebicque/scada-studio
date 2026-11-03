import { defineEventHandler, getRequestHeader, getRouterParam, readBody, createError } from 'h3'
import type { H3Event } from 'h3'
export async function memberMutation(event: H3Event, method: 'POST' | 'PATCH' | 'DELETE'): Promise<{ success: boolean }> {
  const id = getRouterParam(event, 'id')
  const userId = getRouterParam(event, 'userId')
  if (!id || !/^[0-9a-f-]{36}$/i.test(id) || (method !== 'POST' && (!userId || !/^[0-9a-f-]{36}$/i.test(userId)))) throw createError({ statusCode: 400 })
  const token = getRequestHeader(event, 'x-csrf-token')
  if (!token) throw createError({ statusCode: 403 })
  const cookie = getRequestHeader(event, 'cookie')
  const base = useRuntimeConfig().apiBaseUrl.replace(/\/$/, '')
  const path = `/api/workspaces/${id}/members${method === 'POST' ? '' : `/${userId}`}`
  const body = method === 'DELETE' ? undefined : JSON.stringify(await readBody(event))
  const response = await fetch(base + path, {
    method,
    headers: { ...(cookie ? { cookie } : {}), 'X-CSRF-Token': token, ...(body ? { 'Content-Type': 'application/json' } : {}) },
    body,
  }).catch(() => { throw createError({ statusCode: 503 }) })
  if (!response.ok) throw createError({ statusCode: [400, 401, 403, 404, 409, 422].includes(response.status) ? response.status : 502 })
  return { success: true }
}
