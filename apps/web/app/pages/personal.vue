<script setup lang="ts">
const { locale } = useAppLocale()
const { data: account } = useAccount()
const { data: dashboards, status: dashboardsStatus } = usePersonalDashboards()
const { data: tags, status: tagsStatus } = usePersonalTags()
</script>
<template>
  <main class="mx-auto max-w-5xl space-y-6">
    <h1 class="text-3xl font-bold">{{ locale === 'fr' ? 'Mon espace' : 'My workspace' }}</h1>
    <NuxtLink v-if="!account?.authenticated" to="/account">{{ locale === 'fr' ? 'Se connecter' : 'Sign in' }}</NuxtLink>
    <template v-else>
      <section class="rounded-xl border bg-white p-5">
        <h2 class="text-xl font-semibold">{{ locale === 'fr' ? 'Mes tableaux de bord' : 'My dashboards' }}</h2>
        <p v-if="dashboardsStatus === 'pending'">Loading…</p>
        <ul v-else><li v-for="item in dashboards?.dashboards" :key="item.id">{{ item.name }}</li></ul>
      </section>
      <section class="rounded-xl border bg-white p-5">
        <h2 class="text-xl font-semibold">{{ locale === 'fr' ? 'Tags favoris' : 'Favorite tags' }}</h2>
        <p v-if="tagsStatus === 'pending'">Loading…</p>
        <ul v-else><li v-for="tag in tags?.tags.filter(t => t.favorite)" :key="tag.tagId">{{ tag.displayName || tag.tagId }}</li></ul>
      </section>
    </template>
  </main>
</template>
