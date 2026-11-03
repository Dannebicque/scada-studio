# Roadmap de SCADA Studio

Les durées sont **des estimations indicatives**, pas des dates de livraison. Les lots peuvent se chevaucher après stabilisation des contrats.

| Lot | Durée indicative | Livrable démontrable | Sortie de lot |
| --- | --- | --- | --- |
| L1 Socle | 2 semaines | Application démarrable + schéma versionné | CI verte, contrats documentés |
| L2 Runtime | 2 semaines | Valeurs MQTT visibles dans widgets | Reconnexion, qualité de donnée, tests |
| L3 Dashboard & UX | 2–3 semaines | Interface FR/EN, navigation moderne, dashboard éditable et persisté, fiches connecteurs | Widgets enregistrables, configuration documentée, accessibilité et tests |
| L4 Synoptique | 3–4 semaines | Synoptique SVG interactif | Calques, symboles, binding |
| L5 OPC UA & IA | 3 semaines | Source OPC UA + assistant proposant une configuration | Validation humaine, isolation réseau |
| L6 Fiabilisation | 2 semaines | Démonstrateur documenté | Sécurité, performance, accessibilité, E2E |

## Ordre et dépendances
1. **L1** : contrats et modèle de données avant les implémentations de plugins.
2. **L2** : flux de télémétrie canonique avant les widgets temps réel.
3. **L3** : réutilise le runtime L2 et le registre de widgets L1.
4. **L4** : réutilise les bindings L2 et la persistance L3.
5. **L5** : réutilise le modèle canonique et les schémas versionnés ; aucune commande d'équipement générée automatiquement.
6. **L6** : tests continus dès L1, consolidation finale.

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
