import type { WorkspaceMember, WorkspaceRole } from '~/types/access'

export function useWorkspaceMembers(workspaceId: Ref<string>, refreshAccount: () => Promise<unknown>) {
  const { locale } = useAppLocale()
  const members = ref<WorkspaceMember[]>([])
  const loading = ref(false)
  const error = ref('')
  const actionBusy = ref(false)
  const actionError = ref('')
  const actionSuccess = ref('')
  const search = ref('')
  const roleFilter = ref('all')
  const page = ref(1)
  const pageSize = 10
  const filtered = computed(() => members.value.filter(member =>
    (roleFilter.value === 'all' || member.role === roleFilter.value)
    && member.email.toLocaleLowerCase().includes(search.value.trim().toLocaleLowerCase()),
  ))
  const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / pageSize)))
  const visible = computed(() => filtered.value.slice((page.value - 1) * pageSize, page.value * pageSize))
  const owners = computed(() => members.value.filter(member => member.role === 'owner').length)
  watch([search, roleFilter], () => { page.value = 1 })

  async function load(resetFilters = false) {
    const id = workspaceId.value
    members.value = []
    error.value = ''
    if (resetFilters) {
      search.value = ''
      roleFilter.value = 'all'
      page.value = 1
    }
    if (!id) return
    loading.value = true
    try {
      const result = await $fetch<{ members: WorkspaceMember[] }>(`/api/workspaces/${encodeURIComponent(id)}/members`, { retry: 0 })
      if (id === workspaceId.value) {
        members.value = result.members.sort((a, b) => a.email.localeCompare(b.email))
        page.value = Math.min(page.value, Math.max(1, Math.ceil(filtered.value.length / pageSize)))
      }
    } catch {
      if (id === workspaceId.value) error.value = locale.value === 'fr' ? 'Impossible de récupérer les accès de cet espace.' : 'Unable to load workspace members.'
    } finally {
      if (id === workspaceId.value) loading.value = false
    }
  }

  async function mutate(method: 'POST' | 'PATCH' | 'DELETE', options: { userId?: string; email?: string; role?: WorkspaceRole } = {}): Promise<boolean> {
    if (!workspaceId.value || actionBusy.value) return false
    actionBusy.value = true
    actionError.value = ''
    actionSuccess.value = ''
    try {
      const base = `/api/workspaces/${encodeURIComponent(workspaceId.value)}/members`
      const csrf = await $fetch<{ token: string }>(base + '/csrf', { retry: 0 })
      await $fetch(base + (options.userId ? `/${encodeURIComponent(options.userId)}` : ''), {
        method,
        headers: { 'X-CSRF-Token': csrf.token },
        ...(method === 'DELETE' ? {} : { body: method === 'POST' ? { email: options.email, role: options.role } : { role: options.role } }),
        retry: 0,
      })
      await load()
      await refreshAccount()
      actionSuccess.value = locale.value === 'fr' ? 'Accès mis à jour.' : 'Access updated.'
      return true
    } catch (cause: unknown) {
      const status = (cause as { statusCode?: number })?.statusCode
      actionError.value = status === 409
        ? (locale.value === 'fr' ? 'Membre déjà présent ou dernier propriétaire protégé.' : 'Existing member or protected last owner.')
        : status === 404
          ? (locale.value === 'fr' ? 'Compte introuvable.' : 'Account not found.')
          : (locale.value === 'fr' ? 'Modification impossible. Réessayez.' : 'Unable to update access. Try again.')
      return false
    } finally {
      actionBusy.value = false
    }
  }

  return { members, loading, error, actionBusy, actionError, actionSuccess, search, roleFilter, page, pageSize, filtered, visible, totalPages, owners, load, mutate }
}
