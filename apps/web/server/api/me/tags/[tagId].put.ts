import { getRouterParam, createError } from 'h3'
import { personalMutation } from '../../../utils/personalMutation'
export default defineEventHandler((event) => {
  const id = getRouterParam(event, 'tagId')
  if (!id || !/^[0-9a-f-]{36}$/i.test(id)) throw createError({ statusCode: 400 })
  return personalMutation(event, 'PUT', '/api/me/tags/' + encodeURIComponent(id))
})
