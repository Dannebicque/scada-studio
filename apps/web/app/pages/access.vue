<script setup lang="ts">
const { locale } = useAppLocale()
const fr = computed(() => locale.value === 'fr')
type Account = { authenticated: boolean; workspaces: { id: string; name: string; role: string }[] }
type Member = { userId: string; email: string; role: string }
const { data: account } = await useFetch<Account>('/api/me', { server: false })
const selected = ref('')
const members = ref<Member[]>([])
const loading = ref(false)
const error = ref('')
const owned = computed(() => account.value?.workspaces.filter(w => w.role === 'owner') ?? [])
async function load() {
  members.value = []
  error.value = ''
  if (!selected.value) return
  loading.value = true
  try {
    const response = await $fetch<{ members: Member[] }>(`/api/workspaces/${selected.value}/members`)
    members.value = response.members
  } catch {
    error.value = fr.value ? 'Impossible de charger les membres.' : 'Unable to load members.'
  } finally {
    loading.value = false
  }
}
</script>
<template>
  <main class="space-y-5">
    <h1 class="text-2xl font-bold">{{ fr ? 'Gestion des accès' : 'Access management' }}</h1>
    <p v-if="!account?.authenticated">{{ fr ? 'Connexion requise.' : 'Sign in required.' }} <NuxtLink to="/account" class="underline">{{ fr ? 'Mon compte' : 'My account' }}</NuxtLink></p>
    <p v-else-if="!owned.length">{{ fr ? 'Vous ne gérez aucun espace.' : 'You do not own a workspace.' }}</p>
    <template v-else>
      <label for="access-workspace" class="block font-medium">{{ fr ? 'Espace de supervision' : 'Monitoring workspace' }}</label>
      <select id="access-workspace" v-model="selected" class="rounded-lg border border-slate-300 bg-white px-3 py-2" @change="load">
        <option value="">{{ fr ? 'Choisir un espace' : 'Choose a workspace' }}</option>
        <option v-for="workspace in owned" :key="workspace.id" :value="workspace.id">{{ workspace.name }}</option>
      </select>
      <p v-if="loading" role="status">{{ fr ? 'Chargement…' : 'Loading…' }}</p>
      <p v-else-if="error" role="alert" class="text-red-700">{{ error }}</p>
      <table v-else-if="selected" class="w-full text-left">
        <thead><tr><th scope="col" class="p-2">{{ fr ? 'Utilisateur' : 'User' }}</th><th scope="col" class="p-2">{{ fr ? 'Rôle' : 'Role' }}</th></tr></thead>
        <tbody><tr v-for="member in members" :key="member.userId" class="border-t border-slate-200"><td class="p-2">{{ member.email }}</td><td class="p-2">{{ member.role }}</td></tr></tbody>
      </table>
    </template>
  </main>
</template>
