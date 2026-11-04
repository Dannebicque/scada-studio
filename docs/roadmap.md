# Roadmap de SCADA Studio

Les durées sont **des estimations indicatives**, pas des dates de livraison. Les lots peuvent se chevaucher après stabilisation des contrats.

| Lot | Durée indicative | Livrable démontrable | Sortie de lot |
| --- | --- | --- | --- |
| L1 Socle | 2 semaines | Application démarrable + schéma versionné | CI verte, contrats documentés |
| L2 Runtime | 2 semaines | Valeurs MQTT visibles dans widgets | Reconnexion, qualité de donnée, tests |
| L3 Dashboard & UX | 2–3 semaines | Interface FR/EN, navigation moderne, dashboard éditable et persisté, fiches connecteurs | Widgets enregistrables, configuration documentée, accessibilité et tests |
| L4 Synoptique | 3–4 semaines | Synoptique SVG interactif | Calques, symboles, binding |
| L5 OPC UA, UNS & IA | 4–6 semaines (à réestimer après POC) | Connecteur OPC UA, projection UNS navigable et génération assistée de dashboards | Recherche sémantique vérifiable, schémas validés, validation humaine, isolation réseau |
| L6 Fiabilisation | 2 semaines | Démonstrateur documenté | Sécurité, performance, accessibilité, E2E |

## Ordre et dépendances
1. **L1** : contrats et modèle de données avant les implémentations de plugins.
2. **L2** : flux de télémétrie canonique avant les widgets temps réel.
3. **L3** : réutilise le runtime L2 et le registre de widgets L1.
4. **L4** : réutilise les bindings L2 et la persistance L3.
5. **L5** : construit un UNS logique sur le catalogue canonique (préparation possible dès L2/L3), puis l'utilise pour les requêtes et l'assistant IA ; aucune commande d'équipement générée automatiquement.
6. **L6** : tests continus dès L1, consolidation finale.

## Unified Namespace (UNS) — fil conducteur L2 à L5

**Objectif :** offrir une vue logique, hiérarchique et sémantique des équipements, processus, mesures et indicateurs industriels. Le namespace doit pouvoir être interrogé par l'interface, les API et l'assistant IA pour composer des dashboards pertinents à partir de données réellement disponibles. Le **UNS n'est pas un protocole ni une base de données obligatoire** : il représente une organisation cohérente de l'information, éventuellement diffusée via MQTT, indépendante des chemins de topics et des adresses OPC UA.

### Jalons

1. **L2 — Préparer les correspondances** : conserver les identifiants stables des tags, leurs sources et unités ; définir un mapping explicite entre topics MQTT, tags canoniques et chemins UNS. Aucun renommage destructif des tags existants.
2. **L3 — Explorer le namespace** : arborescence navigable par espace/site/atelier/ligne/machine/mesure (structure configurable), recherche filtrée, types, unités, qualité/fraîcheur et permissions. Un tag peut être associé à un nœud sans dépendre du topic MQTT.
3. **L4 — Réutiliser les bindings** : lier widgets et objets SVG à des identifiants stables de tags ou d'actifs ; préserver les associations en cas de changement de chemin UNS.
4. **L5.1 — Construire la projection UNS** : modèle d'actifs et de relations, hiérarchies configurables, métadonnées et alias, versionnement des mappings, ingestion MQTT et OPC UA vers une représentation canonique. Définir par ADR si une publication MQTT UNS est nécessaire.
5. **L5.2 — Recherche et requêtes structurées** : API de navigation et de recherche (par site, équipement, type de mesure, unité, état, disponibilité) ; résolution des noms ambigus, contrôle des droits avant toute réponse et indication de provenance.
6. **L5.3 — IA guidée par l'UNS** : transformer une intention utilisateur en **requête structurée** sur le namespace, récupérer uniquement les actifs/tags autorisés, puis produire une proposition de dashboard JSON conforme au registre des widgets. Prévisualisation, explication des choix, diff et validation humaine avant enregistrement.
7. **L6 — Fiabiliser** : tests de permissions inter-espaces, mappings ambigus/incomplets, références obsolètes, hallucinations d'identifiants, volumétrie, qualité des données et traçabilité des propositions IA.

