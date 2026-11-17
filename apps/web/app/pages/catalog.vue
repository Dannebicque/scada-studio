<script setup lang="ts">
const { t } = useAppLocale()
const { formatDateTime } = useLocalizedDateTime()
type Catalog = {
  workspaces: { id: string; name: string; slug: string }[]
  sources: { id: string; name: string; connectorType: string; enabled: boolean }[]
  tags: { id: string; name: string; key: string; valueType: string; readOnly: boolean; unit: string | null }[]
}
const { data, status, error, refresh } = await useFetch<Catalog>('/api/catalog', { server: false, retry: 0 })
import type { Quality } from '~/utils/measureState'
const { data: telemetry, status: telemetryStatus, error: telemetryError, refresh: refreshTelemetry } = await useFetch<{ values: { tagId: string; value: number | string | boolean; observedAt: string; quality: Quality | 'stale' }[]; staleAfterSeconds: number }>('/api/tag-values', { server: false, retry: 0 })
const now = ref(Date.now())
let clock: ReturnType<typeof setInterval> | undefined
let poll: ReturnType<typeof setInterval> | undefined
onMounted(() => { clock = setInterval(() => { now.value = Date.now() }, 1000); poll = setInterval(() => { void refreshTelemetry() }, 5000) })
onUnmounted(() => { if (clock) clearInterval(clock); if (poll) clearInterval(poll) })
const valueByTag = computed(() => new Map((telemetry.value?.values ?? []).map(item => [item.tagId, item])))
function qualityFor(tagId: string): string {
  const sample = valueByTag.value.get(tagId)
  if (!sample) return t('noReading')
  if (!Number.isFinite(Date.parse(sample.observedAt))) return t('invalidTimestamp')
  switch (measureState(sample, now.value, telemetry.value?.staleAfterSeconds ?? 30)) {
    case 'stale': return t('stale')
    case 'uncertain': return t('uncertain')
    case 'bad': return t('bad')
    default: return t('valid')
  }
}
function refreshAll() { refresh(); refreshTelemetry() }
</script>

<template>
  <div>
    <h1 class="text-3xl font-bold">{{ t('catalogTitle') }}</h1>
    <p class="mt-2 text-slate-700">{{ t('catalogIntro') }}</p>
    <button type="button" class="mt-4 rounded-lg border border-slate-400 bg-white px-4 py-2 text-sm font-semibold hover:bg-slate-50 disabled:opacity-50" :disabled="status === 'pending'" @click="refreshAll()">{{ t('refresh') }}</button>
    <p v-if="status === 'pending' || status === 'idle'" role="status" class="mt-4">{{ t('loading') }}</p>
    <p v-else-if="error" role="alert" class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-red-900">{{ t('catalogUnavailable') }}</p>
    <template v-else-if="data">
      <section aria-labelledby="workspaces-heading" class="mt-8">
        <h2 id="workspaces-heading" class="text-xl font-semibold">{{ t('workspaces') }} ({{ data.workspaces.length }})</h2>
        <ul class="mt-2 list-disc space-y-1 pl-6"><li v-for="item in data.workspaces" :key="item.id">{{ item.name }} — <span class="font-mono text-sm">{{ item.slug }}</span></li></ul>
      </section>
      <section aria-labelledby="sources-heading" class="mt-8">
        <h2 id="sources-heading" class="text-xl font-semibold">{{ t('sources') }} ({{ data.sources.length }})</h2>
        <ul class="mt-2 list-disc space-y-1 pl-6"><li v-for="item in data.sources" :key="item.id">{{ item.name }} — {{ item.connectorType }} ({{ item.enabled ? t('active') : t('inactive') }})</li></ul>
      </section>
      <p v-if="telemetryError" role="alert" class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-950">{{ t('telemetryUnavailable') }}</p>
      <p v-else-if="telemetryStatus === 'pending'" role="status" class="mt-4">{{ t('loadingTelemetry') }}</p>
      <section aria-labelledby="tags-heading" class="mt-8">
        <h2 id="tags-heading" class="text-xl font-semibold">{{ t('tags') }} ({{ data.tags.length }})</h2>
        <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-white">
          <table class="w-full min-w-[48rem] text-left text-sm">
            <caption class="sr-only">{{ t('catalogCaption') }}</caption>
            <thead class="bg-slate-100 text-slate-800"><tr><th scope="col" class="px-4 py-3">{{ t('name') }}</th><th scope="col" class="px-4 py-3">{{ t('key') }}</th><th scope="col" class="px-4 py-3">{{ t('type') }}</th><th scope="col" class="px-4 py-3">{{ t('unit') }}</th><th scope="col" class="px-4 py-3">{{ t('access') }}</th><th scope="col" class="px-4 py-3">{{ t('lastValue') }}</th><th scope="col" class="px-4 py-3">{{ t('timestamp') }}</th><th scope="col" class="px-4 py-3">{{ t('quality') }}</th></tr></thead>
            <tbody class="divide-y divide-slate-100"><tr v-for="item in data.tags" :key="item.id"><th scope="row" class="px-4 py-3 font-medium">{{ item.name }}</th><td class="px-4 py-3 font-mono text-xs">{{ item.key }}</td><td class="px-4 py-3">{{ item.valueType }}</td><td class="px-4 py-3">{{ item.unit ?? '—' }}</td><td class="px-4 py-3">{{ item.readOnly ? t('readOnly') : t('declaredWrite') }}</td><td class="px-4 py-3 tabular-nums">{{ valueByTag.get(item.id)?.value ?? '—' }}</td><td class="px-4 py-3">{{ valueByTag.get(item.id)?.observedAt ? formatDateTime(valueByTag.get(item.id)!.observedAt) : '—' }}</td><td class="px-4 py-3">{{ qualityFor(item.id) }}</td></tr></tbody>
          </table>
        </div>
      </section>
    </template>
  </div>
</template>
