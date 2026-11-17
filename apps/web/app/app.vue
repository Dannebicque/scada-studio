<script setup lang="ts">
const { locale, t, setLocale } = useAppLocale()
const route = useRoute()
const isDocumentation = computed(() => route.path.startsWith('/docs'))
const fr = computed(() => locale.value === 'fr')
const links = [
  { to: '/', key: 'home' },
  { to: '/catalog', key: 'catalog' },
  { to: '/uns', key: 'uns' },
  { to: '/uns-discovery', key: 'unsDiscovery' },
  { to: '/uns-import', key: 'unsImport' },
  { to: '/dashboard', key: 'dashboard' },
  { to: '/connectors', key: 'connectors' },
  { to: '/synoptic', key: 'synoptic' },
  { to: '/docs', key: 'documentation' },
  { to: '/account', key: 'account' },
  { to: '/personal', key: 'personal' },
] as const
type LinkKey = (typeof links)[number]['key']
function label(key: LinkKey): string {
  switch (key) {
    case 'uns': return 'UNS'
    case 'unsDiscovery': return fr.value ? 'Découverte UNS' : 'UNS discovery'
    case 'unsImport': return fr.value ? 'Import UNS' : 'UNS import'
    case 'account': return fr.value ? 'Compte' : 'Account'
    case 'personal': return fr.value ? 'Mon espace' : 'My workspace'
    default: return t(key)
  }
}
const isActive = (to: string) => to === '/' ? route.path === '/' : route.path === to || route.path.startsWith(to + '/')

// Titre de document propre à chaque page (WCAG 2.4.2) ; annoncé par NuxtRouteAnnouncer à chaque navigation.
const pageTitle = computed(() => {
  if (route.path === '/access') return fr.value ? 'Gestion des accès' : 'Access management'
  const link = links.find(item => isActive(item.to))
  return link && link.to !== '/' ? label(link.key) : ''
})
useHead({
  htmlAttrs: { lang: locale },
  title: pageTitle,
  titleTemplate: title => title ? `${title} · SCADA Studio` : 'SCADA Studio',
})
</script>

<template>
  <div class="flex min-h-screen flex-col bg-slate-50 text-slate-900">
    <NuxtRouteAnnouncer />
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:rounded focus:bg-white focus:p-3">{{ t('skip') }}</a>
    <header class="border-b border-slate-200 bg-white">
      <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-5 py-4">
        <NuxtLink to="/" class="text-xl font-bold tracking-tight text-sky-900">SCADA <span class="text-sky-700">Studio</span></NuxtLink>
        <div class="flex items-center gap-3">
          <label for="app-language" class="text-sm font-medium text-slate-700">{{ t('language') }}</label>
          <select id="app-language" :value="locale" class="rounded-lg border bg-white px-3 py-2 text-sm" @change="setLocale(($event.target as HTMLSelectElement).value === 'en' ? 'en' : 'fr')">
            <option value="fr">Français</option>
            <option value="en">English</option>
          </select>
        </div>
      </div>
      <!-- Les liens passent à la ligne au lieu de défiler horizontalement : tout reste visible (reflow, WCAG 1.4.10). -->
      <nav :aria-label="t('navigation')" class="mx-auto max-w-7xl px-5 pb-3">
        <ul class="flex flex-wrap gap-1">
          <li v-for="link in links" :key="link.to">
            <NuxtLink :to="link.to" class="block whitespace-nowrap rounded-lg px-4 py-2 text-sm font-medium transition-colors hover:bg-sky-50 hover:text-sky-800" :class="isActive(link.to) ? 'bg-sky-100 text-sky-900' : 'text-slate-700'" :aria-current="isActive(link.to) ? 'page' : undefined">{{ label(link.key) }}</NuxtLink>
          </li>
        </ul>
      </nav>
    </header>
    <main id="main-content" tabindex="-1" class="mx-auto w-full max-w-7xl flex-1 px-5" :class="isDocumentation ? 'pt-4 pb-8' : 'py-8'">
      <NuxtPage />
    </main>
    <SystemStatusBar />
  </div>
</template>
