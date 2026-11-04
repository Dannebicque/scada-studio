<script setup lang="ts">
const { locale } = useAppLocale()
const { data: account } = useAccount()
const { data: dashboards, status: dashboardsStatus } = usePersonalDashboards()
const { data: tags, status: tagsStatus } = usePersonalTags()
const { busy, error, createDashboard, deleteDashboard, saveTag } = usePersonalActions()
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
        <ul v-else class="mt-3 space-y-2"><li v-for="item in dashboards?.dashboards" :key="item.id" class="flex items-center justify-between gap-3 rounded border p-3"><span>{{ item.name }}</span><button type="button" :disabled="busy" class="text-red-800 underline disabled:opacity-50" @click="removeDashboard(item.id)">{{ locale === 'fr' ? 'Supprimer' : 'Delete' }}</button></li></ul>
        <form class="mt-5 flex flex-wrap items-end gap-3" @submit.prevent="addDashboard">
          <div><label for="personal-workspace" class="block text-sm">{{ locale === 'fr' ? 'Espace' : 'Workspace' }}</label><select id="personal-workspace" v-model="workspaceId" required class="rounded border p-2"><option disabled value="">{{ locale === 'fr' ? 'Choisir' : 'Choose' }}</option><option v-for="space in account.workspaces" :key="space.id" :value="space.id">{{ space.name }}</option></select></div>
          <div><label for="personal-name" class="block text-sm">{{ locale === 'fr' ? 'Nom du tableau' : 'Dashboard name' }}</label><input id="personal-name" v-model="name" required maxlength="120" class="rounded border p-2"></div>
          <button type="submit" :disabled="busy || !slug" class="rounded bg-sky-800 px-4 py-2 text-white disabled:opacity-50">{{ locale === 'fr' ? 'Créer' : 'Create' }}</button>
        </form>
      </section>
      <section class="rounded-xl border bg-white p-5">
        <h2 class="text-xl font-semibold">{{ locale === 'fr' ? 'Tags favoris' : 'Favorite tags' }}</h2>
        <p v-if="tagsStatus === 'pending'">Loading…</p>
        <ul v-else class="mt-3 divide-y"><li v-for="tag in tags?.tags" :key="tag.tagId" class="flex items-center justify-between gap-3 py-2"><span>{{ tag.displayName || tag.tagId }}</span><button type="button" :disabled="busy" :aria-pressed="tag.favorite" class="rounded border px-3 py-2 disabled:opacity-50" @click="toggleFavorite(tag)">{{ tag.favorite ? '★' : '☆' }} {{ locale === 'fr' ? 'Favori' : 'Favorite' }}</button></li></ul>
      </section>
    </template>
  </main>
</template>
