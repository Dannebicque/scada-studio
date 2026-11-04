import { personalCollection } from '../../utils/personalCollection'
import type { PersonalTag } from '~/types/personal'
export default personalCollection<{ tags: PersonalTag[] }>('/api/me/tags')
