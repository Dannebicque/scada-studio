---
title: Démarrage
description: Installer et lancer la démonstration locale
---
# Démarrage local

Prérequis : Docker Compose, PHP/Composer compatibles avec le backend, Node.js/npm compatibles avec Nuxt 4 et les clients Mosquitto.

Depuis la racine du dépôt, après avoir créé et configuré le fichier `.env` :

```bash
docker compose --env-file .env -f infra/compose.yaml up -d
```

Dans `apps/api`, installer les dépendances Composer si nécessaire, configurer `DATABASE_URL`, puis exécuter :

```bash
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:demo:catalog
php bin/console app:demo:enable-mqtt
symfony server:start
```

Dans un autre terminal, toujours dans `apps/api` :

```bash
php bin/console app:mqtt:consume
```

Démarrer le frontend avec `npm run dev` dans `apps/web`. Ouvrir `/catalog`, puis `/dashboard`.

Publier une mesure :

```bash
mosquitto_pub -h 127.0.0.1 -p 1883 -t 'scada/atelier-demo/automate-01/temperature' -m '{"value":22.4}'
```

Le consommateur doit indiquer `Accepted`, et la valeur doit apparaître dans l'interface. Une mesure non renouvelée devient périmée après 30 secondes.

**Sécurité :** le broker de démonstration accepte des connexions anonymes locales. Ne pas l'exposer à Internet.
