<script setup lang="ts">
import { buildChart, sampleRows, summarize } from '~/utils/historyChart'
import type { TagHistoryResponse } from '~~/server/api/tags/[id]/history.get'

// Courbe d'historique d'un tag : moyenne par intervalle et bande min–max. Le graphique est décoratif
// pour les lecteurs d'écran (aria-hidden) ; le résumé en texte et le tableau de données portent l'information.
const props = defineProps<{ tagId: string; title: string; unit: string | null }>()
const { locale } = useAppLocale()
const fr = computed(() => locale.value === 'fr')

const ranges = [
  { value: '15m', fr: '15 min', en: '15 min' },
  { value: '1h', fr: '1 h', en: '1 h' },
  { value: '6h', fr: '6 h', en: '6 h' },
  { value: '24h', fr: '24 h', en: '24 h' },
  { value: '7d', fr: '7 jours', en: '7 days' },
] as const
const range = ref<(typeof ranges)[number]['value']>('1h')
const data = ref<TagHistoryResponse | null>(null)
const state = ref<'loading' | 'ready' | 'error' | 'forbidden'>('loading')
const uid = useId()

let request = 0
async function load() {
  const current = ++request
  try {
    const result = await $fetch<TagHistoryResponse>(`/api/tags/${encodeURIComponent(props.tagId)}/history`, { query: { range: range.value, points: 120 }, retry: 0 })
    if (current !== request) return
    data.value = result
    state.value = 'ready'
  } catch (cause) {
    if (current !== request) return
    const status = (cause as { statusCode?: number })?.statusCode
    state.value = status === 401 || status === 403 || status === 404 ? 'forbidden' : 'error'
  }
}
let timer: ReturnType<typeof setInterval> | undefined
onMounted(() => { void load(); timer = setInterval(load, 30_000) })
onUnmounted(() => { if (timer) clearInterval(timer); request += 1 })
watch(range, () => { state.value = 'loading'; void load() })
watch(() => props.tagId, () => { data.value = null; state.value = 'loading'; void load() })

