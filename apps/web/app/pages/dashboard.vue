<script setup lang="ts">
const { locale, t } = useAppLocale()
type Widget = { type: string; tagId: string; title: string }
type Dashboard = { id: string; name: string; slug: string; widgets: Widget[] }
type Sample = { tagId: string; value: number | boolean | string; observedAt: string; quality: 'good' | 'stale' }
const { data, status, error } = await useFetch<{ dashboards: Dashboard[] }>('/api/dashboards', { server: false, retry: 0 })
const { data: telemetry, refresh } = await useFetch<{ values: Sample[]; staleAfterSeconds: number }>('/api/tag-values', { server: false, retry: 0 })
const selectedId = ref('')
const editing = ref(false)
const draftWidgets = ref<Widget[]>([])
const chosenTagId = ref('')
const { data: catalog } = await useFetch<{ tags: { id: string; name: string }[] }>('/api/catalog', { server: false, retry: 0 })
const availableTags = computed(() => (catalog.value?.tags ?? []).filter(tag => !draftWidgets.value.some(widget => widget.tagId === tag.id)))
function beginPreview() { draftWidgets.value = current.value?.widgets.map(widget => ({ ...widget })) ?? []; editing.value = true }
function cancelPreview() { editing.value = false; draftWidgets.value = []; chosenTagId.value = '' }
function addWidget() {
  const tag = availableTags.value.find(item => item.id === chosenTagId.value)
  if (!tag || draftWidgets.value.length >= 40) return
  draftWidgets.value.push({ type: 'value', tagId: tag.id, title: tag.name })
  chosenTagId.value = ''
}
const visibleWidgets = computed(() => editing.value ? draftWidgets.value : (current.value?.widgets ?? []))
watch(selectedId, cancelPreview)
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
      <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-semibold">{{ current.name }}</h2>
        <button v-if="!editing" type="button" class="rounded-lg border border-sky-300 px-4 py-2 text-sm font-semibold text-sky-800 hover:bg-sky-50 focus-visible:outline-2 focus-visible:outline-offset-2" @click="beginPreview">{{ t('editPreview') }}</button>
        <button v-else type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2" @click="cancelPreview">{{ t('resetPreview') }}</button>
      </div>
      <div v-if="editing" class="mb-6 rounded-xl border border-sky-200 bg-sky-50 p-4">
        <p class="mb-3 text-sm font-medium text-sky-900">{{ t('previewWarning') }}</p>
        <div class="flex flex-wrap items-center gap-3">
          <label for="draft-tag" class="text-sm font-semibold">{{ t('chooseTag') }}</label>
          <select id="draft-tag" v-model="chosenTagId" class="min-w-44 rounded-lg border border-slate-300 bg-white px-3 py-2">
            <option value="">—</option>
            <option v-for="tag in availableTags" :key="tag.id" :value="tag.id">{{ tag.name }}</option>
          </select>
          <button type="button" :disabled="!chosenTagId || draftWidgets.length >= 40" class="rounded-lg bg-sky-800 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" @click="addWidget">{{ t('addWidget') }}</button>
        </div>
      </div>
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article v-for="widget in visibleWidgets.filter(w => w.type === 'value')" :key="widget.tagId" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div class="flex items-start justify-between gap-2">
            <h3 class="text-sm font-medium text-slate-600">{{ widget.title }}</h3>
            <button v-if="editing" type="button" class="rounded border border-slate-200 px-2 py-1 text-xs text-slate-700 hover:bg-slate-100" @click="draftWidgets = draftWidgets.filter(item => item.tagId !== widget.tagId)">{{ t('removeWidget') }}</button>
          </div>
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
