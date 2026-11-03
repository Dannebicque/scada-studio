---
title: Getting started
description: Install and run the local demonstration
---
# Local setup

Requirements: Docker Compose, PHP/Composer compatible with the backend, Node.js/npm compatible with Nuxt 4, and Mosquitto clients.

From the repository root, after creating and configuring `.env`:

```bash
docker compose --env-file .env -f infra/compose.yaml up -d
```

In `apps/api`, install Composer dependencies if needed, configure `DATABASE_URL`, then run:

```bash
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:demo:catalog
php bin/console app:demo:enable-mqtt
symfony server:start
```

In a second terminal in `apps/api`:

```bash
php bin/console app:mqtt:consume
```

Start the frontend with `npm run dev` in `apps/web`. Open `/catalog` and then `/dashboard`.

Publish a reading:

```bash
mosquitto_pub -h 127.0.0.1 -p 1883 -t 'scada/atelier-demo/automate-01/temperature' -m '{"value":22.4}'
```

The consumer should log `Accepted`, and the reading should appear in the UI. Readings become stale after 30 seconds without updates.

**Security:** the demo broker allows anonymous local connections. Never expose it to the Internet.
