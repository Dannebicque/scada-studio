#!/usr/bin/env node
import { readFile } from 'node:fs/promises';
import { parseArgs } from 'node:util';
import { fileURLToPath } from 'node:url';
import { Simulation } from './lib.mjs';

const HELP = `Simulateur de trames MQTT/UNS pour SCADA Studio (développement local uniquement)

Usage : node simulator.mjs [options]

  --url <mqtt://host:port>   Broker (défaut mqtt://127.0.0.1:1883)
  --config <fichier>         Modèle d'usine JSON (défaut plant.json)
  --profile <uns|scada>      uns : {entreprise}/{site}/{…asset}/{tag}
                             scada : scada/{workspace}/{source}/{tag} (ingestion actuelle)
  --workspace <slug>         Slug du workspace (profil scada, défaut atelier-demo)
  --mode <tag|asset>         tag : un topic par tag ; asset : un JSON par asset (uns)
  --interval <ms>            Période de publication (défaut 1000)
  --qos <0|1|2>              QoS (défaut 0)
  --retain                   Publier en retained (le dernier état est rejoué aux nouveaux abonnés)
  --faults                   Injecte des qualités "bad" et des silences (valeurs stale)
  --seed <n>                 Graine pseudo-aléatoire (défaut 42, reproductible)
  --duration <s>             S'arrête après N secondes (défaut : jusqu'à Ctrl+C)
  --dry-run                  Affiche les trames sur stdout sans broker
  --list-topics              Liste les topics et quitte
  --embedded-broker          Démarre un broker local (aedes) sur 127.0.0.1
  --broker-port <n>          Port TCP du broker embarqué (défaut 1883)
  --ws-port <n>              Active aussi un port WebSocket MQTT (ex. 9001)
  -h, --help

Authentification : variables MQTT_USERNAME / MQTT_PASSWORD (jamais en argument).
`;

const { values: o } = parseArgs({
  options: {
    url: { type: 'string', default: 'mqtt://127.0.0.1:1883' },
    config: { type: 'string', default: fileURLToPath(new URL('./plant.json', import.meta.url)) },
    profile: { type: 'string', default: 'uns' },
    workspace: { type: 'string', default: 'atelier-demo' },
    mode: { type: 'string', default: 'tag' },
    interval: { type: 'string', default: '1000' },
    qos: { type: 'string', default: '0' },
    retain: { type: 'boolean', default: false },
    faults: { type: 'boolean', default: false },
    seed: { type: 'string', default: '42' },
    duration: { type: 'string' },
    'dry-run': { type: 'boolean', default: false },
    'list-topics': { type: 'boolean', default: false },
    'embedded-broker': { type: 'boolean', default: false },
    'broker-port': { type: 'string', default: '1883' },
    'ws-port': { type: 'string' },
    help: { type: 'boolean', short: 'h', default: false },
  },
});

if (o.help) { process.stdout.write(HELP); process.exit(0); }

const intOpt = (name, v, min, max) => {
  const n = Number(v);
  if (!Number.isInteger(n) || n < min || n > max) { console.error(`--${name} invalide : ${v}`); process.exit(2); }
  return n;
};
const interval = intOpt('interval', o.interval, 50, 3_600_000);
const qos = intOpt('qos', o.qos, 0, 2);
const seed = intOpt('seed', o.seed, 0, 2 ** 31);

let sim;
try {
  const plant = JSON.parse(await readFile(o.config, 'utf8'));
  sim = new Simulation(plant, { profile: o.profile, workspace: o.workspace, mode: o.mode, faults: o.faults, seed });
} catch (e) {
  console.error(e.message);
  process.exit(2);
}

if (o['list-topics']) { console.log(sim.topics().join('\n')); process.exit(0); }

const stops = [];
const shutdown = async (code = 0) => {
  for (const stop of stops.reverse()) { try { await stop(); } catch { /* arrêt best-effort */ } }
  process.exit(code);
};
process.on('SIGINT', () => shutdown(0));
process.on('SIGTERM', () => shutdown(0));

let publish;
if (o['dry-run']) {
  publish = (topic, payload) => console.log(`${topic} ${payload}`);
} else {
  if (o['embedded-broker']) {
    const { startEmbeddedBroker } = await import('./embedded-broker.mjs');
    const port = intOpt('broker-port', o['broker-port'], 1, 65535);
    const wsPort = o['ws-port'] ? intOpt('ws-port', o['ws-port'], 1, 65535) : undefined;
    stops.push(await startEmbeddedBroker({ port, wsPort }));
  }
  const { default: mqtt } = await import('mqtt');
  const statusTopic = sim.statusTopic();
  const client = mqtt.connect(o.url, {
    clientId: `uns-simulator-${process.pid}`,
    username: process.env.MQTT_USERNAME,
    password: process.env.MQTT_PASSWORD,
    reconnectPeriod: 2000,
    will: statusTopic ? { topic: statusTopic, payload: 'offline', qos: 1, retain: true } : undefined,
  });
  client.on('connect', () => {
    console.error(`Connecté à ${o.url}`);
    if (statusTopic) client.publish(statusTopic, 'online', { qos: 1, retain: true });
  });
  client.on('reconnect', () => console.error('Reconnexion…'));
  client.on('error', (e) => console.error(`Erreur MQTT : ${e.message}`));
  stops.push(() => new Promise((resolve) => {
    if (statusTopic && client.connected) client.publish(statusTopic, 'offline', { qos: 1, retain: true });
    client.end(false, {}, resolve);
  }));
  publish = (topic, payload) => {
    if (client.connected) client.publish(topic, payload, { qos, retain: o.retain });
  };
}

console.error(`Profil ${o.profile}, mode ${o.mode}, ${sim.topics().length} topics, période ${interval} ms. Ctrl+C pour arrêter.`);
const timer = setInterval(() => {
  for (const m of sim.step(new Date(), interval / 1000)) publish(m.topic, m.payload);
}, interval);
stops.push(() => clearInterval(timer));
if (o.duration) setTimeout(() => shutdown(0), intOpt('duration', o.duration, 1, 86_400) * 1000);
