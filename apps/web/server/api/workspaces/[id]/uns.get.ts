import { createError, getRequestHeader, getRouterParam, setResponseHeader } from 'h3'

type UnsAsset = { id: string; parentId: string | null; key: string; name: string; type: string }
type UnsBinding = { assetId: string; tagId: string }
type UnsTag = { tagId: string; sourceId: string; path: string; name: string; valueType: string; unit: string | null }
export default defineEventHandler(async (event): Promise<{ workspaceId: string; projection: string; assets: UnsAsset[]; bindings: UnsBinding[]; tags: UnsTag[] }> => {
  const id = getRouterParam(event, 'id')
  if (!id || !/^[0-9a-f-]{36}$/i.test(id)) throw createError({ statusCode: 400 })
  setResponseHeader(event, 'Cache-Control', 'private, no-store')
  const cookie = getRequestHeader(event, 'cookie')
  const base = useRuntimeConfig().apiBaseUrl.replace(/\/$/, '')
  try {
    return await $fetch<{ workspaceId: string; projection: string; assets: UnsAsset[]; bindings: UnsBinding[]; tags: UnsTag[] }>(base + '/api/workspaces/' + id + '/uns', {
      headers: cookie ? { cookie } : {}, retry: 0, timeout: 3000,
    }) as { workspaceId: string; projection: string; assets: UnsAsset[]; bindings: UnsBinding[]; tags: UnsTag[] }
  } catch (error: unknown) {
    const status = (error as { response?: { status?: number } })?.response?.status
    throw createError({ statusCode: status === 401 || status === 403 || status === 404 ? status : 503 })
  }
})
