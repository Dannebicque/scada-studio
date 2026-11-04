#!/usr/bin/env bash
set -euo pipefail

# Run from the repository root after installing API dependencies and migrations.
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"
BROKER="scada-mqtt-smoke-$$"
CONSUMER_PID=""
SERVER_PID=""
cleanup() {
  if [[ -n "$CONSUMER_PID" ]]; then kill -TERM "$CONSUMER_PID" 2>/dev/null || true; wait "$CONSUMER_PID" 2>/dev/null || true; fi
  if [[ -n "$SERVER_PID" ]]; then kill "$SERVER_PID" 2>/dev/null || true; wait "$SERVER_PID" 2>/dev/null || true; fi
  docker rm -f "$BROKER" >/dev/null 2>&1 || true
}
trap cleanup EXIT

docker run -d --name "$BROKER" -p 127.0.0.1:1883:1883 \
  -v "$ROOT/infra/mosquitto.conf:/mosquitto/config/mosquitto.conf:ro" \
  eclipse-mosquitto:2 >/dev/null

for i in $(seq 1 50); do
  if mosquitto_pub -h 127.0.0.1 -p 1883 -t scada/smoke/ping -m ready 2>/dev/null; then break; fi
  sleep 0.2
done
cd apps/api
APP_ENV=test php bin/console app:demo:catalog
APP_ENV=test php bin/console app:demo:enable-mqtt
APP_ENV=test php bin/console app:mqtt:consume > /tmp/scada-mqtt-consumer-$$.log 2>&1 &
CONSUMER_PID=$!
APP_ENV=test php -S 127.0.0.1:18080 -t public public/index.php > /tmp/scada-mqtt-http-$$.log 2>&1 &
SERVER_PID=$!

publish() {
  mosquitto_pub -h 127.0.0.1 -p 1883 -t scada/atelier-demo/automate-01/temperature -m "{\"value\":$1}"
}
await_value() {
  local expected="$1"
  for i in $(seq 1 100); do
    if curl -fsS http://127.0.0.1:18080/api/tag-values 2>/dev/null | \
      EXPECTED="$expected" python3 -c 'import json,os,sys; d=json.load(sys.stdin); sys.exit(0 if any(v["value"]==float(os.environ["EXPECTED"]) and v["quality"]=="good" for v in d["values"]) else 1)' 2>/dev/null; then
      return 0
    fi
    kill -0 "$CONSUMER_PID" 2>/dev/null || { echo "Consumer died"; cat "/tmp/scada-mqtt-consumer-$$.log"; return 1; }
    sleep 0.2
  done
  echo "Timed out waiting for value $expected"
  cat "/tmp/scada-mqtt-consumer-$$.log"
  return 1
}
# Publish repeatedly until the consumer has subscribed.
for i in $(seq 1 30); do
  publish 21.5
  if curl -fsS http://127.0.0.1:18080/api/tag-values 2>/dev/null | \
    python3 -c 'import json,sys; sys.exit(0 if any(v["value"]==21.5 for v in json.load(sys.stdin)["values"]) else 1)' 2>/dev/null; then break; fi
  sleep 0.3
done
await_value 21.5
docker stop "$BROKER" >/dev/null
sleep 2
kill -0 "$CONSUMER_PID"
docker start "$BROKER" >/dev/null
for i in $(seq 1 100); do
  publish 23.1 2>/dev/null || true
  if curl -fsS http://127.0.0.1:18080/api/tag-values 2>/dev/null | \
    python3 -c 'import json,sys; sys.exit(0 if any(v["value"]==23.1 for v in json.load(sys.stdin)["values"]) else 1)' 2>/dev/null; then break; fi
  sleep 0.3
done
await_value 23.1
kill -TERM "$CONSUMER_PID"
wait "$CONSUMER_PID"
CONSUMER_PID=""
grep -q 'MQTT consumer stopped.' "/tmp/scada-mqtt-consumer-$$.log"
echo "MQTT broker restart, telemetry recovery and graceful shutdown: OK"
rm -f "/tmp/scada-mqtt-consumer-$$.log" "/tmp/scada-mqtt-http-$$.log"
