<script setup lang="ts">
type Catalog = {
  workspaces: { id: string; name: string; slug: string }[]
  sources: { id: string; name: string; connectorType: string; enabled: boolean }[]
  tags: { id: string; name: string; key: string; valueType: string; readOnly: boolean; unit: string | null }[]
}
const { data, status, error, refresh } = await useFetch<Catalog>('/api/catalog', { server: false, retry: 0 })
const { data: telemetry, status: telemetryStatus, error: telemetryError, refresh: refreshTelemetry } = await useFetch<{ values: { tagId: string; value: number | string | boolean; observedAt: string }[] }>('/api/tag-values', { server: false, retry: 0 })
const valueByTag = computed(() => new Map((telemetry.value?.values ?? []).map(item => [item.tagId, item])))
function refreshAll() { refresh(); refreshTelemetry() }
</script>

<template>
  <main class="mx-auto max-w-5xl p-8">
    <NuxtLink to="/" class="underline">Retour à l'accueil</NuxtLink>
    <h1 class="mt-5 text-3xl font-bold">Catalogue de supervision</h1>
    <p class="mt-2">Espaces, sources et points de mesure en lecture seule.</p>
    <button type="button" class="mt-4 rounded border px-3 py-2" :disabled="status === 'pending'" @click="refreshAll()">Actualiser</button>
    <p v-if="status === 'pending' || status === 'idle'" role="status">Chargement…</p>
    <p v-else-if="error" role="alert">Catalogue indisponible. Vérifiez Symfony et PostgreSQL.</p>
    <template v-else-if="data">
      <section aria-labelledby="workspaces-heading" class="mt-8">
        <h2 id="workspaces-heading" class="text-xl font-semibold">Espaces ({{ data.workspaces.length }})</h2>
        <ul><li v-for="item in data.workspaces" :key="item.id">{{ item.name }} — {{ item.slug }}</li></ul>
      </section>
      <section aria-labelledby="sources-heading" class="mt-8">
        <h2 id="sources-heading" class="text-xl font-semibold">Sources ({{ data.sources.length }})</h2>
        <ul><li v-for="item in data.sources" :key="item.id">{{ item.name }} — {{ item.connectorType }} ({{ item.enabled ? 'active' : 'inactive' }})</li></ul>
      </section>
      <p v-if="telemetryError" role="alert" class="mt-4">Valeurs de télémétrie indisponibles.</p>
      <p v-else-if="telemetryStatus === 'pending'" role="status" class="mt-4">Chargement des mesures…</p>
      <section aria-labelledby="tags-heading" class="mt-8">
        <h2 id="tags-heading" class="text-xl font-semibold">Tags ({{ data.tags.length }})</h2>
        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead><tr><th scope="col">Nom</th><th scope="col">Clé</th><th scope="col">Type</th><th scope="col">Unité</th><th scope="col">Accès</th><th scope="col">Dernière valeur</th><th scope="col">Horodatage</th></tr></thead>
            <tbody><tr v-for="item in data.tags" :key="item.id"><td>{{ item.name }}</td><td>{{ item.key }}</td><td>{{ item.valueType }}</td><td>{{ item.unit ?? '—' }}</td><td>{{ item.readOnly ? 'Lecture seule' : 'Écriture déclarée' }}</td><td>{{ valueByTag.get(item.id)?.value ?? '—' }}</td><td>{{ valueByTag.get(item.id)?.observedAt ? new Date(valueByTag.get(item.id)!.observedAt).toLocaleString('fr-FR') : '—' }}</td></tr></tbody>
          </table>
        </div>
      </section>
    </template>
  </main>
</template>
