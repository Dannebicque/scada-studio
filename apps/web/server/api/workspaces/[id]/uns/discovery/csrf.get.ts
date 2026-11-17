import { defineEventHandler, setResponseHeader } from 'h3'
import { unsAdminRequest } from '../../../../../utils/unsAdmin'

export default defineEventHandler((event) => {
  setResponseHeader(event, 'Cache-Control', 'private, no-store')
  return unsAdminRequest<{ token: string; enabled: boolean; broker: string }>(event, 'discovery/csrf', 5000)
})
