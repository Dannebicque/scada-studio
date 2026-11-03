# Roadmap de SCADA Studio

Les durées sont **des estimations indicatives**, pas des dates de livraison. Les lots peuvent se chevaucher après stabilisation des contrats.

| Lot | Durée indicative | Livrable démontrable | Sortie de lot |
| --- | --- | --- | --- |
| L1 Socle | 2 semaines | Application démarrable + schéma versionné | CI verte, contrats documentés |
| L2 Runtime | 2 semaines | Valeurs MQTT visibles dans widgets | Reconnexion, qualité de donnée, tests |
| L3 Dashboard | 2 semaines | Dashboard éditable et persisté | Export/import, widgets enregistrables |
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
