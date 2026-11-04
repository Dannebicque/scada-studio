<script setup lang="ts">
const { locale } = useAppLocale()
const { data: account } = useAccount()
const { data: dashboards, status: dashboardsStatus } = usePersonalDashboards()
const { data: tags, status: tagsStatus } = usePersonalTags()
const { busy, error, createDashboard, deleteDashboard, updateDashboard, saveTag } = usePersonalActions()
const { data: catalog } = useFetch<{ tags: { id: string; name: string }[] }>('/api/catalog', { server: false, retry: 0 })
const chosenTag = ref('')
const editingId = ref('')
const widgets = ref<{ type: string; tagId: string; title: string }[]>([])
function editWidgets(item: { id: string; widgets: { type: string; tagId: string; title: string }[] }) { editingId.value = item.id; widgets.value = item.widgets.map(widget => ({ ...widget })) }
function addWidget() { const tag = catalog.value?.tags.find(tag => tag.id === chosenTag.value); if (!tag || widgets.value.some(widget => widget.tagId === tag.id)) return; widgets.value.push({ type: 'value', tagId: tag.id, title: tag.name }); chosenTag.value = '' }
async function saveWidgets() { if (await updateDashboard(editingId.value, widgets.value)) editingId.value = '' }
const favoriteTagId = ref('')
async function addFavorite() { if (!favoriteTagId.value) return; if (await saveTag({ tagId: favoriteTagId.value, favorite: true, displayName: null })) favoriteTagId.value = '' }
const workspaceId = ref('')
const name = ref('')
const slug = computed(() => name.value.trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''))
async function addDashboard() {
  if (await createDashboard({ workspaceId: workspaceId.value, name: name.value, slug: slug.value, widgets: [] })) name.value = ''
}
async function removeDashboard(id: string) {
  if (window.confirm(locale.value === 'fr' ? 'Supprimer ce tableau de bord ?' : 'Delete this dashboard?')) await deleteDashboard(id)
}
async function toggleFavorite(tag: { tagId: string; displayName: string | null; favorite: boolean }) {
  await saveTag({ ...tag, favorite: !tag.favorite })
}
</script>
<template>
  <main class="mx-auto max-w-5xl space-y-6">
    <h1 class="text-3xl font-bold">{{ locale === 'fr' ? 'Mon espace' : 'My workspace' }}</h1>
    <NuxtLink v-if="!account?.authenticated" to="/account">{{ locale === 'fr' ? 'Se connecter' : 'Sign in' }}</NuxtLink>
    <template v-else>
      <p v-if="error" role="alert" class="text-red-700">{{ error }}</p>
      <section class="rounded-xl border bg-white p-5">
        <h2 class="text-xl font-semibold">{{ locale === 'fr' ? 'Mes tableaux de bord' : 'My dashboards' }}</h2>
        <p v-if="dashboardsStatus === 'pending'">Loading…</p>
        <ul v-else class="mt-3 space-y-2"><li v-for="item in dashboards?.dashboards" :key="item.id" class="flex items-center justify-between gap-3 rounded border p-3"><span>{{ item.name }}</span><button type="button" :disabled="busy" class="underline" @click="editWidgets(item)">{{ locale === 'fr' ? 'Widgets' : 'Widgets' }}</button><button type="button" :disabled="busy" class="text-red-800 underline disabled:opacity-50" @click="removeDashboard(item.id)">{{ locale === 'fr' ? 'Supprimer' : 'Delete' }}</button></li></ul>
        <form v-if="editingId" class="mt-4 space-y-3 rounded border p-3" @submit.prevent="saveWidgets">
          <h3 class="font-semibold">{{ locale === 'fr' ? 'Éditer les widgets' : 'Edit widgets' }}</h3>
          <ul><li v-for="(widget, index) in widgets" :key="widget.tagId" class="flex items-center gap-3 py-1"><span>{{ widget.title }}</span><button type="button" class="underline" @click="widgets.splice(index, 1)">{{ locale === 'fr' ? 'Retirer' : 'Remove' }}</button></li></ul>
          <label for="personal-widget-tag" class="block text-sm">{{ locale === 'fr' ? 'Ajouter un tag' : 'Add tag' }}</label>
          <select id="personal-widget-tag" v-model="chosenTag" class="rounded border p-2"><option value="">—</option><option v-for="tag in catalog?.tags" :key="tag.id" :value="tag.id">{{ tag.name }}</option></select>
          <button type="button" :disabled="!chosenTag" class="ml-2 rounded border px-3 py-2" @click="addWidget">{{ locale === 'fr' ? 'Ajouter' : 'Add' }}</button>
          <div class="flex gap-3"><button type="submit" :disabled="busy" class="rounded bg-sky-800 px-4 py-2 text-white">{{ locale === 'fr' ? 'Enregistrer' : 'Save' }}</button><button type="button" class="underline" @click="editingId = ''">{{ locale === 'fr' ? 'Annuler' : 'Cancel' }}</button></div>
        </form>
        <form class="mt-5 flex flex-wrap items-end gap-3" @submit.prevent="addDashboard">
          <div><label for="personal-workspace" class="block text-sm">{{ locale === 'fr' ? 'Espace' : 'Workspace' }}</label><select id="personal-workspace" v-model="workspaceId" required class="rounded border p-2"><option disabled value="">{{ locale === 'fr' ? 'Choisir' : 'Choose' }}</option><option v-for="space in account.workspaces" :key="space.id" :value="space.id">{{ space.name }}</option></select></div>
          <div><label for="personal-name" class="block text-sm">{{ locale === 'fr' ? 'Nom du tableau' : 'Dashboard name' }}</label><input id="personal-name" v-model="name" required maxlength="120" class="rounded border p-2"></div>
          <button type="submit" :disabled="busy || !slug" class="rounded bg-sky-800 px-4 py-2 text-white disabled:opacity-50">{{ locale === 'fr' ? 'Créer' : 'Create' }}</button>
        </form>
      </section>
      <section class="rounded-xl border bg-white p-5">
        <h2 class="text-xl font-semibold">{{ locale === 'fr' ? 'Tags favoris' : 'Favorite tags' }}</h2>
        <p v-if="tagsStatus === 'pending'">Loading…</p>
        <form class="mt-3 flex flex-wrap gap-3" @submit.prevent="addFavorite">
          <label for="personal-favorite-tag">{{ locale === 'fr' ? 'Ajouter un favori' : 'Add favorite' }}</label>
          <select id="personal-favorite-tag" v-model="favoriteTagId" required class="rounded border p-2"><option disabled value="">—</option><option v-for="tag in catalog?.tags" :key="tag.id" :value="tag.id">{{ tag.name }}</option></select>
          <button type="submit" :disabled="busy || !favoriteTagId" class="rounded bg-sky-800 px-3 py-2 text-white">{{ locale === 'fr' ? 'Ajouter' : 'Add' }}</button>
        </form>
        <ul v-if="tagsStatus !== 'pending'" class="mt-3 divide-y"><li v-for="tag in tags?.tags" :key="tag.tagId" class="flex items-center justify-between gap-3 py-2"><span>{{ tag.displayName || tag.tagId }}</span><button type="button" :disabled="busy" :aria-pressed="tag.favorite" class="rounded border px-3 py-2 disabled:opacity-50" @click="toggleFavorite(tag)">{{ tag.favorite ? '★' : '☆' }} {{ locale === 'fr' ? 'Favori' : 'Favorite' }}</button></li></ul>
      </section>
    </template>
  </main>
</template>
