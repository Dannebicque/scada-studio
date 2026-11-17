# Inférence d'interface depuis des trames MQTT/UNS

Chaîne de bout en bout, lecture seule, avec validation humaine avant toute sauvegarde.

```text
MQTT/UNS ──► tools/uns-infer ──► page.json (page.schema.json) ──► /uns-import ──► dashboard personnel
 (ou tools/uns-simulator)         propositions core.*              aperçu + revue     widgets value/gauge/status/chart
```

## Étapes

1. **Trames de test** : `tools/uns-simulator` (profils `uns` ISA-95 profond, ou `scada` = contrat de `MqttMessageParser`).
2. **Inférence** : `tools/uns-infer` écoute le broker (ou un fichier), apprend type, unité, plage, cadence et comportement de compteur de chaque tag, puis génère `uns-model.json` et une page par area.
3. **Import** : la page web `/uns-import` charge le JSON, le valide (1 Mo, 300 éléments, `schemaVersion: 1`, `kind: dashboard`), l'affiche sans rien exécuter, associe chaque élément à un tag du catalogue et crée un dashboard personnel après confirmation. Le tableau créé est affiché sur `/dashboard` (liste « Mes tableaux », ou lien « Afficher » de Mon espace) avec un rendu par type : valeur, jauge (`%`), voyant d'état ; le type `chart` affiche la dernière valeur en attendant l'historique.

## Correspondance des widgets

| Proposé par l'inférence | Widget personnel (API) |
| --- | --- |
| `core.value` | `value` |
| `core.gauge` | `gauge` |
| `core.status`, `core.alarm`, `core.state` | `status` |
| `core.timeseries` | `chart` |
| `core.section` | titre de groupe (aperçu uniquement) |

Les identifiants `core.*` sont des propositions ; le registre de widgets du contrat d'extension reste à définir.

## Association élément ↔ tag

Deux règles, dans cet ordre, sans jamais deviner :

1. les deux derniers segments du topic (`équipement/tag`) égalent `source/tag` d'un tag du workspace ;
2. le `binding` de l'élément (`packaging.line1.filler.temperature`) égale `source/tag`, la source étant le chemin relatif de l'asset en minuscules joint par `-` (`packaging-line1-filler`) : c'est la règle de nommage de `app:uns:provision`.

Une correspondance ambiguë ou absente donne « Non associé » : l'élément est ignoré à l'enregistrement.

## Ingestion des valeurs

Les topics hiérarchiques sont ingérés via le préfixe de topic d'une source (voir `docs/mqtt-ingestion.md`, section « UNS topics »). Chaîne complète en développement : simulateur → `uns-infer` → `app:uns:provision --enable` → `app:mqtt:consume` → `/uns-import`.

## Limites

- Le préfixe de topic est unique sur toute l'instance : deux workspaces ne peuvent pas ingérer le même topic.
- Les abonnements du consommateur sont calculés au démarrage : redémarrer après ajout ou activation d'une source.
- Le mode « un JSON par asset » du simulateur et de `uns-infer` n'est pas ingéré (seulement inféré).
- Les tests d'intégration PHP (`UnsIngestionIntegrationTest`) exigent PostgreSQL `_test` ; la CI backend les exécute.
- L'import n'écrit pas de layout : la grille de la page inférée sert à l'aperçu ; le dashboard personnel conserve l'ordre de lecture.
- Fonction de développement local : broker anonyme, pas de production.
