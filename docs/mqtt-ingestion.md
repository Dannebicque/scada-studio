# MQTT ingestion — local development

This implementation consumes QoS 0 messages from an MQTT broker using the `mosquitto_sub` CLI. By default it targets Mosquitto on `127.0.0.1:1883` (anonymous, local development). See "Broker connection" to target another broker.

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

## UNS topics (hierarchical)

Besides the legacy contract, a source can declare a **topic prefix** (`data_source.topic_prefix`, unique across the instance). A message published on `{prefix}/{tagKey}` updates the matching tag, e.g. prefix `acme/troyes/packaging/line1/filler` and topic `acme/troyes/packaging/line1/filler/temperature`.

- Topics have 2 to 12 segments, each `[a-zA-Z0-9_-]{1,100}`. The first segment `scada` is reserved for the legacy 4-segment contract and cannot be used as a prefix.
- The consumer subscribes to `scada/+/+/+` plus one `{prefix}/+` filter per **enabled** MQTT source with a prefix (200 at most). It never subscribes to `#`. The catalogue is re-read every 30 seconds and the subscriber is restarted when the filters change, so a source added or enabled is picked up without restarting the consumer.
- Payload: same `value` / optional `observedAt`. Extra fields (`unit`, `valueType`) are ignored. A `quality` field other than `"good"` makes the sample ignored, so the value becomes stale instead of being shown as fresh. One-JSON-per-asset payloads (`{"tags": {…}}`) are not ingested.
- Apply the migration (`php bin/console doctrine:migrations:migrate`), then create sources and tags from an inferred model:

```bash
node tools/uns-infer/infer.mjs --topic 'acme/troyes/#' --out out
php bin/console app:uns:provision atelier-demo out/uns-model.json --enable
php bin/console app:mqtt:consume
node tools/uns-simulator/simulator.mjs --retain   # profile "uns"
```

`app:uns:provision` (development only) creates one source per asset (key = relative asset path in lowercase joined with `-`, topic prefix = asset topic) and one tag per learned variable (`boolean`, `string`, or `float` for numbers). Sources are created **disabled** unless `--enable` is given. Existing sources and tags are left untouched, so the command is idempotent.

## Broker connection

The consumer reads its connection from environment variables (never from command-line arguments, which are visible in the process list):

| Variable | Default | Purpose |
| --- | --- | --- |
| `MQTT_HOST` | `127.0.0.1` | Broker host name or address |
| `MQTT_PORT` | `1883` | Broker port |
| `MQTT_TLS` | off | `1` to encrypt the connection (system certificate authorities) |
| `MQTT_CAFILE` | — | PEM file of a private certificate authority (implies TLS) |
| `MQTT_USERNAME`, `MQTT_PASSWORD` | — | Credentials, to be set together. Passed to `mosquitto_sub` through a temporary `0600` options file removed on exit |

In `APP_ENV=prod` the consumer refuses to start unless `MQTT_HOST` is set, and refuses an unencrypted connection to a non-loopback broker. Use a dedicated account whose ACL allows **read only** on the prefixes you ingest. Secrets belong in `.env.local` or the Symfony secrets vault, not in `.env`.

The `-o` options-file mechanism of `mosquitto_sub` has not been exercised against an authenticated broker in this repository's tests: check it on your broker before relying on it.

## Limitations

The subscriber is still a prototype: a basic bounded restart delay but no production-grade process supervisor, no persistent message history, and 5-second client polling. Do not expose a development broker publicly. Production also requires operational monitoring and, ideally, a native MQTT client library instead of the `mosquitto_sub` subprocess. Historical retention is deferred to the persistence milestone.

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
