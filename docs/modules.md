# Modules et frontières

| Module | Contenu | Dépendances autorisées |
| --- | --- | --- |
| core-contracts | types, événements, schémas, versions | aucune couche applicative |
| connectors | MQTT, OPC UA, simulateur | core-contracts |
| telemetry | normalisation, cache, abonnements, qualité | core-contracts |
| catalog | sources, tags, unités, métadonnées | core-contracts |
| dashboards | layouts, widgets, édition | core-contracts, telemetry |
| charts | providers, séries, axes | core-contracts |
| synoptics | SVG, symboles, calques, bindings | core-contracts, telemetry |
| persistence | sauvegarde, migrations, historique | core-contracts |
| ai | prompts, fournisseurs, validation des propositions | core-contracts, catalog |
| auth | identité, permissions, audit | core-contracts |

**Règle :** les modules métier n'importent pas les détails d'un connecteur. Les données passent par le modèle canonique. Le navigateur n'accède pas directement à l'OPC UA.

## Organisation indicative
```text
apps/
  web/
  api/
packages/
  core-contracts/
  ui/
  charts/
  synoptics/
  connector-sdk/
services/
  ingestion/
docs/
  adr/
```
Cette arborescence est indicative : à confirmer après choix de la stack et de la stratégie monorepo.
