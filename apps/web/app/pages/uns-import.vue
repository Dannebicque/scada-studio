<script setup lang="ts">
import type { ImportElement, ImportError, ImportPage, ImportTag } from '~/utils/unsImport'

const { locale } = useAppLocale()
const fr = computed(() => locale.value === 'fr')
const { data: account, status: accountStatus } = useAccount()
const { busy, error: saveError, createDashboard } = usePersonalActions()

const workspaces = computed(() => account.value?.workspaces ?? [])
const workspaceId = ref('')
watch(workspaces, (items) => {
  if (!items.some(item => item.id === workspaceId.value)) workspaceId.value = items[0]?.id ?? ''
}, { immediate: true })

const tags = ref<ImportTag[]>([])
const tagsState = ref<'idle' | 'loading' | 'error'>('idle')
watch(workspaceId, async (id) => {
  tags.value = []
  if (!id) return
  tagsState.value = 'loading'
  try {
    const result = await $fetch<{ tags: ImportTag[] }>('/api/workspaces/' + encodeURIComponent(id) + '/uns', { retry: 0 })
    tags.value = result.tags
    tagsState.value = 'idle'
  } catch { tagsState.value = 'error' }
}, { immediate: true })

type Sample = { tagId: string; value: number | string | boolean; observedAt: string }
const samples = ref(new Map<string, Sample>())
const now = ref(Date.now())
async function refreshValues() {
  now.value = Date.now()
  try { samples.value = new Map((await $fetch<{ values: Sample[] }>('/api/tag-values', { retry: 0 })).values.map(v => [v.tagId, v])) } catch { /* l'aperçu reste utilisable sans mesures */ }
}
let timer: ReturnType<typeof setInterval> | undefined
onMounted(() => { void refreshValues(); timer = setInterval(refreshValues, 5000) })
onUnmounted(() => { if (timer) clearInterval(timer) })

const page = ref<ImportPage | null>(null)
const fileName = ref('')
const parseError = ref<ImportError | ''>('')
const removed = ref(new Set<string>())
const saved = ref(false)
const name = ref('')

const errorText: Record<ImportError, { fr: string; en: string }> = {
  tooLarge: { fr: 'Fichier trop volumineux (1 Mo maximum).', en: 'File too large (1 MB maximum).' },
  invalidJson: { fr: 'Ce fichier n’est pas du JSON valide.', en: 'This file is not valid JSON.' },
  invalidShape: { fr: 'Format de page non reconnu (schemaVersion 1 attendu).', en: 'Unrecognised page format (schemaVersion 1 expected).' },
  unsupportedKind: { fr: 'Seules les pages de type « dashboard » sont importables.', en: 'Only “dashboard” pages can be imported.' },
  tooManyElements: { fr: 'Trop d’éléments (300 maximum).', en: 'Too many elements (300 maximum).' },
  duplicateId: { fr: 'Identifiants d’éléments en double.', en: 'Duplicate element ids.' },
}

async function onFile(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  page.value = null; parseError.value = ''; removed.value = new Set(); saved.value = false; fileName.value = ''
  if (!file) return
  if (file.size > MAX_IMPORT_BYTES) { parseError.value = 'tooLarge'; return }
  const result = parseInferredPage(await file.text())
  if (result.error) { parseError.value = result.error; return }
  page.value = result.page
  name.value = result.page.title
  fileName.value = file.name
}

const visible = computed(() => (page.value?.elements ?? []).filter(el => !removed.value.has(el.id)))
const plan = computed(() => page.value ? toPersonalWidgets({ ...page.value, elements: visible.value }, tags.value) : null)
const tagById = computed(() => new Map(tags.value.map(tag => [tag.tagId, tag])))
const matched = (el: ImportElement) => matchTag(el.topic, tags.value, el.binding)
function removeElement(id: string) { removed.value = new Set([...removed.value, id]) }
function restoreAll() { removed.value = new Set() }

