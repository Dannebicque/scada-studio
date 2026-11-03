# Accounts, workspaces, dashboards and tags / Comptes, espaces, tableaux de bord et tags

## FR — modèle de données

Les tableaux de bord de supervision partagés restent en lecture seule (`Dashboard`). Les tableaux de bord personnels sont stockés dans `UserDashboard`, avec un propriétaire (`AppUser`) et un espace de travail (`Workspace`). Les préférences personnelles sur les tags (`UserTag`) portent sur un alias et un favori, **sans modifier la définition du tag industriel**.

`WorkspaceMember` attribue les rôles `viewer`, `editor`, `owner` à un compte par espace. Le service `WorkspaceAccess` refuse les accès anonymes par défaut et vérifie les rôles. Les widgets doivent toujours passer par `WidgetLayoutValidator` avant écriture.

Après `doctrine:migrations:migrate`, les tables sont disponibles. Le provider Doctrine, les points d'entrée Symfony de connexion/déconnexion et `GET /api/me` sont préparés. **Il n'y a pas encore d'inscription, de provisionnement de compte, de formulaire de connexion Nuxt, ni d'API d'écriture de tableaux de bord ou de préférences.** Les nouvelles entités ne sont pas exposées en ressources API Platform. Ne pas considérer cette étape comme une authentification déployée.

Avant de permettre la sauvegarde : établir la session entre Nuxt et Symfony (cookies sécurisés, SameSite, CSRF), créer un parcours d'identification et de provisionnement, limiter toutes les lectures et écritures au workspace autorisé, valider les tags et les widgets, et ajouter des tests d'accès croisés et anonymes.

## EN — data model

Shared monitoring dashboards remain read-only (`Dashboard`). Personal dashboards use `UserDashboard`, owned by an `AppUser` within a `Workspace`. `UserTag` stores personal aliases and favorites **without changing industrial tag definitions**.

`WorkspaceMember` assigns `viewer`, `editor`, or `owner` per workspace. `WorkspaceAccess` denies anonymous access by default. Widget writes must pass `WidgetLayoutValidator`.

After applying the migration, the schema is ready. The Doctrine user provider, Symfony login/logout check paths and `GET /api/me` are in place. **Account provisioning, a Nuxt login screen, and secured dashboard/tag write APIs are not yet implemented.** These entities are deliberately not exposed through API Platform.

Before enabling persistence: establish secure Nuxt/Symfony sessions, CSRF protections, provisioning, workspace-scoped read/write checks, widget validation and cross-user authorization tests.
