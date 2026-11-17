# Inférence d'interface depuis des trames MQTT/UNS

Chaîne de bout en bout, lecture seule, avec validation humaine avant toute sauvegarde.

```text
MQTT/UNS ──► tools/uns-infer ──► page.json (page.schema.json) ──► /uns-import ──► dashboard personnel
 (ou tools/uns-simulator)         propositions core.*              aperçu + revue     widgets value/gauge/status/chart
```

## Étapes

1. **Trames de test** : `tools/uns-simulator` (profils `uns` ISA-95 profond, ou `scada` = contrat de `MqttMessageParser`).
2. **Inférence** : `tools/uns-infer` écoute le broker (ou un fichier), apprend type, unité, plage, cadence et comportement de compteur de chaque tag, puis génère `uns-model.json` et une page par area.
3. **Import** : la page web `/uns-import` charge le JSON, le valide (1 Mo, 300 éléments, `schemaVersion: 1`, `kind: dashboard`), l'affiche sans rien exécuter, associe chaque élément à un tag du catalogue et crée un dashboard personnel après confirmation.

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

Un élément est associé quand les deux derniers segments de son topic (`équipement/tag`) égalent `source/tag` d'un tag du workspace. Une correspondance ambiguë ou absente n'est jamais devinée : l'élément est signalé « Non associé » et ignoré à l'enregistrement.

## Limites

- Le catalogue ne connaît que `workspace/source/tag` ; une hiérarchie UNS profonde n'est donc associable que par ses deux derniers segments, jusqu'à ce que les assets sémantiques (projection UNS) servent de cible de mapping.
- L'ingestion MQTT actuelle n'accepte que 4 segments de topic : les trames du profil `uns` profond ne sont pas ingérées, seulement inférées.
- L'import n'écrit pas de layout : la grille de la page inférée sert à l'aperçu ; le dashboard personnel conserve l'ordre de lecture.
- Fonction de développement local : broker anonyme, pas de production.
