<script setup lang="ts">
import { assetLabel, buildSelectedPage } from '~/utils/unsDiscovery'
import type { DiscoveryError } from '~/utils/unsDiscovery'
import type { ImportTag } from '~/utils/unsImport'

const { locale } = useAppLocale()
const fr = computed(() => locale.value === 'fr')
const { data: account, status: accountStatus } = useAccount()
const { busy: dashboardBusy, error: dashboardError, createDashboard } = usePersonalActions()

// Seuls les propriétaires peuvent explorer le broker et créer le catalogue.
const owned = computed(() => (account.value?.workspaces ?? []).filter(item => item.role === 'owner'))
const workspaceId = ref('')
watch(owned, (items) => {
  if (!items.some(item => item.id === workspaceId.value)) workspaceId.value = items[0]?.id ?? ''
}, { immediate: true })

const selected = ref(new Set<string>())
const { status, statusError, phase, error, result, provisioned, loadStatus, discover, provision } = useUnsDiscovery(workspaceId)
watch(workspaceId, () => { selected.value = new Set(); void loadStatus() }, { immediate: true })

const prefix = ref('')
const seconds = ref(15)
const enable = ref(false)
const listening = computed(() => phase.value === 'listening')
const working = computed(() => phase.value !== 'idle')

const errorText: Record<DiscoveryError, { fr: string; en: string }> = {
  unauthenticated: { fr: 'Session expirée : reconnectez-vous.', en: 'Session expired: sign in again.' },
  forbidden: { fr: 'Accès refusé : réservé aux propriétaires, ou préfixe non autorisé sur ce serveur.', en: 'Access denied: owners only, or prefix not allowed on this server.' },
  notFound: { fr: 'Espace introuvable.', en: 'Workspace not found.' },
  invalid: { fr: 'Requête invalide : vérifiez le préfixe (segments simples séparés par « / », sans joker).', en: 'Invalid request: check the prefix (plain segments separated by “/”, no wildcard).' },
  broker: { fr: 'Broker injoignable ou refus d’authentification. Vérifiez la configuration MQTT du serveur.', en: 'Broker unreachable or authentication refused. Check the server’s MQTT configuration.' },
  unavailable: { fr: 'Service indisponible. Réessayez dans un instant.', en: 'Service unavailable. Please try again shortly.' },
}

async function explore() {
  const ok = await discover(prefix.value.trim().replace(/^\/+|\/+$/g, ''), seconds.value)
  if (ok && result.value) selected.value = new Set(result.value.model.assets.map(asset => asset.topic))
}
function toggle(topic: string, checked: boolean) {
  const next = new Set(selected.value)
  if (checked) next.add(topic); else next.delete(topic)
  selected.value = next
}
const allSelected = computed(() => !!result.value && selected.value.size === result.value.model.assets.length)
function selectAll(on: boolean) { selected.value = new Set(on ? result.value?.model.assets.map(asset => asset.topic) : []) }

async function create() {
  await provision([...selected.value], enable.value)
}

const dashboardName = ref('')
const dashboardSaved = ref(false)
const dashboardNote = ref('')
watch(result, (value) => { dashboardName.value = value ? `UNS ${value.prefix}`.slice(0, 120) : ''; dashboardSaved.value = false; dashboardNote.value = '' })
const slug = computed(() => dashboardName.value.trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''))

async function makeDashboard() {
  if (!result.value || dashboardBusy.value || !slug.value) return
  dashboardSaved.value = false
  dashboardNote.value = ''
  let tags: ImportTag[] = []
  try {
    tags = (await $fetch<{ tags: ImportTag[] }>(`/api/workspaces/${encodeURIComponent(workspaceId.value)}/uns`, { retry: 0 })).tags
  } catch {
    dashboardNote.value = fr.value ? 'Catalogue indisponible : impossible d’associer les widgets.' : 'Catalog unavailable: cannot match widgets.'
    return
  }
  const parsed = parseInferredPage(buildSelectedPage(result.value, selected.value, dashboardName.value.trim()))
  if (parsed.error) { dashboardNote.value = fr.value ? 'Page inférée invalide.' : 'Invalid inferred page.'; return }
  const plan = toPersonalWidgets(parsed.page, tags)
  if (!plan.widgets.length) {
    dashboardNote.value = fr.value ? 'Aucun widget associé à un tag du catalogue.' : 'No widget matched a catalog tag.'
    return
  }
  const widgets = plan.widgets.slice(0, MAX_PERSONAL_WIDGETS)
  dashboardSaved.value = await createDashboard({ workspaceId: workspaceId.value, name: dashboardName.value.trim(), slug: slug.value, widgets })
  if (dashboardSaved.value && plan.widgets.length > widgets.length) {
    dashboardNote.value = fr.value ? `Limité à ${MAX_PERSONAL_WIDGETS} widgets (${plan.widgets.length - widgets.length} ignorés).` : `Limited to ${MAX_PERSONAL_WIDGETS} widgets (${plan.widgets.length - widgets.length} skipped).`
  }
}
</script>

