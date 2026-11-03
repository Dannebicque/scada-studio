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

## Reconnection and graceful shutdown smoke test

Run this test locally with the API database initialized, the demo MQTT source enabled, Docker Compose, and `mosquitto_pub` / `mosquitto_sub` installed.

1. Start the broker and run `APP_ENV=dev php bin/console app:mqtt:consume` in a dedicated terminal.
2. Publish a fresh reading (omit `observedAt` to use the receipt time):

   ```bash
   mosquitto_pub -h 127.0.0.1 -p 1883 \
     -t scada/atelier-demo/automate-01/temperature -m '{"value":22.4}'
   ```

3. Confirm `Accepted` in the consumer output and the updated value in `GET /api/tag-values`.
4. Stop the broker from the repository root:

   ```bash
   docker compose --env-file .env -f infra/compose.yaml stop mqtt
   ```

5. Confirm the consumer logs a disconnect and remains running. Restart the broker:

   ```bash
   docker compose --env-file .env -f infra/compose.yaml start mqtt
   ```

6. Publish `{"value":23.1}` to the same topic. Confirm that the subscriber reconnects, logs `Accepted`, and the API returns `23.1` with a newer timestamp.
7. Press Ctrl+C in the consumer terminal. Confirm `MQTT consumer stopped.` and that no `mosquitto_sub` child process remains.

The reconnect delay is bounded to 30 seconds. QoS 0 does **not** replay measurements published while the subscriber is disconnected. This is a manual broker-level smoke test, not a CI integration test; it should be run before considering the reconnect behavior validated.

On Unix, install/enable the PHP `pcntl` extension for graceful signal handling. Without it, the OS can still terminate the command but the application-level shutdown handler is unavailable.

## Automated integration coverage

The Backend GitHub Actions workflow now runs `bash infra/tests/mqtt-reconnect-smoke.sh` after PHPUnit. The smoke test starts a disposable Mosquitto container, seeds the isolated PostgreSQL test database, runs the Symfony consumer and HTTP API, checks the first telemetry sample, stops/restarts Mosquitto, verifies a newer sample, and sends SIGTERM to verify clean shutdown.

Run it locally only with Docker, PHP CLI with `pcntl`, `mosquitto_pub`, Python 3, and an initialized **isolated** `APP_ENV=test` database. Port 1883 and 18080 must be free. It creates the demo catalog in that test database. CI results are the acceptance gate; adding the workflow alone does not prove a passing run.

This covers process reconnection and the latest-value read API. It does not guarantee QoS 0 delivery during disconnection, exactly-once delivery, production readiness, or historical storage.
