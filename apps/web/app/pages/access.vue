<script setup lang="ts">
const { locale } = useAppLocale()
const fr = computed(() => locale.value === 'fr')
type Workspace = { id: string; name: string; role: string }
type Account = { authenticated: boolean; workspaces: Workspace[] }
type Member = { userId: string; email: string; role: 'viewer' | 'editor' | 'owner' }
const { data: account, status: accountStatus, error: accountError, refresh: refreshAccount } = await useFetch<Account>('/api/me', { server: false, retry: 0 })
const owned = computed(() => account.value?.workspaces.filter(w => w.role === 'owner') ?? [])
const selected = ref('')
const members = ref<Member[]>([])
const loading = ref(false)
const error = ref('')
const search = ref('')
const roleFilter = ref('all')
const pageSize = 10
const page = ref(1)
const roles = ['viewer', 'editor', 'owner'] as const
const roleLabels = computed<Record<string, string>>(() => fr.value
  ? { viewer: 'Lecture', editor: 'Édition', owner: 'Propriétaire' }
  : { viewer: 'Viewer', editor: 'Editor', owner: 'Owner' })
const currentWorkspace = computed(() => owned.value.find(w => w.id === selected.value))
const filtered = computed(() => members.value.filter(m =>
  (roleFilter.value === 'all' || m.role === roleFilter.value)
  && m.email.toLocaleLowerCase().includes(search.value.trim().toLocaleLowerCase()),
))
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / pageSize)))
const visible = computed(() => filtered.value.slice((page.value - 1) * pageSize, page.value * pageSize))
const owners = computed(() => members.value.filter(m => m.role === 'owner').length)
watch([search, roleFilter], () => { page.value = 1 })
watch(owned, async (workspaces) => {
  if (!workspaces.length) { selected.value = ''; members.value = []; return }
  if (!workspaces.some(w => w.id === selected.value)) {
    selected.value = workspaces[0]!.id
    await load()
  }
}, { immediate: true })
async function load() {
  const workspaceId = selected.value
  members.value = []
  error.value = ''
  search.value = ''
  roleFilter.value = 'all'
  page.value = 1
  if (!workspaceId) return
  loading.value = true
  try {
    const result = await $fetch<{ members: Member[] }>(`/api/workspaces/${encodeURIComponent(workspaceId)}/members`, { retry: 0 })
    if (workspaceId === selected.value) members.value = result.members.sort((a, b) => a.email.localeCompare(b.email))
  } catch {
    if (workspaceId === selected.value) error.value = fr.value ? 'Impossible de récupérer les accès de cet espace.' : 'Unable to load access for this workspace.'
  } finally {
    if (workspaceId === selected.value) loading.value = false
  }
}
</script>