### Exemple de scénario cible

Demande : « Crée un dashboard des températures et états de la ligne 2 du site A ».

- Résolution `site=A`, `ligne=2` dans le namespace **autorisé**.
- Recherche des mesures de température et des états de fonctionnement à partir de métadonnées typées, pas d'une simple correspondance de chaînes.
- Proposition de widgets `value`, `status`, `gauge` selon les capacités et la nature des données, avec `tagId` stables et unités explicites.
- Signalement des données absentes, ambiguës ou obsolètes ; pas de graphique historique sans historisation effective.
- Prévisualisation et validation de l'utilisateur avant toute persistance ; **aucune commande industrielle**.

### Contrats et critères d'acceptation

- **Identité stable** : `assetId`, `tagId` et `workspaceId` distincts des chemins UNS, topics MQTT et NodeIds OPC UA ; hiérarchie modifiable sans casser les dashboards.
- **Métadonnées minimales** : nom, description, type d'actif, type de valeur, unité, source, horodatage/qualité, relations parent/enfant ; champs optionnels documentés.
- **Sécurité** : filtrage par espace et permissions côté API avant exposition à l'IA ; aucune donnée secrète ni connexion directe du LLM aux équipements.
- **IA contrôlée** : outils de recherche en lecture seule, réponses structurées avec références vérifiables, validation JSON Schema, refus des tags inexistants ou hors périmètre.
- **Interopérabilité** : mappings MQTT et OPC UA explicites, import/export versionné, pas de dépendance obligatoire à une convention unique de nommage.
- **Qualité** : jeux de données de démonstration, tests de résolution sémantique, droits, performances et cas de données manquantes.

**Décision à instruire (ADR)** : projection UNS interne à SCADA Studio ou interopérabilité avec un UNS MQTT existant ; modèle d'actifs (arbre strict ou graphe de relations) ; stratégie de synchronisation et de publication. La première itération reste **en lecture seule**.

## MVP
Un environnement Docker local, un connecteur MQTT en lecture, un catalogue de tags, un dashboard avec graphiques, un synoptique SVG simple, import/export JSON et une démonstration avec simulateur. OPC UA et IA sont des extensions de la version suivante.

## Gouvernance
Issues titrées `[Lx][Module] Action`, avec contexte, critères d'acceptation, tests, dépendances et estimation. Priorités P0/P1/P2. Toute modification de contrat exige une décision d'architecture (ADR) et une stratégie de migration.

## Exigences transversales frontend (à partir de L3)

- **Internationalisation** : français par défaut, anglais disponible partout ; clés de traduction centralisées, pas de textes métier en dur dans les vues ; persistance du choix de langue, formats de date/nombre localisés, `lang` HTML cohérent. Couverture de chaque nouvel écran et vérification de l'absence de clés manquantes.
- **Design system** : shell applicatif responsive, navigation claire (catalogue, dashboards, synoptiques, connecteurs), typographie et contrastes cohérents, états vides/chargement/erreur, retours d'action, densité adaptée à une supervision industrielle.
- **Accessibilité** : navigation clavier, focus visible, titres structurés, libellés explicites, tableaux accessibles, contrastes, annonces non intrusives de la télémétrie ; vérification RGAA/WCAG dans la recette.
- **Connecteurs** : catalogue des protocoles et capacités, statut de disponibilité, mode lecture seule, configuration par source (hôte/port, topics, QoS, authentification selon protocole), validation des paramètres, test de connexion, erreurs explicites. **Ne jamais exposer de secrets au frontend** ; aucune écriture sur les équipements dans le MVP.
- **Qualité** : tests des traductions et parcours critiques, responsive mobile/tablette/desktop, documentation d'exploitation, séparation des contrats API et de la présentation.

### Découpage L3

