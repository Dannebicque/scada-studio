#!/usr/bin/env node
// Lance tout l'environnement de développement local en une commande :
// infra Docker (PostgreSQL + Mosquitto) → migrations → API, web, consommateur MQTT, simulateur.
// Développement local uniquement : broker anonyme lié à 127.0.0.1, aucun secret en argument.
import { spawn, spawnSync } from 'node:child_process'
import { existsSync, copyFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const args = new Set(process.argv.slice(2))

if (args.has('--help') || args.has('-h')) {
  console.log(`Usage: node scripts/dev.mjs [options]   (ou: make dev)

  --uns           Simulateur en profil UNS (topics ISA-95 profonds) au lieu du profil
                  « scada » qui alimente directement le catalogue démo
  --embedded      Broker MQTT embarqué (aedes) au lieu du conteneur Mosquitto
  --no-docker     Ne pas démarrer Docker (PostgreSQL et le broker sont déjà disponibles)
  --no-migrate    Ne pas exécuter les migrations
  --no-consumer   Ne pas lancer app:mqtt:consume
  --no-simulator  Ne pas lancer le simulateur
  --check         Vérifier les prérequis puis quitter

Ctrl+C arrête tous les processus. L'infrastructure Docker reste active (make down pour l'arrêter).`)
  process.exit(0)
}

const useUns = args.has('--uns')
const embedded = args.has('--embedded')

const colors = { api: 36, web: 35, mqtt: 33, sim: 32, infra: 90 }
const tag = (name) => `\x1b[${colors[name] ?? 37}m${name.padEnd(5)}\x1b[0m │ `
const info = (name, line) => console.log(tag(name) + line)

function has(cmd) {
  return spawnSync('sh', ['-c', `command -v ${cmd}`], { stdio: 'ignore' }).status === 0
}

function run(name, cmd, cmdArgs, cwd = root) {
  info(name, `$ ${cmd} ${cmdArgs.join(' ')}`)
  const r = spawnSync(cmd, cmdArgs, { cwd, stdio: 'inherit' })
  if (r.status !== 0) {
    console.error(`\nÉchec : ${cmd} ${cmdArgs.join(' ')} (code ${r.status ?? r.signal})`)
    process.exit(1)
  }
}

// --- Prérequis -----------------------------------------------------------------------------------
const missing = []
const need = (cmd, why) => { if (!has(cmd)) missing.push(`${cmd} — ${why}`) }
need('node', 'Node.js ≥ 20')
need('npm', 'installation des dépendances web')
need('php', 'API Symfony')
if (!args.has('--no-docker')) need('docker', 'PostgreSQL et Mosquitto (ou utilisez --no-docker)')
if (!args.has('--no-consumer')) need('mosquitto_sub', 'client MQTT du consommateur (paquet mosquitto-clients)')
if (missing.length) {
  console.error('Prérequis manquants :\n' + missing.map((m) => `  - ${m}`).join('\n'))
  process.exit(1)
}
if (!existsSync(join(root, 'apps/api/vendor'))) missing.push('apps/api/vendor — lancez make install')
if (!existsSync(join(root, 'node_modules'))) missing.push('node_modules — lancez make install')
if (!existsSync(join(root, 'apps/api/.env.local')))
  missing.push('apps/api/.env.local — lancez make setup (DATABASE_URL local)')
if (missing.length) {
  console.error('À préparer d\'abord :\n' + missing.map((m) => `  - ${m}`).join('\n'))
  process.exit(1)
}
if (args.has('--check')) {
  console.log('Prérequis OK.')
  process.exit(0)
}

if (!existsSync(join(root, '.env'))) {
  copyFileSync(join(root, '.env.example'), join(root, '.env'))
  info('infra', '.env créé depuis .env.example')
}

// Le simulateur n'est pas un workspace npm : ses dépendances sont installées à part.
const simDir = join(root, 'tools/uns-simulator')
if (!args.has('--no-simulator') && !existsSync(join(simDir, 'node_modules'))) {
  run('sim', 'npm', ['install', '--no-audit', '--no-fund'], simDir)
}

// --- Infrastructure ------------------------------------------------------------------------------
if (!args.has('--no-docker')) {
  const services = embedded ? ['postgres'] : ['postgres', 'mqtt']
  run('infra', 'docker', ['compose', '--env-file', '.env', '-f', 'infra/compose.yaml', 'up', '-d', '--wait', ...services])
}
if (!args.has('--no-migrate')) {
  run('api', 'php', ['bin/console', 'doctrine:migrations:migrate', '--no-interaction'], join(root, 'apps/api'))
}

// --- Processus -----------------------------------------------------------------------------------
const children = []
let stopping = false

function start(name, cmd, cmdArgs, { cwd = root, critical = true, env = {} } = {}) {
  const child = spawn(cmd, cmdArgs, {
    cwd,
    env: { ...process.env, ...env },
    stdio: ['ignore', 'pipe', 'pipe'],
    detached: true, // groupe de processus dédié : on arrête aussi php -S, nuxt, etc.
  })
  const pipe = (stream) => {
    let buffer = ''
    stream.on('data', (chunk) => {
      buffer += chunk
      const lines = buffer.split('\n')
      buffer = lines.pop() ?? ''
      for (const line of lines) if (line.trim() !== '') info(name, line)
    })
  }
  pipe(child.stdout)
  pipe(child.stderr)
  child.on('error', (e) => info(name, `erreur de lancement : ${e.message}`))
  child.on('exit', (code, signal) => {
    if (stopping) return
    info(name, `arrêté (${signal ?? `code ${code}`})`)
    if (critical) shutdown(1)
  })
  children.push(child)
}

function shutdown(code = 0) {
  if (stopping) return
  stopping = true
  console.log('\nArrêt des processus…')
  for (const child of children) {
    try { process.kill(-child.pid, 'SIGTERM') } catch { /* déjà arrêté */ }
  }
  const force = setTimeout(() => {
    for (const child of children) {
      try { process.kill(-child.pid, 'SIGKILL') } catch { /* déjà arrêté */ }
    }
    process.exit(code)
  }, 4000)
  force.unref()
  let remaining = children.filter((c) => c.exitCode === null && c.signalCode === null).length
  if (remaining === 0) process.exit(code)
  for (const child of children) {
    if (child.exitCode === null && child.signalCode === null) {
      child.once('exit', () => { if (--remaining === 0) process.exit(code) })
    }
  }
}
process.on('SIGINT', () => shutdown(0))
process.on('SIGTERM', () => shutdown(0))

// Plusieurs workers : la découverte UNS dure jusqu'à 30 s et ne doit pas bloquer les autres requêtes.
start('api', 'php', ['-S', '127.0.0.1:8000', '-t', 'public', 'public/index.php'], { cwd: join(root, 'apps/api'), env: { PHP_CLI_SERVER_WORKERS: '4' } })
start('web', 'npm', ['run', 'dev:web'])
if (!args.has('--no-consumer')) {
  start('mqtt', 'php', ['bin/console', 'app:mqtt:consume'], { cwd: join(root, 'apps/api'), critical: false })
}
if (!args.has('--no-simulator')) {
  const simArgs = ['simulator.mjs']
  if (embedded) simArgs.push('--embedded-broker')
  if (!useUns) simArgs.push('--profile', 'scada', '--config', 'plant-demo.json', '--workspace', 'atelier-demo')
  start('sim', 'node', simArgs, { cwd: simDir, critical: false })
}

console.log(`
  Web        http://localhost:3000
  API        http://127.0.0.1:8000
  MQTT       mqtt://127.0.0.1:1883 (${embedded ? 'broker embarqué' : 'Mosquitto'})
  Simulateur profil ${useUns ? 'uns (voir docs/uns-inference.md pour l\'import)' : 'scada (workspace atelier-demo)'}

  Ctrl+C pour tout arrêter.
`)
