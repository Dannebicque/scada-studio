# Modèle métier initial — issue #6

## Frontières

- **Workspace** : espace logique, identifié par UUID et slug unique.
- **DataSource** : source de télémétrie appartenant à un espace, identifiée par une clé unique *dans cet espace*. `connectorType` désigne un type d'adaptateur sans lier le domaine à MQTT, OPC UA ou un autre protocole.
- **Unit** : unité d'ingénierie partagée, identifiée par un code stable (ex. `Cel`, `%`). Elle ne contient pas de formule de conversion.
- **Tag** : point de mesure d'une source, identifié par une clé unique *dans cette source*, type `boolean|integer|float|string`, unité facultative.

Le modèle est volontairement **sans identifiants de broker, credentials, topics MQTT ou nœuds OPC UA** : ces paramètres appartiendront au module connecteur et à un stockage sécurisé. Aucun secret n'est exposé par API Platform.

## API actuelle

API Platform expose uniquement les opérations de lecture `GET` et `GET collection` sur ces quatre ressources. Les écritures HTTP ne sont pas activées. Attention : ces lectures sont **publiques dans ce prototype**, sans authentification ni isolation par espace. Ne pas déployer en environnement partagé ou de production avant l'implémentation des contrôles d'accès.

## Démonstration locale

Démarrer PostgreSQL, définir `DATABASE_URL` puis, en développement uniquement :

```bash
cd apps/api
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:schema:update --dump-sql
php bin/console doctrine:schema:update --force
php bin/phpunit
```

`schema:update --force` est réservé à ce prototype local : la migration versionnée et les fixtures restent à créer avant fusion. Les endpoints de collection sont accessibles via `/api/workspaces`, `/api/data_sources`, `/api/units`, `/api/tags` (vérifier les routes effectives avec `debug:router`).

## Suite

- Migrer les entités vers des migrations Doctrine versionnées.
- Ajouter fixtures et tests fonctionnels PostgreSQL.
- Ajouter auth, autorisations par espace et DTO pour éviter l'exposition directe des entités.
- Ajouter validation métier des clés et longueurs ; stockage chiffré des paramètres des connecteurs.
- Relier le catalogue aux événements de télémétrie et aux historiques, sans commande d'actionneur dans le MVP.
