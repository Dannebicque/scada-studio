import { discoveryErrorKind } from '~/utils/unsDiscovery'
import type { DiscoveryError, DiscoveryResult } from '~/utils/unsDiscovery'

type DiscoveryStatus = { token: string; enabled: boolean; broker: string }
export type ProvisionResult = { createdSources: number; createdTags: number; skipped: number; messages: string[] }

export function useUnsDiscovery(workspaceId: Ref<string>) {
  const status = ref<DiscoveryStatus | null>(null)
  const statusError = ref<DiscoveryError | ''>('')
  const phase = ref<'idle' | 'listening' | 'provisioning'>('idle')
  const error = ref<DiscoveryError | ''>('')
  const result = ref<DiscoveryResult | null>(null)
  const provisioned = ref<ProvisionResult | null>(null)

  const base = () => `/api/workspaces/${encodeURIComponent(workspaceId.value)}/uns`
  const kind = (cause: unknown) => discoveryErrorKind((cause as { statusCode?: number })?.statusCode)

  function reset() {
    status.value = null
    statusError.value = ''
    error.value = ''
    result.value = null
    provisioned.value = null
    phase.value = 'idle'
  }

  async function loadStatus() {
    const id = workspaceId.value
    reset()
    if (!id) return
    try {
      const value = await $fetch<DiscoveryStatus>(base() + '/discovery/csrf', { retry: 0 })
      if (id === workspaceId.value) status.value = value
    } catch (cause) {
      if (id === workspaceId.value) statusError.value = kind(cause)
    }
  }

  async function freshToken(): Promise<string> {
    return (await $fetch<DiscoveryStatus>(base() + '/discovery/csrf', { retry: 0 })).token
  }

  async function discover(prefix: string, seconds: number): Promise<boolean> {
    if (phase.value !== 'idle' || !workspaceId.value) return false
    const id = workspaceId.value
    phase.value = 'listening'
    error.value = ''
    result.value = null
    provisioned.value = null
    try {
      const token = await freshToken()
      const value = await $fetch<DiscoveryResult>(base() + '/discover', {
        method: 'POST', headers: { 'X-CSRF-Token': token }, body: { prefix, seconds }, retry: 0, timeout: 50_000,
      })
      if (id !== workspaceId.value) return false
      result.value = value
      return true
    } catch (cause) {
      if (id === workspaceId.value) error.value = kind(cause)
      return false
    } finally {
      phase.value = 'idle'
    }
  }

  async function provision(topics: string[], enable: boolean): Promise<boolean> {
    if (phase.value !== 'idle' || !result.value || topics.length === 0) return false
    const id = workspaceId.value
    phase.value = 'provisioning'
    error.value = ''
    try {
      const token = await freshToken()
      const value = await $fetch<ProvisionResult>(base() + '/provision', {
        method: 'POST', headers: { 'X-CSRF-Token': token }, body: { model: result.value.model, topics, enable }, retry: 0,
      })
      if (id !== workspaceId.value) return false
      provisioned.value = value
      return true
    } catch (cause) {
      if (id === workspaceId.value) error.value = kind(cause)
      return false
    } finally {
      phase.value = 'idle'
    }
  }

  return { status, statusError, phase, error, result, provisioned, loadStatus, discover, provision }
}
