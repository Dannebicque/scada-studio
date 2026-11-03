<script setup lang="ts">
const { locale, t, setLocale } = useAppLocale()
const route = useRoute()
const isDocumentation = computed(() => route.path.startsWith('/docs'))
const links = [
  { to: '/', key: 'home' },
  { to: '/catalog', key: 'catalog' },
  { to: '/dashboard', key: 'dashboard' },
  { to: '/connectors', key: 'connectors' },
  { to: '/synoptic', key: 'synoptic' },
  { to: '/docs', key: 'documentation' },
  { to: '/account', key: 'account' },
] as const
</script>

<template>
  <div class="flex min-h-screen flex-col bg-slate-50 text-slate-900">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:rounded focus:bg-white focus:p-3">{{ t('skip') }}</a>
    <header class="border-b border-slate-200 bg-white">
      <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-5 py-4">
        <NuxtLink to="/" class="text-xl font-bold tracking-tight text-sky-900">SCADA <span class="text-sky-600">Studio</span></NuxtLink>
        <div class="flex items-center gap-3">
          <label for="app-language" class="text-sm font-medium text-slate-600">{{ t('language') }}</label>
          <select id="app-language" :value="locale" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600" @change="setLocale(($event.target as HTMLSelectElement).value === 'en' ? 'en' : 'fr')">
            <option value="fr">Français</option>
            <option value="en">English</option>
          </select>
        </div>
      </div>
      <nav :aria-label="t('navigation')" class="mx-auto flex max-w-7xl gap-1 overflow-x-auto px-5 pb-3">
        <NuxtLink v-for="link in links" :key="link.to" :to="link.to" class="whitespace-nowrap rounded-lg px-4 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-sky-50 hover:text-sky-800 focus-visible:outline-2 focus-visible:outline-sky-600" active-class="bg-sky-100 !text-sky-900" :exact-active-class="link.to === '/' ? 'bg-sky-100 !text-sky-900' : undefined">{{ link.key === 'account' ? (locale === 'fr' ? 'Compte' : 'Account') : t(link.key) }}</NuxtLink>
      </nav>
    </header>
    <div id="main-content" class="mx-auto w-full max-w-7xl flex-1 px-5" :class="isDocumentation ? 'pt-4 pb-8' : 'py-8'">
      <NuxtPage />
    </div>
    <SystemStatusBar />
  </div>
</template>
