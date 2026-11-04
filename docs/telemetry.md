# Latest-value telemetry prototype

The catalog describes tags; `TagValue` stores the latest observed value per tag.
The read endpoint `GET /api/tag-values` exposes a snapshot with `tagId`, `value`, and `observedAt`.
This is not a history store, MQTT consumer, or live push channel.

## Local demo

```bash
cd apps/api
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:demo:catalog
php bin/console app:demo:telemetry
curl http://127.0.0.1:8000/api/tag-values
```

Refresh `/catalog` in Nuxt to see the new values. Repeat `app:demo:telemetry` to generate another snapshot. The simulator is disabled in production and does not send commands to physical equipment.

## Next step

Build an authenticated, bounded MQTT ingestion worker with a defined topic-to-tag mapping, value validation, source timestamps and connection health. Keep the broker's anonymous listener restricted to local development.
