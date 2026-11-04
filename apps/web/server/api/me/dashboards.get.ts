import { personalCollection } from '../../utils/personalCollection'
type Dashboard = { id: string; workspaceId: string; name: string; slug: string; widgets: unknown[] }
export default personalCollection<{ dashboards: Dashboard[] }>('/api/me/dashboards')
