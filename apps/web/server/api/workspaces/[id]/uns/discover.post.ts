import { defineEventHandler, setResponseHeader } from 'h3'
import { unsAdminRequest } from '../../../../utils/unsAdmin'

// L'écoute dure jusqu'à 30 s côté API : marge pour la connexion et le calcul.
export default defineEventHandler((event) => {
  setResponseHeader(event, 'Cache-Control', 'private, no-store')
  return unsAdminRequest<object>(event, 'discover', 45_000)
})
