<script setup lang="ts">
type Health = { status: 'ok'; service: string; timestamp: string }
const { t } = useAppLocale()
const { data, status, refresh } = await useFetch<Health>('/api/health', { server: false, retry: 0 })
const isHealthy = computed(() => status.value === 'success' && data.value?.status === 'ok')
</script>

<template>
  <main>
    <header class="mb-8">
      <p class="text-sm font-semibold uppercase tracking-widest text-sky-700">{{ t('workspace') }}</p>
      <h1 class="mt-2 text-4xl font-bold tracking-tight">{{ t('overview') }}</h1>
      <p class="mt-3 text-slate-600">{{ t('introduction') }}</p>
    </header>
    <section aria-labelledby="api-health-heading" class="max-w-xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <h2 id="api-health-heading" class="text-lg font-semibold">{{ t('backend') }}</h2>
      <p class="mt-3 text-sm" role="status" aria-live="polite">
        <template v-if="status === 'pending' || status === 'idle'">{{ t('checking') }}</template>
        <template v-else-if="isHealthy"><span class="font-semibold text-emerald-700">{{ t('healthy') }}</span> ({{ data?.service }})</template>
        <template v-else><span class="font-semibold text-rose-700">{{ t('unavailable') }}</span></template>
      </p>
      <button type="button" class="mt-5 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600 disabled:opacity-50" :disabled="status === 'pending'" @click="refresh()">{{ t('refresh') }}</button>
    </section>
  </main>
</template>