function display(el: ImportElement): string {
  const tag = matched(el)
  const sample = tag ? samples.value.get(tag.tagId) : undefined
  if (!sample) return '—'
  const time = Date.parse(sample.observedAt)
  const fresh = Number.isFinite(time) && now.value - time <= 30_000
  const v = typeof sample.value === 'boolean' ? (sample.value ? 'ON' : 'OFF') : String(sample.value)
  return v + (el.unit ? ' ' + el.unit : '') + (fresh ? '' : fr.value ? ' (périmée)' : ' (stale)')
}

const slug = computed(() => name.value.trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''))
const canSave = computed(() => !busy.value && !!workspaceId.value && !!slug.value && !!plan.value
  && plan.value.widgets.length > 0 && plan.value.widgets.length <= MAX_PERSONAL_WIDGETS)
async function save() {
  if (!plan.value || !canSave.value) return
  saved.value = await createDashboard({ workspaceId: workspaceId.value, name: name.value.trim(), slug: slug.value, widgets: plan.value.widgets })
}
</script>

<template>
  <main class="mx-auto max-w-6xl space-y-6">
    <header>
      <h1 class="text-3xl font-bold">{{ fr ? 'Importer un dashboard depuis le UNS' : 'Import a dashboard from the UNS' }}</h1>
      <p class="mt-2 text-slate-600">{{ fr
        ? 'Chargez une page générée par tools/uns-infer, vérifiez-la, puis enregistrez-la dans Mon espace. Rien n’est publié sans votre validation.'
        : 'Load a page generated by tools/uns-infer, review it, then save it to My workspace. Nothing is published without your confirmation.' }}</p>
    </header>

    <p v-if="accountStatus === 'pending'" role="status">{{ fr ? 'Chargement…' : 'Loading…' }}</p>
    <NuxtLink v-else-if="!account?.authenticated" to="/account" class="underline">{{ fr ? 'Se connecter' : 'Sign in' }}</NuxtLink>
    <p v-else-if="!workspaces.length">{{ fr ? 'Aucun espace accessible.' : 'No accessible workspaces.' }}</p>

    <template v-else>
      <section class="flex flex-wrap items-end gap-4 rounded-xl border bg-white p-4">
        <div>
          <label for="import-workspace" class="block text-sm font-semibold">{{ fr ? 'Espace' : 'Workspace' }}</label>
          <select id="import-workspace" v-model="workspaceId" class="rounded border p-2"><option v-for="item in workspaces" :key="item.id" :value="item.id">{{ item.name }}</option></select>
        </div>
        <div>
          <label for="import-file" class="block text-sm font-semibold">{{ fr ? 'Page générée (.json)' : 'Generated page (.json)' }}</label>
          <input id="import-file" type="file" accept=".json,application/json" class="block rounded border p-2" aria-describedby="import-help" @change="onFile">
        </div>
        <p id="import-help" class="basis-full text-sm text-slate-600">{{ fr
          ? 'Un élément est associé à un tag du catalogue lorsque ses deux derniers segments de topic (équipement/tag) correspondent à source/tag, ou lorsque son chemin correspond à une source créée par app:uns:provision. Les éléments non associés sont signalés et ignorés à l’enregistrement.'
          : 'An element is matched to a catalog tag when its last two topic segments (asset/tag) equal source/tag, or when its path matches a source created by app:uns:provision. Unmatched elements are flagged and skipped on save.' }}</p>
      </section>

      <p v-if="parseError" role="alert" class="rounded border border-red-300 bg-red-50 p-3 text-red-900">{{ errorText[parseError][fr ? 'fr' : 'en'] }}</p>
      <p v-if="tagsState === 'error'" role="alert" class="rounded border border-amber-300 bg-amber-50 p-3 text-amber-900">{{ fr ? 'Catalogue indisponible : aucune association possible.' : 'Catalog unavailable: no matching possible.' }}</p>

      <template v-if="page && plan">
        <section aria-labelledby="import-preview">
          <div class="flex flex-wrap items-baseline justify-between gap-3">
            <h2 id="import-preview" class="text-xl font-semibold">{{ page.title }} <span class="text-sm font-normal text-slate-500">{{ fileName }}</span></h2>
            <button v-if="removed.size" type="button" class="underline" @click="restoreAll">{{ fr ? 'Rétablir les éléments retirés' : 'Restore removed elements' }} ({{ removed.size }})</button>
          </div>
          <p role="status" class="mt-1 text-sm text-slate-700">{{ fr
            ? `${plan.widgets.length} widget(s) seront créés, ${plan.unmatched.length} non associé(s), ${plan.duplicates} doublon(s) écarté(s).`
            : `${plan.widgets.length} widget(s) will be created, ${plan.unmatched.length} unmatched, ${plan.duplicates} duplicate(s) skipped.` }}</p>
          <p v-if="plan.widgets.length > MAX_PERSONAL_WIDGETS" role="alert" class="mt-1 text-red-800">{{ fr ? `Maximum ${MAX_PERSONAL_WIDGETS} widgets : retirez des éléments.` : `Maximum ${MAX_PERSONAL_WIDGETS} widgets: remove some elements.` }}</p>

          <ul class="mt-4 flex flex-col gap-3 md:grid md:grid-cols-12 md:auto-rows-[2.75rem]">
            <li v-for="el in visible" :key="el.id"
              :style="{ '--col': `${el.layout.x + 1} / span ${el.layout.width}`, '--row': `${el.layout.y + 1} / span ${el.layout.height}` }"
              class="min-h-0 md:[grid-column:var(--col)] md:[grid-row:var(--row)]"
              :class="el.type === SECTION_TYPE ? '' : 'overflow-hidden rounded-lg border bg-white p-3'">
              <h3 v-if="el.type === SECTION_TYPE" class="border-b border-slate-300 pb-1 text-base font-semibold">{{ el.label }}</h3>
              <template v-else>
                <div class="flex items-start justify-between gap-2">
                  <div class="min-w-0">
                    <p class="truncate font-medium">{{ el.label }}</p>
                    <p class="text-xs text-slate-500">{{ el.type }}</p>
                  </div>
                  <button type="button" class="shrink-0 text-sm text-red-800 underline" @click="removeElement(el.id)">{{ fr ? 'Retirer' : 'Remove' }}<span class="sr-only"> {{ el.label }}</span></button>
                </div>
                <p v-if="matched(el)" class="truncate text-sm" :title="matched(el)?.path">
                  <span class="font-semibold text-emerald-800">{{ fr ? 'Associé' : 'Matched' }}</span> · <span class="font-mono text-xs">{{ matched(el)?.path }}</span> · <span aria-label="">{{ display(el) }}</span>
                </p>
                <p v-else class="text-sm font-semibold text-amber-800">{{ fr ? 'Non associé' : 'Unmatched' }}<span v-if="el.topic" class="ml-1 truncate font-mono text-xs font-normal text-slate-500">{{ el.topic }}</span></p>
              </template>
            </li>
          </ul>
        </section>

        <form class="flex flex-wrap items-end gap-3 rounded-xl border bg-white p-4" @submit.prevent="save">
          <div>
            <label for="import-name" class="block text-sm font-semibold">{{ fr ? 'Nom du tableau de bord' : 'Dashboard name' }}</label>
            <input id="import-name" v-model="name" required maxlength="120" class="rounded border p-2">
          </div>
          <button type="submit" :disabled="!canSave" class="rounded bg-sky-800 px-4 py-2 text-white disabled:opacity-50">{{ fr ? 'Créer le tableau de bord' : 'Create dashboard' }}</button>
          <p v-if="saveError" role="alert" class="basis-full text-red-800">{{ saveError }}</p>
          <p v-if="saved" role="status" class="basis-full text-emerald-900">{{ fr ? 'Tableau de bord créé.' : 'Dashboard created.' }} <NuxtLink to="/personal" class="underline">{{ fr ? 'Voir Mon espace' : 'Open My workspace' }}</NuxtLink></p>
        </form>
      </template>
    </template>
  </main>
</template>
