# Inférence d'interface depuis des trames MQTT/UNS

Chaîne de bout en bout, lecture seule, avec validation humaine avant toute sauvegarde.

```text
MQTT/UNS ──► tools/uns-infer ──► page.json (page.schema.json) ──► /uns-import ──► dashboard personnel
 (ou tools/uns-simulator)         propositions core.*              aperçu + revue     widgets value/gauge/status/chart
```

## Étapes

1. **Trames de test** : `tools/uns-simulator` (profils `uns` ISA-95 profond, ou `scada` = contrat de `MqttMessageParser`).
2. **Inférence** : `tools/uns-infer` écoute le broker (ou un fichier), apprend type, unité, plage, cadence et comportement de compteur de chaque tag, puis génère `uns-model.json` et une page par area.
3. **Import** : la page web `/uns-import` charge le JSON, le valide (1 Mo, 300 éléments, `schemaVersion: 1`, `kind: dashboard`), l'affiche sans rien exécuter, associe chaque élément à un tag du catalogue et crée un dashboard personnel après confirmation. Le tableau créé est affiché sur `/dashboard` (liste « Mes tableaux », ou lien « Afficher » de Mon espace) avec un rendu par type : valeur, jauge (`%`), voyant d'état ; le type `chart` trace l'historique du tag (moyenne et bande min–max, période au choix, résumé texte et tableau de données), voir `docs/mqtt-ingestion.md`, section History.

## Découverte depuis l'API (sans console)

L'interface `/uns-discovery` (propriétaires uniquement) enchaîne ces appels : exploration d'un préfixe, choix des équipements, création du catalogue (activation de l'ingestion en option), puis création du tableau de bord personnel.

Pour un serveur sans accès console, l'inférence et l'activation sont aussi exposées par l'API, réservées aux **propriétaires** du workspace et protégées par jeton CSRF (même mécanisme que la gestion des membres) :

| Route | Rôle |
| --- | --- |
| `GET /api/workspaces/{id}/uns/discovery/csrf` | Jeton CSRF, état (`enabled`) et adresse du broker configuré |
| `POST /api/workspaces/{id}/uns/discover` `{prefix, seconds}` | Écoute `{prefix}/#` pendant 5 à 30 s et renvoie `{frames, truncated, model, pages}`. Rien n'est enregistré |
| `POST /api/workspaces/{id}/uns/provision` `{model, topics, enable}` | Crée sources et tags pour les assets sélectionnés (`topics`), désactivés sauf `enable: true` |

Le broker est celui du serveur (variables `MQTT_*`, voir `docs/mqtt-ingestion.md`) : l'API n'accepte **jamais** d'adresse en entrée, ce qui évite qu'un compte compromis fasse sonder le réseau interne. Les préfixes explorables sont limités par `UNS_ALLOWED_PREFIXES` (ex. `acme/troyes,acme/lyon`) : sans cette liste, tout préfixe valide est accepté en développement et **aucun en production**. Le préfixe `scada` reste réservé. L'inférence est un port PHP de `tools/uns-infer` ; les deux donnent le même modèle sur les mêmes trames.

Un préfixe n appartient qu à un seul workspace pour toute l instance : la liste blanche qui empêche un propriétaire de s'abonner à des topics qui ne sont pas les siens.

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
- Les abonnements du consommateur sont relus toutes les 30 s : une source ajoutée ou activée est prise en compte sans redémarrage.
- Le mode « un JSON par asset » du simulateur et de `uns-infer` n'est pas ingéré (seulement inféré).
- Les tests d'intégration PHP (`UnsIngestionIntegrationTest`) exigent PostgreSQL `_test` ; la CI backend les exécute.
- L'import n'écrit pas de layout : la grille de la page inférée sert à l'aperçu ; le dashboard personnel conserve l'ordre de lecture.
- Fonction de développement local : broker anonyme, pas de production.
