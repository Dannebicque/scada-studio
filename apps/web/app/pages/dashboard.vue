<script setup lang="ts">
const { t } = useAppLocale()
</script>
<template>
  <main class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
    <p class="text-sm font-semibold uppercase tracking-widest text-sky-700">L3</p>
    <h1 class="mt-2 text-3xl font-bold">{{ t('dashboard') }}</h1>
    <p class="mt-3 text-slate-600">{{ t('dashboardIntro') }}</p>
  </main>
</template>
