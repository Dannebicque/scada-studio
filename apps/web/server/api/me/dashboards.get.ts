import { personalCollection } from '../../utils/personalCollection'
import type { PersonalDashboard } from '~/types/personal'
export default personalCollection<{ dashboards: PersonalDashboard[] }>('/api/me/dashboards')
