# uns-infer : du flux MQTT/UNS à un dashboard

Écoute des trames MQTT (ou lit un fichier), reconstruit l'arbre UNS, **apprend** les caractéristiques de chaque tag (type, unité, plage, cadence, compteur ou non) et génère des pages dashboard conformes à `packages/contracts/schemas/page.schema.json`. Lecture seule : l'outil ne publie jamais. Les pages sont des **propositions à relire** avant publication, conformément au principe « validation humaine » du projet.

## Utilisation

```bash
# terminal 1 : trames de test
cd tools/uns-simulator && npm install && npm run start:embedded

# terminal 2 : inférence
cd tools/uns-infer && npm install
node infer.mjs --topic 'acme/troyes/#' --listen 15 --out out
```

Sans broker : `node ../uns-simulator/simulator.mjs --dry-run --duration 20 | node infer.mjs --from-file - --stdout`.

Sortie dans `out/` : `uns-model.json` (assets, tags, stats apprises) et une page `inferred-<area>.page.json` par area.

## Formats de trames compris

- Un topic par tag : payload `{"value":…,"observedAt":…,"unit":…,"quality":…}` ou valeur brute (`21.5`, `true`, `RUNNING`).
- Un topic par équipement : payload `{"tags":{"nom":{"value":…,"unit":…}}}`.
- Les segments commençant par `_` ou `$` (`_status`) sont ignorés.

Le préfixe commun (entreprise/site) est déduit automatiquement, ou fixé avec `--root acme/troyes`. Le premier segment restant devient une page ; les suivants, les sections.

## Règles d'inférence

| Observation | Widget proposé |
| --- | --- |
| booléen (nom évoquant alarm/fault/trip…) | `core.alarm` ; sinon `core.status` |
| chaîne à ≤ 12 valeurs distinctes | `core.state` (liste des valeurs) |
| nombre monotone croissant (≥ 3 hausses, aucune baisse) | `core.value` (`trend: counter`) |
| nombre constant | `core.value` |
| nombre en `%` | `core.gauge` (0–100) |
| autre nombre | `core.timeseries` |

La période médiane entre messages donne `staleAfterSeconds` = 3 × période (minimum 5 s). Les valeurs de qualité `bad` sont exclues des plages. Chaque élément porte `bindings.value` (chemin pointé sous la racine, ex. `packaging.line1.filler.temperature`) et `properties.topic` (topic source). Les ids de widgets `core.*` sont **proposés** : le registre de widgets n'existe pas encore côté web.

## Limites connues

- Détection de compteur heuristique : une grandeur qui monte lentement sur une fenêtre courte peut être classée compteur. Allonger `--listen` ou renommer le tag aide.
- Plages et unités ne viennent que des trames (aucune métadonnée Sparkplug/UNS dédiée pour l'instant).
- Les seuils d'alarme et les libellés ne sont pas inférés ; les libellés sont dérivés des noms de topic.
- Une écoute courte ne voit pas les tags publiés rarement : relancer avec un `--listen` plus long ou lire les messages retained.

## Tests

```bash
npm test   # inclut la validation contre page.schema.json et un test sur les trames du simulateur
```
