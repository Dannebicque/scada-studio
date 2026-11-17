import { defineEventHandler, setResponseHeader } from 'h3'
import { unsAdminRequest } from '../../../../utils/unsAdmin'

export default defineEventHandler((event) => {
  setResponseHeader(event, 'Cache-Control', 'private, no-store')
  return unsAdminRequest<object>(event, 'provision', 20_000)
})
