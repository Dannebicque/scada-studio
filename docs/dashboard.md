# L3 — First persisted dashboard slice

This first L3 iteration adds a read-only, workspace-owned `Dashboard` entity with a JSON list of value widgets. The example dashboard is seeded by `app:demo:catalog` and binds four tags from the existing MQTT demo. The API exposes read-only `GET /api/dashboards` and Nuxt renders live latest-value cards at `/dashboard`.

## Run

From `apps/api`, after updating the branch:

```bash
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:demo:catalog
```

Start Symfony and Nuxt as usual, then open `/dashboard`. Publish telemetry with `mosquitto_pub` as described in `docs/mqtt-ingestion.md`; the value widgets update every five seconds. Quality changes to stale after 30 seconds.

## Scope and security

This slice **does not expose dashboard write endpoints**: editing, widget placement, configuration forms, import/export and authorization are planned for subsequent L3 increments. JSON widget definitions are generated from known demo tags, not user-supplied input. Do not introduce public write operations without workspace authorization and server-side widget/tag validation.

No time-series history is stored. Widgets show only the most recent value and its freshness.

## Draft editor preview

A local-only preview now allows adding and removing value widgets. It does **not** save anything to the API or database. The `WidgetLayoutValidator` service provides a foundation for future authorized writes, rejecting unsupported widgets, overlong layouts, and tags outside the target workspace. The write endpoint must remain disabled until authentication and workspace authorization are implemented.
