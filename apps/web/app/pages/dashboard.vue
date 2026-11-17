<script setup lang="ts">
const { t } = useAppLocale()
const route = useRoute()
const { formatDateTime } = useLocalizedDateTime()
type Widget = { type: string; tagId: string; title: string }
type Entry = { key: string; origin: 'shared' | 'personal'; name: string; widgets: Widget[] }
import type { MeasureState, Quality } from '~/utils/measureState'
type Sample = { tagId: string; value: number | string | boolean; observedAt: string; quality: Quality | 'stale' }
type CatalogTag = { id: string; name: string; unit: string | null }

const { data, status, error } = await useFetch<{ dashboards: { id: string; name: string; widgets: Widget[] }[] }>('/api/dashboards', { server: false, retry: 0 })
// Tableaux de l'utilisateur (créés par exemple via l'import UNS) : un 401 (non connecté) est simplement ignoré.
const { data: personal } = usePersonalDashboards()
const { data: telemetry, refresh } = await useFetch<{ values: Sample[]; staleAfterSeconds: number }>('/api/tag-values', { server: false, retry: 0 })
const { data: catalog } = await useFetch<{ tags: CatalogTag[] }>('/api/catalog', { server: false, retry: 0 })

const shared = computed<Entry[]>(() => (data.value?.dashboards ?? []).map(item => ({ key: 'shared:' + item.id, origin: 'shared', name: item.name, widgets: item.widgets })))
const own = computed<Entry[]>(() => (personal.value?.dashboards ?? []).map(item => ({ key: 'personal:' + item.id, origin: 'personal', name: item.name, widgets: item.widgets })))
const entries = computed(() => [...shared.value, ...own.value])
const selectedKey = ref('')
// Sélection par défaut = premier tableau ; ?d=personal:<id> (lien « Afficher » de Mon espace) est appliqué dès que
// ce tableau est chargé, même s'il arrive après les tableaux partagés.
let requested = typeof route.query.d === 'string' ? route.query.d : ''
watch(entries, (list) => {
  const wanted = requested ? list.find(entry => entry.key === requested) : undefined
  if (wanted) { selectedKey.value = wanted.key; requested = ''; return }
  if (!list.some(entry => entry.key === selectedKey.value)) selectedKey.value = list[0]?.key ?? ''
}, { immediate: true })
const current = computed(() => entries.value.find(entry => entry.key === selectedKey.value) ?? entries.value[0])

const editing = ref(false)
const draftWidgets = ref<Widget[]>([])
const chosenTagId = ref('')
const availableTags = computed(() => (catalog.value?.tags ?? []).filter(tag => !draftWidgets.value.some(widget => widget.tagId === tag.id)))
const unitByTag = computed(() => new Map((catalog.value?.tags ?? []).map(tag => [tag.id, tag.unit])))
function beginPreview() { draftWidgets.value = current.value?.widgets.map(widget => ({ ...widget })) ?? []; editing.value = true }
function cancelPreview() { editing.value = false; draftWidgets.value = []; chosenTagId.value = '' }
function moveWidget(index: number, offset: number) {
  const target = index + offset
  if (target < 0 || target >= draftWidgets.value.length) return
  const updated = [...draftWidgets.value]
  const [widget] = updated.splice(index, 1)
  if (!widget) return
  updated.splice(target, 0, widget)
  draftWidgets.value = updated
}
function addWidget() {
  const tag = availableTags.value.find(item => item.id === chosenTagId.value)
  if (!tag || draftWidgets.value.length >= 40) return
  draftWidgets.value.push({ type: 'value', tagId: tag.id, title: tag.name })
  chosenTagId.value = ''
}
const visibleWidgets = computed(() => editing.value ? draftWidgets.value : (current.value?.widgets ?? []))
watch(selectedKey, cancelPreview)

const values = computed(() => new Map((telemetry.value?.values ?? []).map(v => [v.tagId, v])))
const now = ref(Date.now())
// L'horloge est recalée à chaque nouvelle mesure reçue : sinon une valeur arrivée après le dernier tick semble « du futur ».
watch(telemetry, () => { now.value = Date.now() })
let timer: ReturnType<typeof setInterval> | undefined
onMounted(() => { timer = setInterval(() => { now.value = Date.now(); void refresh() }, 5000) })
onUnmounted(() => { if (timer) clearInterval(timer) })
function stateOf(sample: Sample | undefined): MeasureState {
  return measureState(sample, now.value, telemetry.value?.staleAfterSeconds ?? 30)
}
</script>

