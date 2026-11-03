<script setup lang="ts">
const { locale, t } = useAppLocale()
type Widget = { type: string; tagId: string; title: string }
type Dashboard = { id: string; name: string; slug: string; widgets: Widget[] }
type Sample = { tagId: string; value: number | boolean | string; observedAt: string; quality: 'good' | 'stale' }
const { data, status, error } = await useFetch<{ dashboards: Dashboard[] }>('/api/dashboards', { server: false, retry: 0 })
const { data: telemetry, refresh } = await useFetch<{ values: Sample[]; staleAfterSeconds: number }>('/api/tag-values', { server: false, retry: 0 })
const selectedId = ref('')
const current = computed(() => data.value?.dashboards.find(d => d.id === selectedId.value) ?? data.value?.dashboards[0])
const values = computed(() => new Map((telemetry.value?.values ?? []).map(v => [v.tagId, v])))
const now = ref(Date.now())
let timer: ReturnType<typeof setInterval> | undefined
onMounted(() => { timer = setInterval(() => { now.value = Date.now(); void refresh() }, 5000) })
onUnmounted(() => { if (timer) clearInterval(timer) })
function quality(sample: Sample | undefined): 'empty' | 'stale' | 'good' {
  if (!sample) return 'empty'
  const time = Date.parse(sample.observedAt)
  return Number.isFinite(time) && time <= now.value && now.value - time <= (telemetry.value?.staleAfterSeconds ?? 30) * 1000 ? 'good' : 'stale'
}
function formatValue(value: number | string | boolean | undefined): string {
  if (value === undefined) return '—'
  if (typeof value === 'boolean') return value ? 'ON' : 'OFF'
  return typeof value === 'number' ? new Intl.NumberFormat(locale.value === 'fr' ? 'fr-FR' : 'en-GB', { maximumFractionDigits: 2 }).format(value) : value
}
</script>

<template>
  <main>
    <header class="mb-8 flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="text-sm font-semibold uppercase tracking-widest text-sky-700">SCADA Studio / L3</p>
        <h1 class="mt-2 text-3xl font-bold">{{ t('dashboard') }}</h1>
        <p class="mt-2 text-slate-600">{{ t('dashboardReadOnly') }}</p>
      </div>
      <select v-if="(data?.dashboards.length ?? 0) > 1" v-model="selectedId" :aria-label="t('dashboard')" class="rounded-lg border border-slate-300 bg-white px-3 py-2">
        <option v-for="item in data?.dashboards" :key="item.id" :value="item.id">{{ item.name }}</option>
      </select>
    </header>
    <p v-if="status === 'pending' || status === 'idle'" role="status">{{ t('checking') }}</p>
    <p v-else-if="error" role="alert">{{ t('dashboardError') }}</p>
    <p v-else-if="!current" class="rounded-xl border border-dashed border-slate-300 p-8 text-slate-600">{{ t('dashboardEmpty') }}</p>
    <section v-else :aria-label="current.name">
      <h2 class="mb-5 text-xl font-semibold">{{ current.name }}</h2>
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article v-for="widget in current.widgets.filter(w => w.type === 'value')" :key="widget.tagId" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <h3 class="text-sm font-medium text-slate-600">{{ widget.title }}</h3>
          <p class="mt-4 text-3xl font-bold tabular-nums">{{ formatValue(values.get(widget.tagId)?.value) }}</p>
          <p class="mt-3 flex items-center gap-2 text-sm" :class="quality(values.get(widget.tagId)) === 'good' ? 'text-emerald-700' : 'text-amber-700'">
            <span aria-hidden="true" class="h-2 w-2 rounded-full" :class="quality(values.get(widget.tagId)) === 'good' ? 'bg-emerald-600' : 'bg-amber-500'" />
            {{ quality(values.get(widget.tagId)) === 'good' ? t('telemetryFresh') : quality(values.get(widget.tagId)) === 'stale' ? t('telemetryStale') : t('dashboardNoValue') }}
          </p>
          <p v-if="values.get(widget.tagId)?.observedAt" class="mt-2 text-xs text-slate-500">{{ new Intl.DateTimeFormat(locale === 'fr' ? 'fr-FR' : 'en-GB', { dateStyle: 'short', timeStyle: 'medium' }).format(new Date(values.get(widget.tagId)!.observedAt)) }}</p>
        </article>
      </div>
    </section>
  </main>
</template>
