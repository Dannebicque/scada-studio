<script setup lang="ts">
const { locale } = useAppLocale()
const route = useRoute()
const pages = {
  fr: {
    index: () => import('~/../content/fr/index.md?raw'),
    'getting-started': () => import('~/../content/fr/getting-started.md?raw'),
    dashboard: () => import('~/../content/fr/dashboard.md?raw'),
    connectors: () => import('~/../content/fr/connectors.md?raw'),
  },
  en: {
    index: () => import('~/../content/en/index.md?raw'),
    'getting-started': () => import('~/../content/en/getting-started.md?raw'),
    dashboard: () => import('~/../content/en/dashboard.md?raw'),
    connectors: () => import('~/../content/en/connectors.md?raw'),
  },
} as const
type Lang = keyof typeof pages
type Slug = keyof typeof pages.fr
const lang = computed<Lang>(() => route.params.lang === 'en' ? 'en' : 'fr')
const slug = computed<Slug>(() => {
  const candidate = String(route.params.slug || 'index')
  return Object.hasOwn(pages.fr, candidate) ? candidate as Slug : 'index'
})
const raw = ref('')
watch([lang, slug], async () => {
  const module = await pages[lang.value][slug.value]()
  raw.value = module.default
}, { immediate: true })
const links = computed(() => [
  { slug: 'index', title: lang.value === 'fr' ? 'Introduction' : 'Introduction' },
  { slug: 'getting-started', title: lang.value === 'fr' ? 'Démarrage' : 'Getting started' },
  { slug: 'dashboard', title: lang.value === 'fr' ? 'Tableaux de bord' : 'Dashboards' },
  { slug: 'connectors', title: lang.value === 'fr' ? 'Connecteurs' : 'Connectors' },
])
type Block = { kind: 'h1' | 'h2' | 'p' | 'code' | 'li'; value: string }
const blocks = computed<Block[]>(() => {
  const source = raw.value.replace(/^---\n[\s\S]*?\n---\n/, '').trim()
  const result: Block[] = []
  let inCode = false
  let code = ''
  for (const line of source.split('\n')) {
    if (line.startsWith('```')) {
      if (inCode) { result.push({ kind: 'code', value: code.trimEnd() }); code = '' }
      inCode = !inCode
    } else if (inCode) {
      code += line + '\n'
    } else if (line.startsWith('# ')) {
      result.push({ kind: 'h1', value: line.slice(2) })
    } else if (line.startsWith('## ')) {
      result.push({ kind: 'h2', value: line.slice(3) })
    } else if (line.startsWith('- ')) {
      result.push({ kind: 'li', value: line.slice(2) })
    } else if (line.trim()) {
      result.push({ kind: 'p', value: line })
    }
  }
  return result
})
function linkParts(value: string) {
  return value.split(/(\[[^\]]+\]\([^)]*\))/g).filter(Boolean).map(part => {
    const match = /^\[([^\]]+)\]\(([^)]*)\)$/.exec(part)
    return match ? { label: match[1], href: match[2] ?? '' } : { label: part.replace(/\*\*/g, '').replace(/`/g, ''), href: '' }
  })
}
// The application header is the single language selector.
// Keep the current documentation page when switching languages.
watch(locale, value => {
  if (value !== lang.value) {
    void navigateTo('/docs/' + value + '/' + slug.value, { replace: true })
  }
})
</script>

<template>
  <div class="grid gap-8 lg:grid-cols-[220px_minmax(0,1fr)]">
    <nav :aria-label="lang === 'fr' ? 'Documentation' : 'Documentation'" class="rounded-xl border border-slate-200 bg-white p-4 lg:self-start">
      <h2 class="mb-3 font-semibold">{{ lang === 'fr' ? 'Documentation' : 'Documentation' }}</h2>
      <ul class="space-y-1">
        <li v-for="link in links" :key="link.slug">
          <NuxtLink :to="'/docs/' + lang + '/' + link.slug" :aria-current="slug === link.slug ? 'page' : undefined" class="block rounded-lg px-3 py-2 text-sm hover:bg-sky-50 focus-visible:outline-2 focus-visible:outline-sky-700" :class="slug === link.slug ? 'bg-sky-100 font-semibold text-sky-900' : 'text-slate-700'">{{ link.title }}</NuxtLink>
        </li>
      </ul>
    </nav>
    <article class="min-w-0 rounded-xl border border-slate-200 bg-white p-5 sm:p-8">
      <template v-for="(block, index) in blocks" :key="index">
        <h1 v-if="block.kind === 'h1'" class="mb-6 text-3xl font-bold">{{ block.value }}</h1>
        <h2 v-else-if="block.kind === 'h2'" class="mb-4 mt-8 text-xl font-semibold">{{ block.value }}</h2>
        <pre v-else-if="block.kind === 'code'" class="my-4 overflow-x-auto rounded-lg bg-slate-900 p-4 text-sm text-slate-50"><code>{{ block.value }}</code></pre>
        <p v-else :class="block.kind === 'li' ? 'mb-2 pl-4 before:mr-2 before:content-[\'•\']' : 'mb-4 leading-7'">
          <template v-for="(part, partIndex) in linkParts(block.value)" :key="partIndex">
            <NuxtLink v-if="part.href.startsWith('/docs/')" :to="part.href" class="text-sky-700 underline">{{ part.label }}</NuxtLink>
            <span v-else>{{ part.label }}</span>
          </template>
        </p>
      </template>
    </article>
  </div>
</template>
