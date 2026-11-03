import type { WorkspaceRole } from '~/types/access'

export function useWorkspaceRoles() {
  const { locale } = useAppLocale()
  const roles: WorkspaceRole[] = ['viewer', 'editor', 'owner']
  const labels = computed<Record<WorkspaceRole, string>>(() => locale.value === 'fr'
    ? { viewer: 'Lecture', editor: 'Édition', owner: 'Propriétaire' }
    : { viewer: 'Viewer', editor: 'Editor', owner: 'Owner' })
  return { roles, labels }
}
