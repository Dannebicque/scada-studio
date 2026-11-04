import type { PersonalDashboard, PersonalTag } from '~/types/personal'

export function usePersonalActions() {
  const busy = ref(false)
  const error = ref('')
  const { refresh: refreshDashboards } = usePersonalDashboards()
  const { refresh: refreshTags } = usePersonalTags()

  async function execute(path: string, method: 'POST' | 'PUT' | 'DELETE', body?: object) {
    if (busy.value) return false
    busy.value = true
    error.value = ''
    try {
      const { token } = await $fetch<{ token: string }>('/api/me/personal-csrf', { retry: 0 })
      await $fetch(path, { method, headers: { 'X-CSRF-Token': token }, ...(body ? { body } : {}), retry: 0 })
      await Promise.all([refreshDashboards(), refreshTags()])
      return true
    } catch {
      error.value = 'Unable to save changes / Enregistrement impossible.'
      return false
    } finally {
      busy.value = false
    }
  }

  function createDashboard(input: Pick<PersonalDashboard, 'workspaceId' | 'name' | 'slug' | 'widgets'>) {
    return execute('/api/me/dashboards', 'POST', input)
  }
  function deleteDashboard(id: string) {
    return execute('/api/me/dashboards/' + encodeURIComponent(id), 'DELETE')
  }
  function saveTag(tag: PersonalTag) {
    return execute('/api/me/tags/' + encodeURIComponent(tag.tagId), 'PUT', { favorite: tag.favorite, displayName: tag.displayName })
  }
  return { busy, error, createDashboard, deleteDashboard, saveTag }
}
