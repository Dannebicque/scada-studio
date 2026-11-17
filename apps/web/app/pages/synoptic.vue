<script setup lang="ts">
import { buildSynopticTree, visibleTree, parseOverrides, emptyOverrides, countTags, type SynopticNode, type SynopticOverrides, type UnsPayload } from '~/utils/synopticModel'
import type { MeasureSample, Quality } from '~/utils/measureState'
import type { SynopticActions } from '~/components/SynopticNodeView.vue'

const { locale, t } = useAppLocale()
const fr = computed(() => locale.value === 'fr')
const { data: account, status: accountStatus } = useAccount()
const workspaces = computed(() => account.value?.workspaces ?? [])
const selectedWorkspace = ref('')
watch(workspaces, (items) => {
  if (!items.some(item => item.id === selectedWorkspace.value)) selectedWorkspace.value = items[0]?.id ?? ''
}, { immediate: true })

const uns = ref<UnsPayload | null>(null)
const status = ref<'idle' | 'pending' | 'success' | 'error'>('idle')
const error = computed(() => status.value === 'error')
let loadToken = 0
watch(selectedWorkspace, async (id) => {
  const token = ++loadToken // ignore une réponse tardive d'un espace précédent
  uns.value = null
  if (!id) { status.value = 'idle'; return }
  status.value = 'pending'
  try {
    const payload = await $fetch<UnsPayload>('/api/workspaces/' + id + '/uns')
    if (token === loadToken) { uns.value = payload; status.value = 'success' }
  }
  catch { if (token === loadToken) status.value = 'error' }
}, { immediate: true })
type Sample = MeasureSample & { tagId: string; value: number | string | boolean; quality: Quality | 'stale' }
const { data: telemetry, refresh } = await useFetch<{ values: Sample[]; staleAfterSeconds: number }>('/api/tag-values', { server: false, retry: 0 })
const values = computed(() => new Map((telemetry.value?.values ?? []).map(v => [v.tagId, v])))
const now = ref(Date.now())
watch(telemetry, () => { now.value = Date.now() })
let timer: ReturnType<typeof setInterval> | undefined
onMounted(() => { timer = setInterval(() => { now.value = Date.now(); void refresh() }, 5000) })
onUnmounted(() => { if (timer) clearInterval(timer) })

// Surcharges : stockées par espace, sur ce navigateur. Elles s'appliquent au modèle régénéré sans le modifier.
const overrides = ref<SynopticOverrides>(emptyOverrides())
const storageKey = computed(() => 'scada-synoptic:' + selectedWorkspace.value)
function load() {
  try { overrides.value = parseOverrides(JSON.parse(localStorage.getItem(storageKey.value) ?? 'null')) }
  catch { overrides.value = emptyOverrides() }
}
function persist() {
  try { localStorage.setItem(storageKey.value, JSON.stringify(overrides.value)) } catch { /* stockage indisponible : la session reste utilisable */ }
}
onMounted(load)
watch(selectedWorkspace, load)

const editing = ref(false)
const full = computed(() => buildSynopticTree(uns.value ?? { assets: [], bindings: [], tags: [] }, overrides.value))
const shown = computed(() => editing.value ? full.value : visibleTree(full.value))
const total = computed(() => countTags(full.value))
const customised = computed(() => Object.keys(overrides.value.nodes).length + Object.keys(overrides.value.tags).length)
const announcement = ref('')

function patch(group: 'nodes' | 'tags', id: string, change: { label?: string; hidden?: boolean; order?: number }) {
  const next = { ...overrides.value[group][id], ...change }
  if (next.hidden === false) delete next.hidden
  if (next.label === '') delete next.label
  const copy = { ...overrides.value[group] }
  if (Object.keys(next).length) copy[id] = next; else delete copy[id]
  overrides.value = { ...overrides.value, [group]: copy }
  persist()
}
function siblingsOf(id: string): SynopticNode[] {
  const find = (nodes: SynopticNode[]): SynopticNode[] | undefined => {
    if (nodes.some(n => n.id === id)) return nodes
    for (const n of nodes) { const hit = find(n.children); if (hit) return hit }
  }
  return find(full.value) ?? []
}
function reorder(group: 'nodes' | 'tags', ids: string[], id: string, delta: -1 | 1, label: string) {
  const from = ids.indexOf(id)
  const to = from + delta
  if (from < 0 || to < 0 || to >= ids.length) return
  const order = [...ids]
  order.splice(to, 0, order.splice(from, 1)[0]!)
  const copy = { ...overrides.value[group] }
  order.forEach((key, position) => { copy[key] = { ...copy[key], order: position } })
  overrides.value = { ...overrides.value, [group]: copy }
  persist()
  announcement.value = `${label} : ${fr.value ? 'position' : 'position'} ${to + 1} / ${ids.length}`
}
const nodeLabel = (id: string) => { const find = (nodes: SynopticNode[]): string | undefined => { for (const n of nodes) { if (n.id === id) return n.label; const h = find(n.children); if (h) return h } }; return find(full.value) ?? id }
const find = (nodes: SynopticNode[], id: string): SynopticNode | undefined => { for (const n of nodes) { if (n.id === id) return n; const h = find(n.children, id); if (h) return h } }

