import { personalCollection } from '../../utils/personalCollection'
type TagPreference = { tagId: string; displayName: string | null; favorite: boolean }
export default personalCollection<{ tags: TagPreference[] }>('/api/me/tags')
