<?php

declare(strict_types=1);

namespace App\Telemetry;

/**
 * Validates the transport envelope independently of Doctrine and the MQTT client.
 * Domain validation (source enabled, tag type, ordering) remains in TelemetryIngestor.
 *
 * Two topic shapes are accepted:
 *  - legacy contract: scada/{workspace}/{source}/{tag} (exactly 4 segments);
 *  - UNS: {prefix…}/{tag} with 2 to 12 segments, resolved later through DataSource::$topicPrefix.
 * The "scada" first segment is reserved for the legacy contract.
 */
final class MqttMessageParser
{
    private const SEGMENT = '/^[a-zA-Z0-9_-]{1,100}$/D';
    private const MAX_SEGMENTS = 12;
    public const QUALITIES = ['good', 'uncertain', 'bad'];

    /** @return array{workspace: ?string, source: ?string, prefix: ?string, tag: string, value: mixed, observedAt: \DateTimeImmutable, quality: string}|null */
    public function parse(string $topic, string $payload): ?array
    {
        if (strlen($topic) > 512 || strlen($payload) > 4096) {
            return null;
        }

        $parts = explode('/', $topic);
        $count = count($parts);
        if ($count < 2 || $count > self::MAX_SEGMENTS) {
            return null;
        }
        foreach ($parts as $segment) {
            if (!preg_match(self::SEGMENT, $segment)) {
                return null;
            }
        }
        $legacy = $parts[0] === 'scada';
        if ($legacy && $count !== 4) {
            return null;
        }

        try {
            $message = json_decode($payload, false, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!($message instanceof \stdClass) || !property_exists($message, 'value')) {
            return null;
        }
        // Qualité de la mesure (vocabulaire OPC simplifié) : absente = « good ». Toute autre valeur est rejetée.
        $quality = property_exists($message, 'quality') ? $message->quality : 'good';
        if (!is_string($quality) || !in_array($quality, self::QUALITIES, true)) {
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
            'workspace' => $legacy ? $parts[1] : null,
            'source' => $legacy ? $parts[2] : null,
            'prefix' => $legacy ? null : implode('/', array_slice($parts, 0, -1)),
            'tag' => $parts[$count - 1],
            'value' => $message->value,
            'observedAt' => $observedAt,
            'quality' => $quality,
        ];
    }
}
