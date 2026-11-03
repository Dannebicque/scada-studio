import { createError, defineEventHandler, getRequestHeader, getRouterParam, setResponseHeader } from 'h3'

type Member = { userId: string; email: string; role: 'viewer' | 'editor' | 'owner' }
export default defineEventHandler(async (event): Promise<{ members: Member[] }> => {
  setResponseHeader(event, 'Cache-Control', 'private, no-store')
  const id = getRouterParam(event, 'id')
  if (!id || !/^[0-9a-f-]{36}$/i.test(id)) throw createError({ statusCode: 400, statusMessage: 'Invalid workspace' })
  const cookie = getRequestHeader(event, 'cookie')
  const base = useRuntimeConfig().apiBaseUrl.replace(/\/$/, '')
  try {
    return await $fetch<{ members: Member[] }>(`${base}/api/workspaces/${encodeURIComponent(id)}/members`, {
      headers: cookie ? { cookie } : {}, retry: 0, timeout: 3000,
    })
  } catch (error: unknown) {
    const status = (error as { response?: { status?: number } })?.response?.status
    throw createError({ statusCode: status === 401 || status === 403 || status === 404 ? status : 503, statusMessage: 'Unable to load workspace members' })
  }
})
