import type { Account } from '~/types/access'

export function useAccount() {
  return useFetch<Account>('/api/me', { server: false, retry: 0, key: 'current-account' })
}
