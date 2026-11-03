<script setup lang="ts">
const { locale } = useAppLocale()
type Account = {
  authenticated: boolean
  user?: { id: string; email: string }
  workspaces: { id: string; name: string; role: string }[]
}
const { data, status, error, refresh } = await useFetch<Account>('/api/me', { server: false, retry: 0 })
const fr = computed(() => locale.value === 'fr')
</script>

<template>
  <main class="space-y-6">
    <header>
      <h1 class="text-3xl font-bold">{{ fr ? 'Mon compte' : 'My account' }}</h1>
      <p class="mt-2 text-slate-600">{{ fr ? 'Identité et accès aux espaces de supervision.' : 'Identity and monitoring workspace access.' }}</p>
    </header>
    <p v-if="status === 'pending'" role="status">{{ fr ? 'Chargement…' : 'Loading…' }}</p>
    <p v-else-if="error" role="alert">{{ fr ? 'Service de compte indisponible.' : 'Account service unavailable.' }}</p>
    <section v-else-if="!data?.authenticated" class="rounded-xl border border-amber-200 bg-amber-50 p-5">
      <h2 class="font-semibold">{{ fr ? 'Non connecté' : 'Not signed in' }}</h2>
      <p class="mt-2">{{ fr ? 'La connexion depuis l’interface sera activée après la configuration des sessions sécurisées.' : 'In-app login will be enabled after secure session setup.' }}</p>
    </section>
    <template v-else>
      <section class="rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="font-semibold">{{ fr ? 'Identité' : 'Identity' }}</h2>
        <p class="mt-2">{{ data.user?.email }}</p>
      </section>
      <section class="rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="mb-3 font-semibold">{{ fr ? 'Mes espaces' : 'My workspaces' }}</h2>
        <ul class="divide-y divide-slate-100">
          <li v-for="workspace in data.workspaces" :key="workspace.id" class="flex justify-between gap-4 py-3">
            <span>{{ workspace.name }}</span>
            <span class="text-sm text-slate-600">{{ workspace.role }}</span>
          </li>
        </ul>
      </section>
    </template>
    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm" @click="refresh()">{{ fr ? 'Actualiser' : 'Refresh' }}</button>
  </main>
</template>