const isBoolean = computed(() => data.value?.valueType === 'boolean')
const chart = computed(() => data.value?.supported ? buildChart(data.value.points, data.value.from, data.value.to, data.value.bucketSeconds, 300, 100, isBoolean.value ? [0, 1] : null) : null)
const summary = computed(() => data.value?.supported ? summarize(data.value.points) : null)
const rows = computed(() => sampleRows(data.value?.points ?? []))
const number = computed(() => new Intl.NumberFormat(fr.value ? 'fr-FR' : 'en-GB', { maximumFractionDigits: 2 }))
const time = computed(() => new Intl.DateTimeFormat(fr.value ? 'fr-FR' : 'en-GB', range.value === '7d' || range.value === '24h' ? { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' } : { hour: '2-digit', minute: '2-digit', second: range.value === '15m' ? '2-digit' : undefined }))
const fmt = (n: number) => number.value.format(n) + (props.unit && !isBoolean.value ? ' ' + props.unit : '')
const rangeLabel = computed(() => ranges.find(r => r.value === range.value)?.[fr.value ? 'fr' : 'en'] ?? '')
const summaryText = computed(() => {
  const s = summary.value
  if (!s) return ''
  if (isBoolean.value) {
    const pct = number.value.format(s.mean * 100)
    return fr.value ? `Actif ${pct} % du temps sur ${rangeLabel.value} (${s.count} mesures).` : `Active ${pct}% of the time over ${rangeLabel.value} (${s.count} samples).`
  }
  return fr.value
    ? `Sur ${rangeLabel.value} : minimum ${fmt(s.min)}, maximum ${fmt(s.max)}, moyenne ${fmt(s.mean)} (${s.count} mesures).`
    : `Over ${rangeLabel.value}: minimum ${fmt(s.min)}, maximum ${fmt(s.max)}, average ${fmt(s.mean)} (${s.count} samples).`
})
</script>

<template>
  <div class="mt-4">
    <div class="flex items-center justify-between gap-2">
      <label :for="`${uid}-range`" class="text-xs font-medium text-slate-700">{{ fr ? 'Période' : 'Period' }}<span class="sr-only"> — {{ title }}</span></label>
      <select :id="`${uid}-range`" v-model="range" class="rounded border border-slate-500 bg-white px-2 py-1 text-sm">
        <option v-for="r in ranges" :key="r.value" :value="r.value">{{ fr ? r.fr : r.en }}</option>
      </select>
    </div>

    <p v-if="state === 'loading' && !data" role="status" class="mt-3 text-sm text-slate-700">{{ fr ? 'Chargement de l’historique…' : 'Loading history…' }}</p>
    <p v-else-if="state === 'forbidden'" class="mt-3 text-sm text-slate-700">{{ fr ? 'Historique réservé aux membres de l’espace.' : 'History is reserved for workspace members.' }}</p>
    <p v-else-if="state === 'error' && !data" role="alert" class="mt-3 text-sm text-amber-900">{{ fr ? 'Historique indisponible pour le moment.' : 'History temporarily unavailable.' }}</p>
    <p v-else-if="data && !data.supported" class="mt-3 text-sm text-slate-700">{{ fr ? 'Pas d’historique pour une valeur de type texte.' : 'No history for text values.' }}</p>
    <p v-else-if="data && !chart" class="mt-3 text-sm text-slate-700">{{ fr ? `Aucune mesure enregistrée sur ${rangeLabel}.` : `No samples recorded over ${rangeLabel}.` }}</p>

    <figure v-else-if="chart && data" class="mt-3">
      <div class="flex gap-2">
        <div class="flex flex-col justify-between py-0.5 text-right text-xs tabular-nums text-slate-700" aria-hidden="true">
          <span>{{ isBoolean ? 'ON' : number.format(chart.yMax) }}</span><span>{{ isBoolean ? 'OFF' : number.format(chart.yMin) }}</span>
        </div>
        <svg :viewBox="`0 0 ${chart.width} ${chart.height}`" class="h-28 w-full rounded border border-slate-300 bg-slate-50" preserveAspectRatio="none" aria-hidden="true" focusable="false">
          <path v-if="chart.band" :d="chart.band" class="fill-sky-200/70" />
          <path :d="chart.line" fill="none" class="stroke-sky-800" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round" />
          <circle v-if="chart.last" :cx="chart.last.x" :cy="chart.last.y" r="3" class="fill-sky-900" />
        </svg>
      </div>
      <div class="mt-1 flex justify-between text-xs tabular-nums text-slate-700" aria-hidden="true">
        <span>{{ time.format(new Date(data.from)) }}</span><span>{{ time.format(new Date(data.to)) }}</span>
      </div>
      <figcaption class="mt-2 text-sm text-slate-800">{{ summaryText }}</figcaption>
      <details class="mt-2 text-sm">
        <summary class="cursor-pointer underline">{{ fr ? 'Voir les données' : 'View data' }}</summary>
        <table class="mt-2 w-full text-left text-xs tabular-nums">
          <caption class="sr-only">{{ fr ? `Historique de ${title}` : `History of ${title}` }}</caption>
          <thead>
            <tr v-if="isBoolean"><th scope="col" class="pr-2">{{ fr ? 'Heure' : 'Time' }}</th><th scope="col">{{ fr ? 'Actif (%)' : 'Active (%)' }}</th></tr>
            <tr v-else><th scope="col" class="pr-2">{{ fr ? 'Heure' : 'Time' }}</th><th scope="col" class="pr-2">{{ fr ? 'Moyenne' : 'Average' }}</th><th scope="col" class="pr-2">Min</th><th scope="col">Max</th></tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.t">
              <th scope="row" class="pr-2 font-normal">{{ time.format(new Date(row.t)) }}</th>
              <td v-if="isBoolean">{{ number.format(row.avg * 100) }} %</td>
              <template v-else><td class="pr-2">{{ fmt(row.avg) }}</td><td class="pr-2">{{ fmt(row.min) }}</td><td>{{ fmt(row.max) }}</td></template>
            </tr>
          </tbody>
        </table>
      </details>
    </figure>
  </div>
</template>
