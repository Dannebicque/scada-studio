import { readdirSync, readFileSync } from 'node:fs'
import { join } from 'node:path'

const root = new URL('../apps/web/content/', import.meta.url).pathname
const fr = readdirSync(join(root, 'fr')).filter(name => name.endsWith('.md')).sort()
const en = readdirSync(join(root, 'en')).filter(name => name.endsWith('.md')).sort()
const errors = []
if (JSON.stringify(fr) !== JSON.stringify(en)) errors.push('FR and EN document slugs differ')
for (const locale of ['fr', 'en']) {
  for (const name of readdirSync(join(root, locale)).filter(name => name.endsWith('.md'))) {
    const body = readFileSync(join(root, locale, name), 'utf8')
    if (!body.startsWith('---')) errors.push(locale + '/' + name + ': missing frontmatter')
    if (!body.includes('title:')) errors.push(locale + '/' + name + ': missing title')
    if (!body.includes('description:')) errors.push(locale + '/' + name + ': missing description')
    for (const slug of ['index', 'getting-started', 'dashboard', 'connectors']) {
      if (body.includes('/docs/' + locale + '/' + slug) && !readdirSync(join(root, locale)).includes(slug + '.md')) errors.push('Broken link: ' + slug)
    }
  }
}
if (errors.length) {
  console.error(errors.join('\n'))
  process.exitCode = 1
} else {
  console.log('Bilingual docs validated: ' + fr.length + ' pages per language')
}
