import { defineEventHandler } from 'h3'
import { memberMutation } from '../../../../utils/memberMutation'
export default defineEventHandler(event => memberMutation(event, 'DELETE'))
