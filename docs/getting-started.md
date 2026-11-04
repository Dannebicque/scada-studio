# Démarrage du prototype

Prérequis : Node.js compatible Nuxt 4, npm, Docker Compose, PHP et Composer pour le backend.

1. `cp .env.example .env`
2. Remplacer `POSTGRES_PASSWORD` par un mot de passe local.
3. `docker compose --env-file .env -f infra/compose.yaml up -d`
4. `npm install`
5. `npm run dev:web` puis ouvrir l'adresse indiquée par Nuxt.

Le broker MQTT de développement accepte les connexions anonymes **uniquement sur 127.0.0.1**. Il n'est pas adapté à un déploiement réel.

## Backend
`apps/api` sera généré depuis le squelette officiel Symfony et API Platform dans l'issue #2. Le choix demandé est Symfony 8.1 ; vérifier la disponibilité effective de cette version et ses prérequis PHP avant installation. Ne pas substituer silencieusement une version.

## État actuel
Les pages Nuxt sont des écrans d'attente. Il n'y a pas encore d'API, de connexion MQTT, d'éditeur ni de moteur temps réel. Les JSON Schema constituent une première base contractuelle à éprouver et à compléter.