<template>
  <div>
    <header class="mb-8 flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="text-sm font-semibold uppercase tracking-widest text-sky-700">SCADA Studio / L3</p>
        <h1 class="mt-2 text-3xl font-bold">{{ t('dashboard') }}</h1>
        <p class="mt-2 text-slate-700">{{ t('dashboardReadOnly') }}</p>
      </div>
      <div v-if="entries.length > 1">
        <label for="dashboard-select" class="sr-only">{{ t('dashboard') }}</label>
        <select id="dashboard-select" v-model="selectedKey" class="rounded-lg border bg-white px-3 py-2">
          <optgroup v-if="shared.length" :label="t('dashboardsShared')">
            <option v-for="item in shared" :key="item.key" :value="item.key">{{ item.name }}</option>
          </optgroup>
          <optgroup v-if="own.length" :label="t('dashboardsPersonal')">
            <option v-for="item in own" :key="item.key" :value="item.key">{{ item.name }}</option>
          </optgroup>
        </select>
      </div>
    </header>

    <p v-if="status === 'pending' || status === 'idle'" role="status">{{ t('checking') }}</p>
    <p v-else-if="error && !entries.length" role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-900">{{ t('dashboardError') }}</p>
    <p v-else-if="!current" class="rounded-xl border border-dashed border-slate-400 p-8 text-slate-700">{{ t('dashboardEmpty') }}</p>
    <section v-else aria-labelledby="dashboard-title">
      <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h2 id="dashboard-title" class="text-xl font-semibold">{{ current.name }}</h2>
        <button v-if="!editing" type="button" class="rounded-lg border border-sky-700 px-4 py-2 text-sm font-semibold text-sky-900 hover:bg-sky-50" @click="beginPreview">{{ t('editPreview') }}</button>
        <button v-else type="button" class="rounded-lg border border-slate-400 px-4 py-2 text-sm font-semibold hover:bg-slate-50" @click="cancelPreview">{{ t('resetPreview') }}</button>
      </div>
      <div v-if="editing" class="mb-6 rounded-xl border border-sky-200 bg-sky-50 p-4">
        <p class="mb-3 text-sm font-medium text-sky-900">{{ t('previewWarning') }}</p>
        <div class="flex flex-wrap items-center gap-3">
          <label for="draft-tag" class="text-sm font-semibold">{{ t('chooseTag') }}</label>
          <select id="draft-tag" v-model="chosenTagId" class="min-w-44 rounded-lg border bg-white px-3 py-2">
            <option value="">—</option>
            <option v-for="tag in availableTags" :key="tag.id" :value="tag.id">{{ tag.name }}</option>
          </select>
          <button type="button" :disabled="!chosenTagId || draftWidgets.length >= 40" class="rounded-lg bg-sky-800 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" @click="addWidget">{{ t('addWidget') }}</button>
        </div>
      </div>
      <p v-if="!visibleWidgets.length" class="rounded-xl border border-dashed border-slate-400 p-6 text-slate-700">{{ t('dashboardEmpty') }}</p>
      <ul v-else :aria-label="t('widgetList')" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <li v-for="(widget, index) in visibleWidgets" :key="widget.tagId" :class="widget.type === 'chart' ? 'sm:col-span-2' : ''">
          <DashboardWidget
            :type="widget.type"
            :tag-id="widget.tagId"
            :title="widget.title"
            :value="values.get(widget.tagId)?.value"
            :unit="unitByTag.get(widget.tagId) ?? null"
            :state="stateOf(values.get(widget.tagId))"
            :observed-at="values.get(widget.tagId)?.observedAt ? formatDateTime(values.get(widget.tagId)!.observedAt) : ''"
          >
            <template v-if="editing" #actions>
              <div class="flex flex-wrap gap-1">
                <button type="button" :disabled="index === 0" :aria-label="t('moveEarlier') + ': ' + widget.title" class="rounded border border-slate-300 px-2 py-1 text-xs disabled:opacity-30" @click="moveWidget(index, -1)">←</button>
                <button type="button" :disabled="index === draftWidgets.length - 1" :aria-label="t('moveLater') + ': ' + widget.title" class="rounded border border-slate-300 px-2 py-1 text-xs disabled:opacity-30" @click="moveWidget(index, 1)">→</button>
                <button type="button" :aria-label="t('removeWidget') + ': ' + widget.title" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-800 hover:bg-slate-100" @click="draftWidgets = draftWidgets.filter(item => item.tagId !== widget.tagId)">{{ t('removeWidget') }}</button>
              </div>
            </template>
          </DashboardWidget>
        </li>
      </ul>
    </section>
  </div>
</template>