provide<SynopticActions>('synopticActions', {
  renameNode: (id, label) => patch('nodes', id, { label: label.trim().slice(0, 80) }),
  toggleNode: id => patch('nodes', id, { hidden: !find(full.value, id)?.hidden }),
  moveNode: (id, delta) => reorder('nodes', siblingsOf(id).map(n => n.id), id, delta, nodeLabel(id)),
  renameTag: (tagId, label) => patch('tags', tagId, { label: label.trim().slice(0, 80) }),
  toggleTag: (tagId) => {
    const current = overrides.value.tags[tagId]?.hidden === true
    patch('tags', tagId, { hidden: !current })
  },
  moveTag: (nodeId, tagId, delta) => {
    const node = find(full.value, nodeId)
    if (node) reorder('tags', node.tags.map(tag => tag.tagId), tagId, delta, node.tags.find(tag => tag.tagId === tagId)?.label ?? tagId)
  },
})
function reset() {
  overrides.value = emptyOverrides()
  persist()
  announcement.value = fr.value ? 'Adaptations supprimées : retour à la base générée.' : 'Customisations removed: back to the generated base.'
}
</script>

<template>
  <div class="space-y-6">
    <header>
      <p class="text-sm font-semibold uppercase tracking-widest text-sky-700">L4</p>
      <h1 class="mt-1 text-3xl font-bold">{{ t('synoptic') }}</h1>
      <p class="mt-2 max-w-3xl text-slate-700">{{ fr ? 'Base générée automatiquement depuis la hiérarchie du Unified Namespace : un bloc par niveau (site, atelier, équipement…) avec ses mesures en direct. Vous pouvez ensuite l’adapter sans toucher aux données.' : 'Base generated automatically from the Unified Namespace hierarchy: one block per level (site, workshop, equipment…) with live measurements. You can then adapt it without touching the data.' }}</p>
    </header>

    <p v-if="accountStatus === 'pending'" role="status">{{ t('checking') }}</p>
    <NuxtLink v-else-if="!account?.authenticated" to="/account" class="underline">{{ fr ? 'Se connecter' : 'Sign in' }}</NuxtLink>
    <p v-else-if="!workspaces.length">{{ fr ? 'Aucun espace accessible.' : 'No accessible workspaces.' }}</p>
    <template v-else>
      <div class="flex flex-wrap items-end gap-4">
        <div v-if="workspaces.length > 1">
          <label for="syn-workspace" class="block text-sm font-semibold">{{ fr ? 'Espace' : 'Workspace' }}</label>
          <select id="syn-workspace" v-model="selectedWorkspace" class="rounded-lg border bg-white px-3 py-2"><option v-for="item in workspaces" :key="item.id" :value="item.id">{{ item.name }}</option></select>
        </div>
        <button type="button" :aria-pressed="editing" class="rounded-lg border border-sky-700 px-4 py-2 text-sm font-semibold text-sky-900 hover:bg-sky-50" @click="editing = !editing">{{ editing ? (fr ? 'Terminer l’adaptation' : 'Finish adapting') : (fr ? 'Adapter la base' : 'Adapt the base') }}</button>
        <button v-if="customised" type="button" class="rounded-lg border border-slate-400 px-4 py-2 text-sm font-semibold hover:bg-slate-50" @click="reset">{{ fr ? 'Revenir à la base générée' : 'Back to generated base' }}</button>
      </div>
      <p v-if="editing" class="rounded-xl border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900">{{ fr ? 'Renommez, réordonnez ou masquez des niveaux et des mesures. Les adaptations sont enregistrées sur ce navigateur pour cet espace ; la base générée n’est jamais modifiée et suit l’arrivée de nouveaux tags.' : 'Rename, reorder or hide levels and measurements. Changes are saved in this browser for this workspace; the generated base is never modified and follows new tags.' }}</p>
      <p class="sr-only" role="status" aria-live="polite">{{ announcement }}</p>

      <p v-if="status === 'pending'" role="status">{{ t('checking') }}</p>
      <p v-else-if="error" role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-900">{{ fr ? 'Namespace indisponible.' : 'Namespace unavailable.' }}</p>
      <p v-else-if="!total" class="rounded-xl border border-dashed border-slate-400 p-8 text-slate-700">
        {{ fr ? 'Aucun tag dans cet espace.' : 'No tags in this workspace.' }}
        <NuxtLink to="/uns-discovery" class="underline">{{ fr ? 'Découvrir depuis le broker' : 'Discover from the broker' }}</NuxtLink>
      </p>
      <p v-else-if="!shown.length" class="rounded-xl border border-dashed border-slate-400 p-8 text-slate-700">{{ fr ? 'Tout est masqué. Utilisez « Adapter la base » pour réafficher des éléments.' : 'Everything is hidden. Use “Adapt the base” to show items again.' }}</p>
      <div v-else class="space-y-4">
        <SynopticNodeView
          v-for="(node, index) in shown" :key="node.id"
          :node="node" :depth="0" :index="index" :count="shown.length"
          :editing="editing" :values="values" :now-ms="now" :stale-after-seconds="telemetry?.staleAfterSeconds ?? 30"
        />
      </div>
    </template>
  </div>
</template>
