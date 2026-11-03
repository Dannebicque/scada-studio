# MQTT ingestion — local development

This initial implementation consumes QoS 0 messages from Mosquitto on `127.0.0.1:1883` using the `mosquitto_sub` CLI. It is not enabled in production.

## Prerequisites

Install Mosquitto clients on the host (`brew install mosquitto` on macOS). Start the Docker broker from the repository root:

```bash
docker compose --env-file .env -f infra/compose.yaml up -d mqtt
```

## Setup and test

```bash
cd apps/api
php bin/console app:demo:catalog
php bin/console app:demo:enable-mqtt
php bin/console app:mqtt:consume
```

In another terminal:

```bash
mosquitto_pub -h 127.0.0.1 -p 1883 \
  -t 'scada/atelier-demo/automate-01/temperature' \
  -m '{"value":22.4,"observedAt":"2026-11-03T18:00:00Z"}'
```

Use a current UTC timestamp for subsequent messages: older or duplicate timestamps are ignored. The read API marks samples older than 30 seconds as `stale`; the Nuxt catalog displays the status and polls every 5 seconds. Check `GET /api/tag-values` or refresh the Nuxt catalog to see the latest reading.

## Contract

Topic: `scada/{workspaceSlug}/{sourceKey}/{tagKey}`

Payload: JSON object with `value` and optional ISO-8601 `observedAt`.

The source must exist, be enabled, and have connector type `mqtt`. Values must match the tag's declared type. Oversized payloads, unknown topics, invalid values, future timestamps, and stale measurements are ignored.

## Limitations

The subscriber is intended for local prototyping, not production. It uses an anonymous broker bound to localhost, a basic bounded restart delay but no production-grade process supervisor, no persistent message history, and 5-second client polling. Do not expose the broker publicly. Production requires TLS/authentication, operational monitoring, and a proper MQTT client library. Historical retention is deferred to the persistence milestone.
