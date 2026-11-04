import { defineEventHandler, getRequestHeader, setResponseHeader, createError } from 'h3'

type Account = { authenticated: boolean; user?: { id: string; email: string }; workspaces: { id: string; name: string; role: string }[] }

export default defineEventHandler(async (event): Promise<Account> => {
  setResponseHeader(event, 'Cache-Control', 'private, no-store')
  const base = useRuntimeConfig().apiBaseUrl.replace(/\/$/, '')
  const cookie = getRequestHeader(event, 'cookie')
  try {
    return await $fetch<Account>(`${base}/api/me`, {
      headers: cookie ? { cookie } : {},
      timeout: 3000,
      retry: 0,
    })
  } catch {
    throw createError({ statusCode: 503, statusMessage: 'Account API unavailable' })
  }
})
