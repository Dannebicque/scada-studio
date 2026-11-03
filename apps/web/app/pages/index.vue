<script setup lang="ts">
type Health = { status: 'ok'; service: string; timestamp: string }

const { data, status, error, refresh } = await useFetch<Health>('/api/health', {
  server: false,
  retry: 0,
})
const isHealthy = computed(() => status.value === 'success' && data.value?.status === 'ok')
</script>

<template>
  <main class="mx-auto max-w-4xl p-8">
    <h1 class="text-3xl font-bold">SCADA Studio</h1>
    <p class="mt-4">Socle Nuxt 4 — prototype en cours de construction.</p>

    <section aria-labelledby="api-health-heading" class="mt-6 rounded-lg border p-4">
      <h2 id="api-health-heading" class="text-lg font-semibold">État du backend</h2>
      <p role="status" aria-live="polite" class="mt-2">
        <template v-if="status === 'pending' || status === 'idle'">Vérification de l'API…</template>
        <template v-else-if="isHealthy">API opérationnelle ({{ data?.service }})</template>
        <template v-else>API indisponible{{ error ? ' : connexion impossible ou réponse invalide' : '' }}</template>
      </p>
      <button type="button" class="mt-3 rounded border px-3 py-2 hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-offset-2" :disabled="status === 'pending'" @click="refresh()">
        Actualiser
      </button>
    </section>

    <nav aria-label="Navigation principale" class="mt-6 flex gap-4">
      <NuxtLink to="/dashboard">Dashboard</NuxtLink>
      <NuxtLink to="/catalog">Catalogue</NuxtLink>
      <NuxtLink to="/synoptic">Synoptique</NuxtLink>
    </nav>
  </main>
</template>
