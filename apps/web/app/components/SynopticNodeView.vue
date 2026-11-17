<script setup lang="ts">
// Un nœud du synoptique : titre, mesures de l'équipement, puis sous-nœuds (récursif).
import type { SynopticNode } from '~/utils/synopticModel'
import { measureState, type MeasureSample } from '~/utils/measureState'

export type SynopticActions = {
  renameNode: (id: string, label: string) => void
  toggleNode: (id: string) => void
  moveNode: (id: string, delta: -1 | 1) => void
  renameTag: (tagId: string, label: string) => void
  toggleTag: (tagId: string) => void
  moveTag: (nodeId: string, tagId: string, delta: -1 | 1) => void
}
type Sample = MeasureSample & { value: number | string | boolean }

const props = defineProps<{
  node: SynopticNode
  depth: number
  index: number
  count: number
  editing: boolean
  values: Map<string, Sample>
  nowMs: number
  staleAfterSeconds: number
}>()
const { locale } = useAppLocale()
const { formatDateTime } = useLocalizedDateTime()
const actions = inject<SynopticActions>('synopticActions')!
const fr = computed(() => locale.value === 'fr')
const level = computed(() => Math.min(2 + props.depth, 6))
const headingId = computed(() => 'syn-' + props.node.id.replace(/[^a-zA-Z0-9_-]/g, '_'))
const stateOf = (tagId: string) => measureState(props.values.get(tagId), props.nowMs, props.staleAfterSeconds)
</script>

<template>
  <section :aria-labelledby="headingId" class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4" :class="node.hidden ? 'opacity-70' : ''">
    <div class="mb-3 flex flex-wrap items-center gap-3">
      <component :is="'h' + level" :id="headingId" class="text-lg font-semibold">{{ node.label }}</component>
      <span v-if="node.hidden" class="rounded-full border border-slate-400 px-2 py-0.5 text-xs font-semibold text-slate-800">{{ fr ? 'Masqué' : 'Hidden' }}</span>
      <div v-if="editing" class="flex flex-wrap items-center gap-2">
        <label :for="headingId + '-label'" class="sr-only">{{ fr ? 'Libellé de' : 'Label of' }} {{ node.label }}</label>
        <input :id="headingId + '-label'" :value="node.label" type="text" maxlength="80" class="rounded border border-slate-400 px-2 py-1 text-sm" @change="actions.renameNode(node.id, ($event.target as HTMLInputElement).value)">
        <button type="button" :disabled="index === 0" :aria-label="(fr ? 'Monter' : 'Move up') + ' : ' + node.label" class="rounded border border-slate-300 px-2 py-1 text-xs disabled:opacity-30" @click="actions.moveNode(node.id, -1)">↑</button>
        <button type="button" :disabled="index === count - 1" :aria-label="(fr ? 'Descendre' : 'Move down') + ' : ' + node.label" class="rounded border border-slate-300 px-2 py-1 text-xs disabled:opacity-30" @click="actions.moveNode(node.id, 1)">↓</button>
        <button type="button" :aria-pressed="node.hidden" class="rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-100" @click="actions.toggleNode(node.id)">{{ node.hidden ? (fr ? 'Afficher' : 'Show') : (fr ? 'Masquer' : 'Hide') }}<span class="sr-only"> {{ node.label }}</span></button>
      </div>
    </div>

    <ul v-if="node.tags.length" :aria-label="(fr ? 'Mesures de ' : 'Measurements of ') + node.label" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <li v-for="(tag, tagIndex) in node.tags" :key="tag.tagId" class="flex flex-col gap-1" :class="tag.hidden ? 'opacity-60' : ''">
        <div class="grow">
        <DashboardWidget
          :type="tag.kind"
          :heading-level="level + 1"
          :title="tag.label"
          :value="values.get(tag.tagId)?.value"
          :unit="tag.unit"
          :state="stateOf(tag.tagId)"
          :observed-at="values.get(tag.tagId)?.observedAt ? formatDateTime(values.get(tag.tagId)!.observedAt) : ''"
        >
          <template v-if="editing" #actions>
            <div class="flex flex-wrap gap-1">
              <button type="button" :disabled="tagIndex === 0" :aria-label="(fr ? 'Avant' : 'Earlier') + ' : ' + tag.label" class="rounded border border-slate-300 px-2 py-1 text-xs disabled:opacity-30" @click="actions.moveTag(node.id, tag.tagId, -1)">←</button>
              <button type="button" :disabled="tagIndex === node.tags.length - 1" :aria-label="(fr ? 'Après' : 'Later') + ' : ' + tag.label" class="rounded border border-slate-300 px-2 py-1 text-xs disabled:opacity-30" @click="actions.moveTag(node.id, tag.tagId, 1)">→</button>
              <button type="button" :aria-pressed="tag.hidden" class="rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-100" @click="actions.toggleTag(tag.tagId)">{{ tag.hidden ? (fr ? 'Afficher' : 'Show') : (fr ? 'Masquer' : 'Hide') }}<span class="sr-only"> {{ tag.label }}</span></button>
            </div>
          </template>
        </DashboardWidget>
        </div>
        <div v-if="editing">
          <label :for="headingId + '-t-' + tagIndex" class="block text-xs font-semibold text-slate-700">{{ fr ? 'Libellé' : 'Label' }} ({{ tag.name }})</label>
          <input :id="headingId + '-t-' + tagIndex" :value="tag.label" type="text" maxlength="80" class="w-full rounded border border-slate-400 px-2 py-1 text-sm" @change="actions.renameTag(tag.tagId, ($event.target as HTMLInputElement).value)">
        </div>
      </li>
    </ul>

    <div v-if="node.children.length" class="mt-4 space-y-4">
      <SynopticNodeView
        v-for="(child, childIndex) in node.children" :key="child.id"
        :node="child" :depth="depth + 1" :index="childIndex" :count="node.children.length"
        :editing="editing" :values="values" :now-ms="nowMs" :stale-after-seconds="staleAfterSeconds"
      />
    </div>
  </section>
</template>
