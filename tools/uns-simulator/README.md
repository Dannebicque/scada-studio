# Simulateur de trames MQTT / UNS

Outil de **développement local** : publie des trames réalistes sur un broker MQTT pour tester l'ingestion et, à terme, l'inférence automatique d'interface de supervision à partir de l'arborescence UNS. Il n'envoie aucune commande et n'a aucun lien avec du matériel réel.

## Démarrage rapide

```bash
cd tools/uns-simulator
npm install
npm run start:embedded      # broker local aedes (127.0.0.1:1883) + simulateur
```

Avec le broker Mosquitto du dépôt (`docker compose --env-file .env -f infra/compose.yaml up -d mqtt`) :

```bash
npm start
```

Sans broker : `node simulator.mjs --dry-run` affiche les trames sur stdout. `--list-topics` liste l'espace de noms.

## Profils

| Profil | Topic | Usage |
| --- | --- | --- |
| `uns` (défaut) | `{entreprise}/{site}/{area}/{ligne}/{cellule}/{tag}` (ISA-95) | Tester l'inférence d'UI : hiérarchie profonde, types variés |
| `scada` | `scada/{workspace}/{source}/{tag}` | Contrat actuel de `MqttMessageParser` (4 segments) |

Pour alimenter l'ingestion existante (catalogue démo `atelier-demo` / `automate-01`) :

```bash
node simulator.mjs --profile scada --config plant-demo.json --workspace atelier-demo
```

Dans le profil `scada`, la source est le champ `source` de l'asset, sinon le chemin aplati (`packaging-line1-filler`) : elle doit exister, être activée et de type `mqtt` côté API.

## Format des trames

Mode `tag` (défaut), un topic par tag :

```json
{"value":61.31,"observedAt":"2026-11-17T18:26:04.756Z","unit":"°C","quality":"good","valueType":"number"}
```

`value` et `observedAt` suivent le contrat de l'ingestion (`observedAt` strictement croissant par topic). `unit`, `quality` (`good|bad`) et `valueType` (`number|boolean|string`) sont des champs additionnels ignorés par l'ingestion actuelle. Mode `asset` (profil `uns`), un topic par équipement :

```json
{"observedAt":"…","tags":{"temperature":{"value":65.2,"unit":"°C","quality":"good"},"running":{"value":true,"quality":"good"}}}
```

Le profil `uns` publie aussi `{entreprise}/{site}/_status` (`online`, `offline` via LWT, retained).

## Options utiles

`--interval <ms>`, `--qos`, `--retain`, `--seed` (reproductible), `--duration <s>`, `--ws-port 9001` (WebSocket avec broker embarqué), `--url`. Identifiants : variables `MQTT_USERNAME` / `MQTT_PASSWORD`. `--faults` injecte des qualités `bad` et des silences (le tag n'est plus publié → valeur `stale` côté lecture), pour vérifier l'affichage des états dégradés.

## Modèle d'usine

`plant.json` décrit l'usine fictive (4 équipements, 19 tags). Formes de signaux : `sine`, `walk`, `counter` (nombres), `boolean`, `enum` (avec `weights`), `string`. Propriétés : `unit`, `min`, `max`, `base`, `amplitude`, `period`, `noise`, `step`, `decimals`, `toggleProb`, `changeProb`, `every` (publie un tick sur N). Les segments de topic sont limités à `[A-Za-z0-9_-]`.

## Tests

```bash
npm test
```

Le broker embarqué est anonyme et lié à `127.0.0.1` : ne pas l'exposer.