1. **L3.1 — Fondations UX et FR/EN** : shell, navigation, sélecteur de langue, dictionnaires, composants de statut, pages existantes harmonisées.
2. **L3.2 — Catalogue et connecteurs** : écran sources/connecteurs, explication du mapping MQTT, configuration et diagnostic ; distinguer les capacités effectives des fonctionnalités prévues.
3. **L3.3 — Dashboard persistant** : modèle backend dashboard/widgets/layout, API par workspace, migrations, validation et tests.
4. **L3.4 — Éditeur** : ajout/suppression de widgets, liaison aux tags, réorganisation responsive, sauvegarde et restauration, états qualité.
5. **L3.5 — Import/export & recette** : schéma JSON versionné, validation à l'import, tests et documentation FR/EN.

**Garde-fou :** la persistance historique de télémétrie reste reportée. Les graphiques de séries temporelles nécessitant un historique ne seront pas présentés comme opérationnels tant que ce stockage n'existe pas.

### Barre d'état système (L3.1 → L3.2)

- [x] Bandeau commun responsive et bilingue : API joignable, qualité/fraîcheur des dernières mesures, heure de vérification.
- [ ] Ajouter un véritable heartbeat backend du consommateur MQTT et un statut de connexion au broker ; ne pas déduire la connexion de la présence de mesures.
- [ ] Étendre aux autres connecteurs et services, avec états normalisés `online/degraded/offline/unknown`, horodatage et détail consultable.
- [ ] Ajouter les alertes et incidents persistants ultérieurement ; éviter de confondre alerte métier et état d'infrastructure.
- [ ] Vérifier clavier, lecteurs d'écran, fréquence des annonces et contrastes ; pas d'annonces répétées à chaque polling.

## Documentation utilisateur FR/EN (transverse, démarrage L3)

- [x] Créer un corpus Markdown bilingue versionné dans `apps/web/content/fr` et `apps/web/content/en`, avec les mêmes slugs.
- [x] Documenter le démarrage, le dashboard, les connecteurs MQTT et les limites de sécurité, sans anticiper les fonctionnalités non livrées.
- [ ] Brancher `@nuxt/content` et générer le lockfile npm dans un environnement disposant du registre npm ; ne pas ajouter de dépendance sans lockfile reproductible.
- [ ] Exposer `/docs/fr/*` et `/docs/en/*` avec navigation, recherche, sommaire et liens contextuels depuis l'application.
- [ ] Synchroniser la langue de la documentation avec le choix FR/EN de l'interface.
- [ ] Ajouter en CI un contrôle de parité des slugs, des liens et des métadonnées des pages ; documenter chaque nouvelle fonctionnalité dans la même PR.
- [ ] Revoir les anciens documents racine `docs/*.md` et retirer les instructions obsolètes, ou les marquer comme archives techniques.

**Règle de contribution :** une évolution fonctionnelle ne peut être déclarée terminée sans sa documentation utilisateur FR/EN et la mise à jour éventuelle des références techniques.

### Correctif accessibilité documentation et dates (2026-11-03)

- [x] Première consultation intégrée de `apps/web/content/{fr,en}` via les routes `/docs/fr/*` et `/docs/en/*`, sans dépendance externe (rendu Markdown minimal provisoire).
- [x] Entrée Documentation dans la navigation et bascule FR/EN.
- [x] Formatage centralisé des horodatages avec langue et fuseau horaire du navigateur ; sérialisation UTC explicite côté API.
- [ ] Remplacer le rendu Markdown minimal par `@nuxt/content` avec lockfile, sommaire, recherche et rendu Markdown complet.
- [ ] Tester en CI les routes documentaires, les formats de dates et le changement de fuseau horaire.

### Avancement UNS (première projection)

- [x] API de lecture `GET /api/workspaces/{id}/uns` avec contrôle de visibilité du workspace.
- [x] Projection provisoire `workspace/source/tag` conservant les `tagId` stables.
- [x] Proxy Nuxt avec session et page bilingue `/uns` : sélection d'espace, recherche locale et liste des tags.
- [ ] Hiérarchie d'actifs indépendante des sources, mappings explicites MQTT/OPC UA et recherche serveur paginée.
- [ ] Métadonnées sémantiques, requêtes structurées et outils IA en lecture seule.
- [ ] Tests authentifiés inter-espaces, performances et vérification de la conformité des mappings.

La projection actuelle est une **étape exploratoire**, pas encore un UNS sémantique complet.
