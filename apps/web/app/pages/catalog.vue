<script setup lang="ts">
const { t } = useAppLocale()
const { formatDateTime } = useLocalizedDateTime()
type Catalog = {
  workspaces: { id: string; name: string; slug: string }[]
  sources: { id: string; name: string; connectorType: string; enabled: boolean }[]
  tags: { id: string; name: string; key: string; valueType: string; readOnly: boolean; unit: string | null }[]
}
const { data, status, error, refresh } = await useFetch<Catalog>('/api/catalog', { server: false, retry: 0 })
const { data: telemetry, status: telemetryStatus, error: telemetryError, refresh: refreshTelemetry } = await useFetch<{ values: { tagId: string; value: number | string | boolean; observedAt: string; quality: 'good' | 'stale' }[]; staleAfterSeconds: number }>('/api/tag-values', { server: false, retry: 0 })
const now = ref(Date.now())
let clock: ReturnType<typeof setInterval> | undefined
let poll: ReturnType<typeof setInterval> | undefined
onMounted(() => { clock = setInterval(() => { now.value = Date.now() }, 1000); poll = setInterval(() => { void refreshTelemetry() }, 5000) })
onUnmounted(() => { if (clock) clearInterval(clock); if (poll) clearInterval(poll) })
const valueByTag = computed(() => new Map((telemetry.value?.values ?? []).map(item => [item.tagId, item])))
function qualityFor(tagId: string): string {
  const sample = valueByTag.value.get(tagId)
  if (!sample) return t('noReading')
  const timestamp = Date.parse(sample.observedAt)
  if (!Number.isFinite(timestamp)) return t('invalidTimestamp')
  return now.value - timestamp > (telemetry.value?.staleAfterSeconds ?? 30) * 1000 ? t('stale') : t('valid')
}
function refreshAll() { refresh(); refreshTelemetry() }
</script>

<template>
  <main class="mx-auto max-w-6xl">
    <NuxtLink to="/" class="underline">Retour à l'accueil</NuxtLink>
    <h1 class="mt-5 text-3xl font-bold">{{ t('catalogTitle') }}</h1>
    <p class="mt-2">{{ t('catalogIntro') }}</p>
    <button type="button" class="mt-4 rounded border px-3 py-2" :disabled="status === 'pending'" @click="refreshAll()">{{ t('refresh') }}</button>
    <p v-if="status === 'pending' || status === 'idle'" role="status">{{ t('loading') }}</p>
    <p v-else-if="error" role="alert">{{ t('catalogUnavailable') }}</p>
    <template v-else-if="data">
      <section aria-labelledby="workspaces-heading" class="mt-8">
        <h2 id="workspaces-heading" class="text-xl font-semibold">{{ t('workspaces') }} ({{ data.workspaces.length }})</h2>
        <ul><li v-for="item in data.workspaces" :key="item.id">{{ item.name }} — {{ item.slug }}</li></ul>
      </section>
      <section aria-labelledby="sources-heading" class="mt-8">
        <h2 id="sources-heading" class="text-xl font-semibold">{{ t('sources') }} ({{ data.sources.length }})</h2>
        <ul><li v-for="item in data.sources" :key="item.id">{{ item.name }} — {{ item.connectorType }} ({{ item.enabled ? t('active') : t('inactive') }})</li></ul>
      </section>
      <p v-if="telemetryError" role="alert" class="mt-4">{{ t('telemetryUnavailable') }}</p>
      <p v-else-if="telemetryStatus === 'pending'" role="status" class="mt-4">{{ t('loadingTelemetry') }}</p>
      <section aria-labelledby="tags-heading" class="mt-8">
        <h2 id="tags-heading" class="text-xl font-semibold">{{ t('tags') }} ({{ data.tags.length }})</h2>
        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead><tr><th scope="col">{{ t('name') }}</th><th scope="col">{{ t('key') }}</th><th scope="col">{{ t('type') }}</th><th scope="col">{{ t('unit') }}</th><th scope="col">{{ t('access') }}</th><th scope="col">{{ t('lastValue') }}</th><th scope="col">{{ t('timestamp') }}</th><th scope="col">{{ t('quality') }}</th></tr></thead>
            <tbody><tr v-for="item in data.tags" :key="item.id"><td>{{ item.name }}</td><td>{{ item.key }}</td><td>{{ item.valueType }}</td><td>{{ item.unit ?? '—' }}</td><td>{{ item.readOnly ? t('readOnly') : t('declaredWrite') }}</td><td>{{ valueByTag.get(item.id)?.value ?? '—' }}</td><td>{{ valueByTag.get(item.id)?.observedAt ? formatDateTime(valueByTag.get(item.id)!.observedAt) : '—' }}</td><td>{{ qualityFor(item.id) }}</td></tr></tbody>
          </table>
        </div>
      </section>
    </template>
  </main>
</template>