<template>
  <main class="mx-auto max-w-6xl space-y-7">
    <nav :aria-label="fr ? 'Fil d’Ariane' : 'Breadcrumb'" class="text-sm text-slate-500">
      <NuxtLink to="/account" class="rounded hover:text-sky-800 hover:underline focus-visible:outline-2 focus-visible:outline-sky-600">{{ fr ? 'Mon compte' : 'My account' }}</NuxtLink>
      <span aria-hidden="true" class="mx-2">/</span>
      <span class="text-slate-800">{{ fr ? 'Gestion des accès' : 'Access management' }}</span>
    </nav>
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="text-xs font-semibold uppercase tracking-widest text-sky-700">{{ fr ? 'Administration des espaces' : 'Workspace administration' }}</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">{{ fr ? 'Gestion des accès' : 'Access management' }}</h1>
        <p class="mt-2 max-w-2xl text-slate-600">{{ fr ? 'Consultez les membres et leurs autorisations dans vos espaces de supervision.' : 'Review members and permissions across your monitoring workspaces.' }}</p>
      </div>
      <button type="button" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600" @click="refreshAccount()">{{ fr ? 'Actualiser les espaces' : 'Refresh workspaces' }}</button>
    </header>

    <p v-if="accountStatus === 'pending'" role="status" class="rounded-xl border border-slate-200 bg-white p-6">{{ fr ? 'Chargement du compte…' : 'Loading account…' }}</p>
    <section v-else-if="accountError" role="alert" class="rounded-xl border border-red-200 bg-red-50 p-6">
      <h2 class="font-semibold text-red-900">{{ fr ? 'Compte indisponible' : 'Account unavailable' }}</h2>
      <p class="mt-2 text-red-800">{{ fr ? 'Impossible de vérifier vos autorisations.' : 'Unable to verify your permissions.' }}</p>
      <button type="button" class="mt-3 rounded-lg border border-red-300 px-3 py-2 text-sm" @click="refreshAccount()">{{ fr ? 'Réessayer' : 'Try again' }}</button>
    </section>
    <section v-else-if="!account?.authenticated" class="rounded-xl border border-amber-200 bg-amber-50 p-6">
      <h2 class="text-lg font-semibold">{{ fr ? 'Connexion nécessaire' : 'Sign-in required' }}</h2>
      <p class="mt-2 text-slate-700">{{ fr ? 'Connectez-vous pour consulter les accès.' : 'Sign in to review workspace access.' }}</p>
      <NuxtLink to="/account" class="mt-4 inline-block rounded-lg bg-sky-800 px-4 py-2 font-medium text-white">{{ fr ? 'Se connecter' : 'Sign in' }}</NuxtLink>
    </section>
    <section v-else-if="!owned.length" class="rounded-xl border border-slate-200 bg-white p-8">
      <h2 class="text-lg font-semibold">{{ fr ? 'Aucun espace à administrer' : 'No workspaces to manage' }}</h2>
      <p class="mt-2 text-slate-600">{{ fr ? 'Seuls les propriétaires peuvent consulter la liste des membres.' : 'Only owners can view workspace members.' }}</p>
      <NuxtLink to="/account" class="mt-4 inline-block font-medium text-sky-800 underline">{{ fr ? 'Retour à mon compte' : 'Back to my account' }}</NuxtLink>
    </section>
    <template v-else>
      <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <label for="access-workspace" class="block text-sm font-semibold text-slate-800">{{ fr ? 'Espace de supervision' : 'Monitoring workspace' }}</label>
        <select id="access-workspace" v-model="selected" class="mt-2 w-full max-w-xl rounded-lg border border-slate-300 bg-white px-3 py-3 text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600" @change="load">
          <option v-for="workspace in owned" :key="workspace.id" :value="workspace.id">{{ workspace.name }}</option>
        </select>
        <p class="mt-2 text-sm text-slate-500">{{ fr ? 'Vous ne voyez que les espaces dont vous êtes propriétaire.' : 'Only workspaces you own are listed.' }}</p>
      </section>

      <section v-if="currentWorkspace" class="space-y-5" :aria-label="fr ? 'Membres de l’espace' : 'Workspace members'">
        <div class="grid gap-4 sm:grid-cols-3">
          <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">{{ fr ? 'Membres' : 'Members' }}</p>
            <p class="mt-1 text-3xl font-semibold tabular-nums">{{ loading ? '—' : members.length }}</p>
          </div>
          <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">{{ fr ? 'Propriétaires' : 'Owners' }}</p>
            <p class="mt-1 text-3xl font-semibold tabular-nums">{{ loading ? '—' : owners }}</p>
          </div>
          <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">{{ fr ? 'Votre rôle' : 'Your role' }}</p>
            <p class="mt-2 text-lg font-semibold text-sky-800">{{ roleLabels.owner }}</p>
          </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
          <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 p-5">
            <div>
              <h2 class="text-lg font-semibold">{{ fr ? 'Membres de l’espace' : 'Workspace members' }}</h2>
              <p class="mt-1 text-sm text-slate-500">{{ fr ? 'Annuaire des utilisateurs autorisés.' : 'Directory of authorized users.' }}</p>
            </div>
            <button type="button" :disabled="loading" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50 disabled:opacity-50" @click="load">{{ fr ? 'Rafraîchir' : 'Refresh' }}</button>
          </div>
          <div class="grid gap-3 border-b border-slate-200 bg-slate-50/70 p-5 sm:grid-cols-[minmax(0,1fr)_12rem]">
            <div>
              <label for="member-search" class="block text-sm font-medium">{{ fr ? 'Rechercher un membre' : 'Search members' }}</label>
              <input id="member-search" v-model="search" type="search" :placeholder="fr ? 'Adresse e-mail…' : 'Email address…'" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 focus-visible:outline-2 focus-visible:outline-sky-600">
            </div>
            <div>
              <label for="member-role" class="block text-sm font-medium">{{ fr ? 'Filtrer par rôle' : 'Filter by role' }}</label>
              <select id="member-role" v-model="roleFilter" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 focus-visible:outline-2 focus-visible:outline-sky-600">
                <option value="all">{{ fr ? 'Tous les rôles' : 'All roles' }}</option>
                <option v-for="role in roles" :key="role" :value="role">{{ roleLabels[role] }}</option>
              </select>
            </div>
          </div>
          <p v-if="loading" role="status" class="p-8 text-center text-slate-600">{{ fr ? 'Chargement des membres…' : 'Loading members…' }}</p>
          <div v-else-if="error" role="alert" class="p-8 text-center">
            <p class="font-medium text-red-800">{{ error }}</p>
            <button type="button" class="mt-3 rounded-lg border border-slate-300 px-4 py-2" @click="load">{{ fr ? 'Réessayer' : 'Try again' }}</button>
          </div>
          <div v-else-if="!visible.length" class="p-10 text-center">
            <p class="font-semibold text-slate-800">{{ fr ? 'Aucun membre trouvé' : 'No members found' }}</p>
            <p class="mt-2 text-sm text-slate-500">{{ fr ? 'Essayez de modifier les filtres.' : 'Try changing your filters.' }}</p>
          </div>
          <div v-else class="overflow-x-auto">
            <table class="w-full min-w-[32rem] text-left text-sm">
              <thead class="bg-slate-50 text-slate-600"><tr><th scope="col" class="px-5 py-3 font-semibold">{{ fr ? 'Utilisateur' : 'User' }}</th><th scope="col" class="px-5 py-3 font-semibold">{{ fr ? 'Autorisation' : 'Permission' }}</th></tr></thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="member in visible" :key="member.userId" class="hover:bg-slate-50">
                  <td class="px-5 py-4 font-medium text-slate-900">{{ member.email }}</td>
                  <td class="px-5 py-4"><span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" :class="member.role === 'owner' ? 'bg-sky-100 text-sky-900' : member.role === 'editor' ? 'bg-indigo-50 text-indigo-800' : 'bg-slate-100 text-slate-700'">{{ roleLabels[member.role] ?? member.role }}</span></td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-if="!loading && !error && filtered.length" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-4 text-sm text-slate-600">
            <p role="status">{{ filtered.length }} {{ fr ? 'résultat(s)' : 'result(s)' }}</p>
            <div v-if="totalPages > 1" class="flex items-center gap-3">
              <button type="button" :disabled="page === 1" class="rounded-lg border border-slate-300 px-3 py-2 disabled:opacity-40" @click="page--">{{ fr ? 'Précédent' : 'Previous' }}</button>
              <span>{{ page }} / {{ totalPages }}</span>
              <button type="button" :disabled="page === totalPages" class="rounded-lg border border-slate-300 px-3 py-2 disabled:opacity-40" @click="page++">{{ fr ? 'Suivant' : 'Next' }}</button>
            </div>
          </div>
        </div>
        <p class="rounded-lg border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-900">{{ fr ? 'Les autorisations sont actuellement consultables uniquement. La modification des membres sera activée après sécurisation des opérations.' : 'Permissions are currently read-only. Member changes will be enabled after securing write operations.' }}</p>
      </section>
    </template>
  </main>
</template>
