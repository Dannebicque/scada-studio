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

## Update / Mise à jour — Account provisioning and private reads

FR : la commande `php bin/console app:user:create email@example.org workspace-slug editor` crée un compte et/ou une appartenance à un espace existant. Le mot de passe initial est saisi de façon interactive. Les endpoints `GET /api/me/dashboards` et `GET /api/me/tags` retournent uniquement les données personnelles accessibles au compte authentifié. Les requêtes anonymes retournent 401. **Aucune écriture n'est exposée à ce stade** : il reste à finaliser les sessions Nuxt/Symfony et la protection CSRF.

EN: `php bin/console app:user:create email@example.org workspace-slug editor` provisions an account or an additional membership in an existing workspace; the initial password is entered interactively. `GET /api/me/dashboards` and `GET /api/me/tags` only return data owned by the authenticated account and belonging to authorized workspaces. Anonymous requests receive 401. **Writes remain disabled** until Nuxt/Symfony sessions and CSRF protection are implemented.

## FR — gestion des accès

Le voter Symfony `WorkspaceVoter` définit trois permissions : `WORKSPACE_VIEW`, `WORKSPACE_EDIT`, `WORKSPACE_MANAGE`. Il délègue à `WorkspaceAccess` pour éviter de dupliquer les règles. Les membres sans rôle valide et les utilisateurs anonymes ne reçoivent aucun accès.

`GET /api/workspaces/{id}/members` retourne les comptes et rôles d'un espace **uniquement à son propriétaire**. Les non-authentifiés sont refusés, et un membre non propriétaire reçoit 403. La commande `app:user:create` permet aussi de modifier le rôle d'une appartenance existante. L'administration des membres depuis Nuxt et la modification des rôles via HTTP ne sont pas encore disponibles.

## EN — access management

The Symfony `WorkspaceVoter` defines `WORKSPACE_VIEW`, `WORKSPACE_EDIT`, and `WORKSPACE_MANAGE` permissions, delegating membership checks to `WorkspaceAccess`. Anonymous users and accounts without appropriate workspace membership are denied.

`GET /api/workspaces/{id}/members` lists workspace members and roles **only for workspace owners**. Unauthenticated requests are rejected and non-owner members receive 403. The `app:user:create` CLI can also change existing memberships. Member administration in Nuxt and HTTP role updates are not yet available.

## FR — connexion depuis Nuxt (développement)

La page `/account` contient désormais le formulaire de connexion et un bouton de déconnexion. Les routes Nitro `POST /api/auth/login` et `POST /api/auth/logout` transmettent les requêtes à Symfony. La route Symfony `/api/auth/login` est enregistrée pour l'authenticator `json_login`. Créer d'abord un compte avec `app:user:create`. Vérifier en développement que les cookies de session sont bien conservés par le navigateur, que `GET /api/me` confirme la connexion et que la déconnexion invalide la session. **Ne pas déployer publiquement avant validation des protections CSRF, des cookies sécurisés et des tests d'intégration de session.**

## EN — Nuxt sign-in (development)

The `/account` page now includes sign-in and sign-out controls. Nitro routes `POST /api/auth/login` and `POST /api/auth/logout` forward requests to Symfony. The Symfony `/api/auth/login` route is registered for `json_login`. First provision an account with `app:user:create`. In development, verify session cookie persistence, authenticated `GET /api/me`, and session invalidation on logout. **Do not expose publicly before CSRF protections, secure cookies, and integration session tests are validated.**

## FR — annuaire des membres

La page Nuxt `/access` permet aux propriétaires d'espaces de consulter les membres et leurs rôles. La route Nitro `GET /api/workspaces/{id}/members` transmet la session au backend ; le voter Symfony vérifie la permission de gestion. L'interface reste en lecture seule, en attendant la sécurisation des mutations de rôles et des invitations.

## EN — member directory

The Nuxt `/access` page lets workspace owners view members and their roles. The Nitro `GET /api/workspaces/{id}/members` route forwards the session to Symfony; the voter enforces workspace management permissions. The interface remains read-only until role mutations and invitations are secured.

## FR — modification des membres

Depuis `/access`, un propriétaire peut désormais associer un compte **déjà existant** à un espace (`POST /api/workspaces/{id}/members`), changer son rôle (`PATCH /api/workspaces/{id}/members/{userId}`) ou retirer son accès (`DELETE` sur la même URL). Les rôles possibles sont `viewer`, `editor` et `owner`. La dernière appartenance `owner` est protégée contre la rétrogradation et la suppression (HTTP 409). Les opérations sont vérifiées côté serveur avec `WORKSPACE_MANAGE` et un jeton CSRF lié à la session, obtenu via `GET /api/workspaces/{id}/members/csrf` puis transmis dans `X-CSRF-Token`. Nuxt relaie le cookie de session. Il n'existe pas encore de création de compte ou d'invitation par e-mail depuis l'interface. **À vérifier avant production :** tests d'intégration avec PostgreSQL et sessions, cookies Secure/SameSite, politique d'origine, journalisation et concurrence sur le dernier propriétaire.

## EN — managing members

From `/access`, an owner can assign an **existing** account to a workspace (`POST /api/workspaces/{id}/members`), change a role (`PATCH /api/workspaces/{id}/members/{userId}`), or remove access (`DELETE` on the same URL). Supported roles: `viewer`, `editor`, `owner`. The last owner cannot be demoted or removed (HTTP 409). Server-side operations enforce `WORKSPACE_MANAGE` and a session-bound CSRF token retrieved from `GET /api/workspaces/{id}/members/csrf` and supplied through `X-CSRF-Token`. Nuxt forwards the session cookie. Account creation and email invitations are not yet available in the UI. **Before production:** validate PostgreSQL/session integration tests, Secure/SameSite cookies, origin policy, auditing, and concurrent last-owner changes.

## FR — architecture frontend mutualisée

La gestion du compte et des accès utilise les types communs `app/types/access.ts`, le composable `useAccount()` (clé de cache Nuxt `current-account`), `useWorkspaceRoles()` pour les libellés et les composants `app/components/access/RoleBadge.vue` et `RoleSelect.vue`. Les pages `account.vue` et `access.vue` consomment ces éléments au lieu de redéfinir les types et les libellés. Conserver les contrôles d'autorisation dans Symfony : les composants ne constituent jamais une frontière de sécurité.

## EN — shared frontend architecture

Account and access pages share `app/types/access.ts`, the `useAccount()` composable (Nuxt cache key `current-account`), `useWorkspaceRoles()` for labels, and `app/components/access/RoleBadge.vue` and `RoleSelect.vue`. Pages consume these primitives instead of redefining types and labels. Authorization must remain enforced by Symfony: UI components are not a security boundary.

## FR — composable des membres

`app/composables/useWorkspaceMembers.ts` regroupe le chargement, les mutations CSRF, les états d'erreur, les filtres et la pagination. `app/components/access/AddMemberForm.vue` est un formulaire réutilisable à événement `add(email, role)` typé ; il réutilise `AccessRoleSelect`. La page `access.vue` conserve la sélection de l'espace, les confirmations utilisateur et l'assemblage des composants. Les formulaires ne sont réinitialisés qu'après une mutation réussie.

## EN — members composable

`app/composables/useWorkspaceMembers.ts` centralizes loading, CSRF-protected mutations, errors, filtering and pagination. `app/components/access/AddMemberForm.vue` exposes a typed `add(email, role)` event and reuses `AccessRoleSelect`. The `access.vue` page keeps workspace selection, user confirmations and composition. Forms are reset only after successful mutations.
