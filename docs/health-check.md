# Health check API — L1

## Contrat
`GET /api/health` renvoie HTTP 200, `Content-Type: application/json`, `Cache-Control: no-store` et un objet :
```json
{"status":"ok","service":"scada-studio-api","timestamp":"2026-11-03T12:00:00+00:00"}
```
Le timestamp est l'heure courante UTC. Ce endpoint confirme uniquement que Symfony répond ; il ne garantit pas l'état de PostgreSQL, MQTT ou OPC UA.

## Développement
Dans un terminal : `cd apps/api && symfony server:start` (ou `php -S 127.0.0.1:8000 -t public public/index.php`).
Dans un autre : `npm run dev:web`.
Configurer `NUXT_API_BASE_URL=http://127.0.0.1:8000` si le backend écoute ailleurs.

Le navigateur appelle `/api/health` **sur Nuxt**, dont la route serveur relaie l'appel vers Symfony. Aucun CORS permissif ni URL interne de l'API n'est exposé au navigateur. Le proxy retourne HTTP 503 en cas de panne ou de réponse inattendue. Le bouton « Actualiser » relance la vérification.

## Tests
`cd apps/api && php bin/phpunit` : réponse JSON 200 et refus de POST (405).
Le workflow `backend.yml` exécute les tests en CI sur PHP 8.4.

## Sécurité
Le health check ne renvoie aucun secret ni détail d'infrastructure. Une vérification approfondie des dépendances sera ajoutée séparément, avec des contrôles d'accès adaptés.
