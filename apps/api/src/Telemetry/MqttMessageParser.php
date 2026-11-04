<?php

declare(strict_types=1);

namespace App\Telemetry;

/**
 * Validates the transport envelope independently of Doctrine and the MQTT client.
 * Domain validation (source enabled, tag type, ordering) remains in TelemetryIngestor.
 */
final class MqttMessageParser
{
    /** @return array{workspace: string, source: string, tag: string, value: mixed, observedAt: \DateTimeImmutable}|null */
    public function parse(string $topic, string $payload): ?array
    {
        if (strlen($topic) > 512 || strlen($payload) > 4096) {
            return null;
        }

        $parts = explode('/', $topic);
        if (count($parts) !== 4 || $parts[0] !== 'scada') {
            return null;
        }
        foreach (array_slice($parts, 1) as $segment) {
            if (!preg_match('/^[a-zA-Z0-9_-]{1,100}$/D', $segment)) {
                return null;
            }
        }

        try {
            $message = json_decode($payload, false, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!($message instanceof \stdClass) || !property_exists($message, 'value')) {
            return null;
        }

        $observedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if (property_exists($message, 'observedAt')) {
            if (!is_string($message->observedAt) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/D', $message->observedAt)) {
                return null;
            }
            try {
                $observedAt = (new \DateTimeImmutable($message->observedAt))->setTimezone(new \DateTimeZone('UTC'));
                if (\DateTimeImmutable::getLastErrors() !== false) {
                    return null;
                }
            } catch (\Exception) {
                return null;
            }
            if ($observedAt->getTimestamp() > time() + 300) {
                return null;
            }
        }

        return [
            'workspace' => $parts[1],
            'source' => $parts[2],
            'tag' => $parts[3],
            'value' => $message->value,
            'observedAt' => $observedAt,
        ];
    }
}
