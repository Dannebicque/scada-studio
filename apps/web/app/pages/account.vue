<script setup lang="ts">
const { locale } = useAppLocale()
type Account = {
  authenticated: boolean
  user?: { id: string; email: string }
  workspaces: { id: string; name: string; role: string }[]
}
const { data, status, error, refresh } = await useFetch<Account>('/api/me', { server: false, retry: 0 })
const fr = computed(() => locale.value === 'fr')
const email = ref('')
const password = ref('')
const submitting = ref(false)
const authError = ref('')
async function login() {
  submitting.value = true
  authError.value = ''
  try {
    await $fetch('/api/auth/login', { method: 'POST', body: { email: email.value, password: password.value } })
    password.value = ''
    await refresh()
  } catch {
    authError.value = fr.value ? 'Connexion impossible. Vérifiez vos identifiants.' : 'Sign-in failed. Check your credentials.'
  } finally {
    submitting.value = false
  }
}
async function logout() {
  submitting.value = true
  try {
    await $fetch('/api/auth/logout', { method: 'POST' })
    await refresh()
  } catch {
    authError.value = fr.value ? 'Déconnexion impossible.' : 'Sign-out failed.'
  } finally {
    submitting.value = false
  }
}
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
      <form class="mt-4 max-w-sm space-y-4" @submit.prevent="login">
        <div>
          <label for="account-email" class="block text-sm font-medium">{{ fr ? 'Adresse e-mail' : 'Email address' }}</label>
          <input id="account-email" v-model.trim="email" type="email" autocomplete="username" required class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
        </div>
        <div>
          <label for="account-password" class="block text-sm font-medium">{{ fr ? 'Mot de passe' : 'Password' }}</label>
          <input id="account-password" v-model="password" type="password" autocomplete="current-password" required class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
        </div>
        <p v-if="authError" role="alert" class="text-sm text-red-700">{{ authError }}</p>
        <button type="submit" :disabled="submitting" class="rounded-lg bg-sky-800 px-4 py-2 font-medium text-white disabled:opacity-50">{{ fr ? 'Se connecter' : 'Sign in' }}</button>
      </form>
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
      <NuxtLink to="/access" class="inline-block rounded-lg bg-sky-800 px-4 py-2 text-white">{{ fr ? "Gestion des accès" : "Access management" }}</NuxtLink>
      <button type="button" :disabled="submitting" class="rounded-lg border border-slate-300 px-4 py-2" @click="logout">{{ fr ? 'Se déconnecter' : 'Sign out' }}</button>
    </template>
    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm" @click="refresh()">{{ fr ? 'Actualiser' : 'Refresh' }}</button>
  </main>
</template>
