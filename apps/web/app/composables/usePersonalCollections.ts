import type { PersonalDashboard, PersonalTag } from '~/types/personal'
export function usePersonalDashboards() {
  return useFetch<{ dashboards: PersonalDashboard[] }>('/api/me/dashboards', { server: false, retry: 0, key: 'personal-dashboards' })
}
export function usePersonalTags() {
  return useFetch<{ tags: PersonalTag[] }>('/api/me/tags', { server: false, retry: 0, key: 'personal-tags' })
}
