#!/usr/bin/env node
import { createReadStream } from 'node:fs';
import { mkdir, writeFile } from 'node:fs/promises';
import { createInterface } from 'node:readline';
import { join } from 'node:path';
import { parseArgs } from 'node:util';
import { Collector, buildModel, buildPages } from './lib.mjs';

const HELP = `Déduit un modèle UNS et des pages dashboard (page.schema.json) à partir de trames MQTT.

Usage : node infer.mjs [options]

  --url <mqtt://host:port>   Broker (défaut mqtt://127.0.0.1:1883)
  --topic <filtre>           Filtre d'abonnement (défaut "#"), ex. "acme/troyes/#"
  --listen <s>               Durée d'écoute (défaut 15). Les messages retained sont reçus aussitôt.
  --from-file <chemin|->     Lit des lignes "<topic> <payload>" (sortie de \`simulator.mjs --dry-run\`) au lieu du broker
  --root <a/b>               Préfixe à ignorer (entreprise/site) ; sinon déduit automatiquement
  --out <dossier>            Sortie (défaut ./out) : <area>.page.json + uns-model.json
  --stdout                   Affiche le modèle sur stdout au lieu d'écrire des fichiers
  -h, --help

Identifiants : MQTT_USERNAME / MQTT_PASSWORD. Lecture seule : l'outil ne publie jamais.
`;

const { values: o } = parseArgs({
  options: {
    url: { type: 'string', default: 'mqtt://127.0.0.1:1883' },
    topic: { type: 'string', default: '#' },
    listen: { type: 'string', default: '15' },
    'from-file': { type: 'string' },
    root: { type: 'string' },
    out: { type: 'string', default: './out' },
    stdout: { type: 'boolean', default: false },
    help: { type: 'boolean', short: 'h', default: false },
  },
});
if (o.help) { process.stdout.write(HELP); process.exit(0); }

const listen = Number(o.listen);
if (!Number.isFinite(listen) || listen < 1 || listen > 3600) { console.error('--listen invalide'); process.exit(2); }

const collector = new Collector();

if (o['from-file']) {
  const input = o['from-file'] === '-' ? process.stdin : createReadStream(o['from-file']);
  for await (const line of createInterface({ input })) {
    const i = line.indexOf(' ');
    if (i > 0) collector.addFrame(line.slice(0, i), line.slice(i + 1), Date.now());
  }
} else {
  const { default: mqtt } = await import('mqtt');
  const client = mqtt.connect(o.url, {
    clientId: `uns-infer-${process.pid}`,
    username: process.env.MQTT_USERNAME,
    password: process.env.MQTT_PASSWORD,
  });
  client.on('error', (e) => console.error(`Erreur MQTT : ${e.message}`));
  await new Promise((resolve) => {
    client.on('connect', () => {
      console.error(`Écoute de ${o.topic} sur ${o.url} pendant ${listen} s…`);
      client.subscribe(o.topic, { qos: 0 });
      setTimeout(resolve, listen * 1000);
    });
    client.on('message', (topic, payload) => collector.addFrame(topic, payload.toString('utf8').slice(0, 65536)));
  });
  await new Promise((r) => client.end(false, {}, r));
}

if (collector.tags.size === 0) { console.error('Aucune trame exploitable reçue.'); process.exit(1); }

const model = buildModel(collector, { root: o.root });
const pages = buildPages(model);
const tagCount = model.assets.reduce((n, a) => n + a.tags.length, 0);

if (o.stdout) {
  console.log(JSON.stringify({ model, pages }, null, 2));
} else {
  await mkdir(o.out, { recursive: true });
  await writeFile(join(o.out, 'uns-model.json'), JSON.stringify(model, null, 2) + '\n');
  for (const p of pages) await writeFile(join(o.out, `${p.id}.page.json`), JSON.stringify(p, null, 2) + '\n');
  console.error(`${model.frames} trames → ${model.assets.length} assets, ${tagCount} tags, ${pages.length} page(s) dans ${o.out}`);
}
