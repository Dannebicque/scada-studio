# ADR 0001 — Stack et frontières du monorepo

Statut : accepté pour le prototype (issue #1, choix exprimés le 3 novembre 2026).

## Décision
- Frontend : Nuxt 4 (Vue 3, TypeScript) et Tailwind CSS 4.
- Backend : Symfony 8.1 **cible demandée**, API Platform, PHP compatible avec la version retenue ; vérifier disponibilité et prérequis au moment du bootstrap.
- Services optionnels : Python pour OPC UA (asyncua) et tâches asynchrones spécialisées.
- Monorepo : `apps/web`, `apps/api`, `packages/contracts`, `services/`, `infra/`.
- PostgreSQL pour les métadonnées ; historique de séries temporelles à décider par mesure (TimescaleDB non imposé).
- MQTT initialement via broker de développement ; Mercure ou WebSocket pour la diffusion au navigateur, décision reportée à L2.

## Frontières
Nuxt ne dialogue qu'avec l'API et le canal de télémétrie autorisé. Les connecteurs isolent les protocoles industriels. Les tags canoniques et la qualité de données sont indépendants de MQTT/OPC UA. Les pages dashboard et synoptique partagent un format de document versionné et un moteur de binding, mais disposent d'éditeurs/rendeurs spécialisés.

## Contraintes de sûreté
MVP lecture seule, aucune commande d'actionneur, aucune exécution de code fourni par l'IA ou par un document JSON. Les secrets restent côté serveur, jamais dans les exports. L'IA produit des propositions validées puis approuvées.

## Conséquences
Les packages partagés exposent JSON Schema et types TypeScript ; Symfony valide les mêmes documents avec un validateur JSON Schema. Le bootstrap Symfony réel doit être généré avec Composer pour obtenir un lockfile reproductible, plutôt que de simuler des fichiers framework.
