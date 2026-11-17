<script setup lang="ts">
// Rendu d'un indicateur selon son type. Le contenu n'est jamais porté par la couleur seule.
import type { MeasureState } from '~/utils/measureState'
const props = defineProps<{
  type: string
  tagId?: string
  title: string
  value: number | string | boolean | undefined
  unit: string | null
  state: MeasureState
  observedAt: string
}>()
const { locale, t } = useAppLocale()
const numberFormat = computed(() => new Intl.NumberFormat(locale.value === 'fr' ? 'fr-FR' : 'en-GB', { maximumFractionDigits: 2 }))
const text = computed(() => {
  const v = props.value
  if (v === undefined) return '—'
  if (typeof v === 'boolean') return v ? 'ON' : 'OFF'
  return typeof v === 'number' ? numberFormat.value.format(v) : v
})
const isPercentGauge = computed(() => props.type === 'gauge' && props.unit === '%' && typeof props.value === 'number')
const percent = computed(() => Math.min(100, Math.max(0, typeof props.value === 'number' ? props.value : 0)))
const stateLabel = computed(() => ({ good: t('readingFresh'), stale: t('readingStale'), uncertain: t('readingUncertain'), bad: t('readingBad'), empty: t('dashboardNoValue') })[props.state])
// Mesure invalide : la valeur reste lisible mais barrée et atténuée ; l'état est dit en texte, jamais par la couleur seule.
const unreliable = computed(() => props.state === 'bad')
const stateIcon = computed(() => ({ good: '●', stale: '○', uncertain: '◐', bad: '✕', empty: '○' })[props.state])
const stateClass = computed(() => props.state === 'good' ? 'text-emerald-800' : props.state === 'bad' ? 'text-red-800' : 'text-amber-800')
const dotClass = computed(() => props.state === 'good' ? 'bg-emerald-600' : props.state === 'bad' ? 'bg-red-700' : 'bg-amber-500')
</script>

<template>
  <article class="h-full rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex items-start justify-between gap-2">
      <h3 class="text-sm font-medium text-slate-700">{{ title }}</h3>
      <slot name="actions" />
    </div>

    <p v-if="type === 'status' && typeof value === 'boolean'" class="mt-4">
      <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-lg font-bold" :class="value ? 'bg-emerald-50 text-emerald-900' : 'bg-slate-100 text-slate-800'">
        <span aria-hidden="true">{{ value ? '●' : '○' }}</span>{{ value ? t('stateOn') : t('stateOff') }}
      </span>
    </p>
    <p v-else-if="type === 'status' && typeof value === 'string'" class="mt-4">
      <span class="inline-flex rounded-full bg-sky-50 px-3 py-1 text-lg font-bold text-sky-900">{{ value }}</span>
    </p>
    <template v-else>
      <p class="mt-4 text-3xl font-bold tabular-nums">{{ text }}<span v-if="unit && value !== undefined && typeof value === 'number'" class="ml-1 text-lg font-medium text-slate-600">{{ unit }}</span></p>
      <meter v-if="isPercentGauge" class="mt-3 h-3 w-full" min="0" max="100" :value="percent" :aria-label="title">{{ text }} %</meter>
    </template>

    <p class="mt-3 flex items-center gap-2 text-sm" :class="stateClass">
      <span aria-hidden="true" class="text-xs leading-none" :class="state === 'good' ? '' : 'font-bold'">{{ stateIcon }}</span>
      {{ stateLabel }}
    </p>
    <p v-if="observedAt" class="mt-2 text-xs text-slate-600">{{ observedAt }}</p>
    <TagHistoryChart v-if="type === 'chart' && tagId" :tag-id="tagId" :title="title" :unit="unit" />
    <p v-else-if="type === 'chart'" class="mt-2 text-xs text-slate-600">{{ t('chartPending') }}</p>
  </article>
</template>