<template>
  <div class="space-y-6">
    <header>
      <h1 class="text-3xl font-bold">{{ fr ? 'Découverte UNS' : 'UNS discovery' }}</h1>
      <p class="mt-2 text-slate-600">{{ fr
        ? 'Écoutez brièvement un espace de noms du broker configuré sur le serveur, vérifiez ce qui a été trouvé, puis créez le catalogue et un tableau de bord. L’écoute est en lecture seule : rien n’est publié sur le broker.'
        : 'Briefly listen to a namespace on the broker configured on the server, review what was found, then create the catalog and a dashboard. Listening is read-only: nothing is published to the broker.' }}</p>
    </header>

    <p v-if="accountStatus === 'pending'" role="status">{{ fr ? 'Chargement…' : 'Loading…' }}</p>
    <NuxtLink v-else-if="!account?.authenticated" to="/account" class="underline">{{ fr ? 'Se connecter' : 'Sign in' }}</NuxtLink>
    <p v-else-if="!owned.length">{{ fr ? 'Cette page est réservée aux propriétaires d’un espace.' : 'This page is reserved for workspace owners.' }}</p>

    <template v-else>
      <section class="rounded-xl border bg-white p-4" aria-labelledby="discovery-source">
        <h2 id="discovery-source" class="text-xl font-semibold">{{ fr ? '1. Source' : '1. Source' }}</h2>
        <div class="mt-3 flex flex-wrap items-end gap-4">
          <div>
            <label for="discovery-workspace" class="block text-sm font-semibold">{{ fr ? 'Espace' : 'Workspace' }}</label>
            <select id="discovery-workspace" v-model="workspaceId" :disabled="working" class="rounded border p-2"><option v-for="item in owned" :key="item.id" :value="item.id">{{ item.name }}</option></select>
          </div>
          <p v-if="status" class="text-sm"><span class="font-semibold">{{ fr ? 'Broker du serveur' : 'Server broker' }}</span> : <code class="font-mono">{{ status.broker }}</code></p>
        </div>
        <p v-if="statusError" role="alert" class="mt-3 rounded border border-red-300 bg-red-50 p-3 text-red-900">{{ errorText[statusError][fr ? 'fr' : 'en'] }}</p>
        <p v-else-if="status && !status.enabled" role="alert" class="mt-3 rounded border border-amber-300 bg-amber-50 p-3 text-amber-900">{{ fr
          ? 'La découverte est désactivée sur ce serveur : définissez UNS_ALLOWED_PREFIXES (liste de préfixes UNS autorisés, ex. acme/troyes) dans la configuration de l’API.'
          : 'Discovery is disabled on this server: set UNS_ALLOWED_PREFIXES (allowed UNS prefixes, e.g. acme/troyes) in the API configuration.' }}</p>

        <form v-if="status?.enabled" class="mt-4 flex flex-wrap items-end gap-4" @submit.prevent="explore">
          <div>
            <label for="discovery-prefix" class="block text-sm font-semibold">{{ fr ? 'Préfixe à explorer' : 'Prefix to explore' }}</label>
            <input id="discovery-prefix" v-model="prefix" required maxlength="400" pattern="/?[A-Za-z0-9_\-]+(/[A-Za-z0-9_\-]+)*/?" autocomplete="off" spellcheck="false"
              aria-describedby="discovery-prefix-help" placeholder="acme/troyes" class="w-72 rounded border p-2 font-mono">
          </div>
          <div>
            <label for="discovery-seconds" class="block text-sm font-semibold">{{ fr ? 'Durée d’écoute (s)' : 'Listening time (s)' }}</label>
            <input id="discovery-seconds" v-model.number="seconds" type="number" min="5" max="30" step="1" required class="w-24 rounded border p-2">
          </div>
          <button type="submit" :disabled="working" class="rounded bg-sky-800 px-4 py-2 text-white disabled:opacity-50">{{ listening ? (fr ? 'Écoute en cours…' : 'Listening…') : (fr ? 'Explorer' : 'Explore') }}</button>
          <p id="discovery-prefix-help" class="basis-full text-sm text-slate-600">{{ fr
            ? 'Entreprise/site, par exemple acme/troyes. Le serveur écoute ce préfixe et tout ce qu’il contient, jamais le broker entier. Une durée plus longue révèle les tags lents.'
            : 'Enterprise/site, e.g. acme/troyes. The server listens to this prefix and everything under it, never the whole broker. A longer time reveals slow tags.' }}</p>
        </form>

        <div v-if="listening" role="status" class="mt-4">
          <p>{{ fr ? `Écoute de ${prefix} pendant ${seconds} s…` : `Listening to ${prefix} for ${seconds} s…` }}</p>
          <progress class="mt-2 w-full max-w-md" :aria-label="fr ? 'Écoute en cours' : 'Listening in progress'" />
        </div>
        <p v-if="error && !result" role="alert" class="mt-3 rounded border border-red-300 bg-red-50 p-3 text-red-900">{{ errorText[error][fr ? 'fr' : 'en'] }}</p>
      </section>

      <section v-if="result" class="rounded-xl border bg-white p-4" aria-labelledby="discovery-found">
        <h2 id="discovery-found" class="text-xl font-semibold">{{ fr ? '2. Ce qui a été trouvé' : '2. What was found' }}</h2>
        <p role="status" class="mt-1 text-sm text-slate-700">{{ fr
          ? `${result.frames} trame(s) reçue(s), ${result.model.assets.length} équipement(s), ${result.model.assets.reduce((n, a) => n + a.tags.length, 0)} tag(s) sous ${result.prefix}.`
          : `${result.frames} frame(s) received, ${result.model.assets.length} asset(s), ${result.model.assets.reduce((n, a) => n + a.tags.length, 0)} tag(s) under ${result.prefix}.` }}</p>
        <p v-if="result.truncated" class="mt-2 rounded border border-amber-300 bg-amber-50 p-3 text-amber-900">{{ fr ? 'Exploration tronquée (limite de trames ou de tags atteinte) : affinez le préfixe.' : 'Exploration truncated (frame or tag limit reached): narrow the prefix.' }}</p>
        <p v-if="!result.model.assets.length" class="mt-3">{{ fr ? 'Aucune trame reçue : vérifiez le préfixe, ou allongez la durée d’écoute.' : 'No frames received: check the prefix or listen for longer.' }}</p>

        <fieldset v-else class="mt-4">
          <legend class="font-semibold">{{ fr ? 'Équipements à retenir' : 'Assets to keep' }}</legend>
          <p class="mt-1"><button type="button" class="underline" @click="selectAll(!allSelected)">{{ allSelected ? (fr ? 'Tout désélectionner' : 'Deselect all') : (fr ? 'Tout sélectionner' : 'Select all') }}</button></p>
          <ul class="mt-2 divide-y">
            <li v-for="(asset, index) in result.model.assets" :key="asset.topic" class="py-2">
              <div class="flex items-start gap-3">
                <input :id="`asset-${index}`" type="checkbox" class="mt-1 h-5 w-5" :checked="selected.has(asset.topic)" @change="toggle(asset.topic, ($event.target as HTMLInputElement).checked)">
                <div class="min-w-0">
                  <label :for="`asset-${index}`" class="font-medium">{{ assetLabel(asset) }} <span class="text-sm font-normal text-slate-500">({{ asset.tags.length }} {{ fr ? 'tags' : 'tags' }})</span></label>
                  <p class="truncate font-mono text-xs text-slate-600" :title="asset.topic">{{ asset.topic }}</p>
                  <p class="text-sm text-slate-700">{{ asset.tags.slice(0, 8).map(tag => tag.name + (tag.unit ? ` (${tag.unit})` : '')).join(', ') }}<span v-if="asset.tags.length > 8"> …</span></p>
                </div>
              </div>
            </li>
          </ul>
        </fieldset>
      </section>

      <section v-if="result && result.model.assets.length" class="rounded-xl border bg-white p-4" aria-labelledby="discovery-create">
        <h2 id="discovery-create" class="text-xl font-semibold">{{ fr ? '3. Créer le catalogue' : '3. Create the catalog' }}</h2>
        <form class="mt-3 space-y-3" @submit.prevent="create">
          <div class="flex items-start gap-3">
            <input id="discovery-enable" v-model="enable" type="checkbox" class="mt-1 h-5 w-5" aria-describedby="discovery-enable-help">
            <div>
              <label for="discovery-enable" class="font-medium">{{ fr ? 'Activer l’ingestion immédiatement' : 'Enable ingestion immediately' }}</label>
              <p id="discovery-enable-help" class="text-sm text-slate-600">{{ fr
                ? 'Sinon, les sources sont créées désactivées : aucune valeur n’est enregistrée tant qu’elles ne sont pas activées. Une fois activées, le consommateur les prend en compte en 30 secondes environ.'
                : 'Otherwise sources are created disabled: no value is stored until they are enabled. Once enabled, the consumer picks them up within about 30 seconds.' }}</p>
            </div>
          </div>
          <button type="submit" :disabled="working || selected.size === 0" class="rounded bg-sky-800 px-4 py-2 text-white disabled:opacity-50">
            {{ phase === 'provisioning' ? (fr ? 'Création…' : 'Creating…') : (fr ? `Créer le catalogue (${selected.size} équipement${selected.size > 1 ? 's' : ''})` : `Create the catalog (${selected.size} asset${selected.size > 1 ? 's' : ''})`) }}
          </button>
        </form>
        <p v-if="error && result" role="alert" class="mt-3 rounded border border-red-300 bg-red-50 p-3 text-red-900">{{ errorText[error][fr ? 'fr' : 'en'] }}</p>

        <div v-if="provisioned" role="status" class="mt-4 rounded border border-emerald-300 bg-emerald-50 p-3 text-emerald-900">
          <p>{{ fr
            ? `${provisioned.createdSources} source(s) et ${provisioned.createdTags} tag(s) créés, ${provisioned.skipped} ignoré(s).`
            : `${provisioned.createdSources} source(s) and ${provisioned.createdTags} tag(s) created, ${provisioned.skipped} skipped.` }}</p>
          <ul v-if="provisioned.messages.length" class="mt-2 list-disc pl-5 text-sm">
            <li v-for="message in provisioned.messages" :key="message">{{ message }}</li>
          </ul>
        </div>
      </section>

      <section v-if="provisioned" class="rounded-xl border bg-white p-4" aria-labelledby="discovery-dashboard">
        <h2 id="discovery-dashboard" class="text-xl font-semibold">{{ fr ? '4. Créer un tableau de bord' : '4. Create a dashboard' }}</h2>
        <form class="mt-3 flex flex-wrap items-end gap-3" @submit.prevent="makeDashboard">
          <div>
            <label for="discovery-dashboard-name" class="block text-sm font-semibold">{{ fr ? 'Nom du tableau de bord' : 'Dashboard name' }}</label>
            <input id="discovery-dashboard-name" v-model="dashboardName" required maxlength="120" class="rounded border p-2">
          </div>
          <button type="submit" :disabled="dashboardBusy || !slug" class="rounded bg-sky-800 px-4 py-2 text-white disabled:opacity-50">{{ fr ? 'Créer le tableau de bord' : 'Create dashboard' }}</button>
        </form>
        <p v-if="dashboardError" role="alert" class="mt-3 text-red-800">{{ dashboardError }}</p>
        <p v-if="dashboardNote" role="alert" class="mt-3 text-amber-900">{{ dashboardNote }}</p>
        <p v-if="dashboardSaved" role="status" class="mt-3 text-emerald-900">{{ fr ? 'Tableau de bord créé.' : 'Dashboard created.' }} <NuxtLink to="/dashboard" class="underline">{{ fr ? 'Ouvrir les tableaux de bord' : 'Open dashboards' }}</NuxtLink></p>
      </section>
    </template>
  </div>
</template>
