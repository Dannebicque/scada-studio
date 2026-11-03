<script setup lang="ts">
const { t } = useAppLocale()
const { formatTime } = useLocalizedDateTime()
type Sample = { observedAt: string; quality: 'good' | 'stale' }
type Snapshot = { values: Sample[]; staleAfterSeconds: number }
const { data: health, status: healthStatus, error: healthError, refresh: refreshHealth } = await useFetch<{ status: string }>('/api/health', { server: false, retry: 0 })
const { data: telemetry, error: telemetryError, refresh: refreshTelemetry } = await useFetch<Snapshot>('/api/tag-values', { server: false, retry: 0 })
const now = ref(Date.now())
const lastChecked = ref<number | null>(null)
let timer: ReturnType<typeof setInterval> | undefined
let clock: ReturnType<typeof setInterval> | undefined
const apiState = computed(() => healthStatus.value === 'pending' || healthStatus.value === 'idle' ? 'checking' : !healthError.value && health.value?.status === 'ok' ? 'online' : 'offline')
const recentCount = computed(() => (telemetry.value?.values ?? []).filter(item => {
  const timestamp = Date.parse(item.observedAt)
  return Number.isFinite(timestamp) && timestamp <= now.value && now.value - timestamp <= (telemetry.value?.staleAfterSeconds ?? 30) * 1000
}).length)
const staleCount = computed(() => Math.max(0, (telemetry.value?.values.length ?? 0) - recentCount.value))
const telemetryLabel = computed(() => {
  if (telemetryError.value) return t('telemetryUnknown')
  if (!telemetry.value?.values.length) return t('telemetryEmpty')
  return recentCount.value > 0 ? t('telemetryFresh') : t('telemetryStale')
})
async function update() {
  await Promise.allSettled([refreshHealth(), refreshTelemetry()])
  lastChecked.value = Date.now()
}
onMounted(() => {
  lastChecked.value = Date.now()
  clock = setInterval(() => { now.value = Date.now() }, 1000)
  timer = setInterval(() => { void update() }, 10000)
})
onUnmounted(() => { if (timer) clearInterval(timer); if (clock) clearInterval(clock) })
</script>

<template>
  <footer class="border-t border-slate-200 bg-white text-sm text-slate-600" :aria-label="t('systemStatus')">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-6 gap-y-2 px-5 py-3">
      <span class="font-semibold text-slate-800">{{ t('systemStatus') }}</span>
      <span class="inline-flex items-center gap-2">
        <span aria-hidden="true" class="h-2.5 w-2.5 rounded-full" :class="apiState === 'online' ? 'bg-emerald-600' : apiState === 'offline' ? 'bg-rose-600' : 'bg-slate-400'" />
        {{ apiState === 'online' ? t('apiOnline') : apiState === 'offline' ? t('apiOffline') : t('apiChecking') }}
      </span>
      <span class="inline-flex items-center gap-2">
        <span aria-hidden="true" class="h-2.5 w-2.5 rounded-full" :class="telemetryError ? 'bg-rose-600' : recentCount ? 'bg-emerald-600' : 'bg-amber-500'" />
        {{ telemetryLabel }}
        <span v-if="telemetry?.values.length">({{ recentCount }} / {{ telemetry.values.length }})</span>
      </span>
      <span v-if="lastChecked" class="text-xs text-slate-500">{{ t('lastChecked') }} : {{ formatTime(lastChecked) }}</span>
      <span class="w-full text-xs text-slate-500">{{ t('systemStatusNote') }}</span>
    </div>
  </footer>
</template>
