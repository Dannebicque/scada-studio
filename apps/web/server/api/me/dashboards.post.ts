import { personalMutation } from '../../utils/personalMutation'
export default defineEventHandler(event => personalMutation(event, 'POST', '/api/me/dashboards'))
