<script setup lang="ts">
const { locale } = useAppLocale()
const { data: account, status: accountStatus } = useAccount()
const selectedWorkspace = ref('')
const query = ref('')
const workspaces = computed(() => account.value?.workspaces ?? [])
watch(workspaces, (items) => {
  if (!items.some(item => item.id === selectedWorkspace.value)) selectedWorkspace.value = items[0]?.id ?? ''
}, { immediate: true })
type UnsTag = { tagId: string; sourceId: string; path: string; name: string; valueType: string; unit: string | null }
type UnsAsset = { id: string; parentId: string | null; key: string; name: string; type: string }
const { data, status, error } = useFetch<{ workspaceId: string; projection: string; assets: UnsAsset[]; bindings: { assetId: string; tagId: string }[]; tags: UnsTag[] }>(
  () => selectedWorkspace.value ? '/api/workspaces/' + selectedWorkspace.value + '/uns' : '/api/me/uns-unselected',
  { server: false, retry: 0, watch: [selectedWorkspace] },
)
const assetNames = computed(() => new Map((data.value?.assets ?? []).map(asset => [asset.id, asset.name])))
const tagAssets = computed(() => {
  const result = new Map<string, string[]>()
  for (const binding of data.value?.bindings ?? []) result.set(binding.tagId, [...(result.get(binding.tagId) ?? []), assetNames.value.get(binding.assetId) ?? binding.assetId])
  return result
})
const filtered = computed(() => {
  const q = query.value.trim().toLocaleLowerCase()
  return (data.value?.tags ?? []).filter(tag => !q || (tag.path + ' ' + tag.name + ' ' + tag.valueType + ' ' + (tag.unit ?? '')).toLocaleLowerCase().includes(q))
})
</script>
<template>
  <main class="mx-auto max-w-6xl space-y-6">
    <header>
      <h1 class="text-3xl font-bold">Unified Namespace</h1>
      <p class="mt-2 text-slate-600">{{ locale === 'fr' ? 'Exploration en lecture seule des sources et tags accessibles.' : 'Read-only exploration of accessible sources and tags.' }}</p>
    </header>
    <p v-if="accountStatus === 'pending'" role="status">{{ locale === 'fr' ? 'Chargement…' : 'Loading…' }}</p>
    <NuxtLink v-else-if="!account?.authenticated" to="/account" class="underline">{{ locale === 'fr' ? 'Se connecter' : 'Sign in' }}</NuxtLink>
    <p v-else-if="!workspaces.length">{{ locale === 'fr' ? 'Aucun espace accessible.' : 'No accessible workspaces.' }}</p>
    <template v-else>
      <div class="flex flex-wrap gap-4">
        <div><label for="uns-workspace" class="block text-sm font-semibold">{{ locale === 'fr' ? 'Espace' : 'Workspace' }}</label><select id="uns-workspace" v-model="selectedWorkspace" class="rounded border p-2"><option v-for="item in workspaces" :key="item.id" :value="item.id">{{ item.name }}</option></select></div>
        <div class="grow"><label for="uns-query" class="block text-sm font-semibold">{{ locale === 'fr' ? 'Rechercher' : 'Search' }}</label><input id="uns-query" v-model="query" type="search" class="w-full rounded border p-2" :placeholder="locale === 'fr' ? 'Chemin, tag, type, unité…' : 'Path, tag, type, unit…'"></div>
      </div>
      <p v-if="status === 'pending'" role="status">{{ locale === 'fr' ? 'Chargement des tags…' : 'Loading tags…' }}</p>
      <p v-else-if="error" role="alert">{{ locale === 'fr' ? 'Namespace indisponible.' : 'Namespace unavailable.' }}</p>
      <p v-else-if="!filtered.length">{{ locale === 'fr' ? 'Aucun tag trouvé.' : 'No tags found.' }}</p>
      <section v-if="data?.assets.length" class="rounded-xl border bg-white p-4">
        <h2 class="mb-3 text-lg font-semibold">{{ locale === 'fr' ? 'Actifs industriels' : 'Industrial assets' }}</h2>
        <ul class="grid gap-2 sm:grid-cols-2"><li v-for="asset in data.assets" :key="asset.id" class="rounded border p-3"><strong>{{ asset.name }}</strong><span class="ml-2 text-sm text-slate-500">{{ asset.type }}</span><p class="text-xs text-slate-600">{{ asset.parentId ? (assetNames.get(asset.parentId) ?? asset.parentId) + ' / ' : '' }}{{ asset.key }}</p></li></ul>
      </section>
      <div v-if="filtered.length" class="overflow-x-auto rounded-xl border">
        <table class="w-full text-left text-sm">
          <thead class="bg-slate-100"><tr><th scope="col" class="p-3">{{ locale === 'fr' ? 'Chemin logique' : 'Logical path' }}</th><th scope="col" class="p-3">Tag</th><th scope="col" class="p-3">Type</th><th scope="col" class="p-3">{{ locale === 'fr' ? 'Actifs' : 'Assets' }}</th><th scope="col" class="p-3">{{ locale === 'fr' ? 'Unité' : 'Unit' }}</th></tr></thead>
          <tbody><tr v-for="tag in filtered" :key="tag.tagId" class="border-t"><td class="p-3 font-mono">{{ tag.path }}</td><td class="p-3">{{ tag.name }}</td><td class="p-3">{{ tag.valueType }}</td><td class="p-3">{{ tagAssets.get(tag.tagId)?.join(', ') ?? '—' }}</td><td class="p-3">{{ tag.unit ?? '—' }}</td></tr></tbody>
        </table>
      </div>
    </template>
  </main>
</template>
