<?php

declare(strict_types=1);

namespace App\Uns;

/**
 * Accumule des trames MQTT/UNS et en tire des statistiques par tag (type, plage, cadence, compteur).
 * Port PHP de tools/uns-infer/lib.mjs : les deux doivent produire le même modèle.
 *
 * Deux formes de trame :
 *  - un topic par tag : {…asset}/{tag}, payload {value,…} ou scalaire ;
 *  - un topic par asset : {…asset}, payload {"tags":{nom:{value,…}}}.
 */
final class UnsCollector
{
    public const MAX_TAGS = 2000;
    private const MAX_TIMESTAMPS = 200;
    private const MAX_DISTINCT = 20;

    /** @var array<string, array<string, mixed>> */
    private array $tags = [];
    private int $frames = 0;

    public function frames(): int
    {
        return $this->frames;
    }

    /** @return array<string, array<string, mixed>> */
    public function tags(): array
    {
        return $this->tags;
    }

    public function addFrame(string $topic, string $payload, ?float $receivedAtMs = null): void
    {
        ++$this->frames;
        $receivedAtMs ??= microtime(true) * 1000;
        foreach (self::parseFrame($topic, $payload, $receivedAtMs) as $sample) {
            $this->add($sample);
        }
    }

    /**
     * @return list<array{path: list<string>, tag: string, value: mixed, unit: ?string, quality: string, ts: float}>
     */
    public static function parseFrame(string $topic, string $text, float $receivedAtMs): array
    {
        $segments = array_values(array_filter(explode('/', $topic), static fn (string $s): bool => $s !== ''));
        if (count($segments) < 2) {
            return [];
        }
        foreach ($segments as $segment) {
            // _status, $SYS… : métadonnées de transport, pas des tags.
            if ($segment[0] === '_' || $segment[0] === '$') {
                return [];
            }
        }
        $payload = self::parsePayload($text);
        if ($payload === null) {
            return [];
        }

        $stamp = $payload['observedAt'] ?? $payload['timestamp'] ?? null;
        $ts = is_string($stamp) ? (self::parseDate($stamp) ?? $receivedAtMs) : $receivedAtMs;

        $sample = static fn (array $path, string $tag, array $o): array => [
            'path' => $path,
            'tag' => $tag,
            'value' => $o['value'],
            'unit' => isset($o['unit']) && is_string($o['unit']) ? $o['unit'] : null,
            'quality' => isset($o['quality']) && is_string($o['quality']) ? $o['quality'] : 'good',
            'ts' => $ts,
        ];

        if (isset($payload['tags']) && is_array($payload['tags'])) {
            $samples = [];
            foreach ($payload['tags'] as $name => $o) {
                if (is_array($o) && array_key_exists('value', $o) && $name !== '' && !str_contains((string) $name, '/')) {
                    $samples[] = $sample($segments, (string) $name, $o);
                }
            }

            return $samples;
        }
        if (!array_key_exists('value', $payload)) {
            return [];
        }

        return [$sample(array_slice($segments, 0, -1), $segments[count($segments) - 1], $payload)];
    }

    /** @return array<string, mixed>|null */
    private static function parsePayload(string $text): ?array
    {
        try {
            $v = json_decode($text, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $s = trim($text);
            if ($s === '') {
                return null;
            }
            if (is_numeric($s)) {
                return ['value' => $s + 0];
            }
            if ($s === 'true' || $s === 'false') {
                return ['value' => $s === 'true'];
            }

            return ['value' => $s];
        }

        return is_array($v) && !array_is_list($v) ? $v : ['value' => $v];
    }

    private static function parseDate(string $value): ?float
    {
        try {
            $date = new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }

        return (float) $date->format('U') * 1000 + (int) $date->format('v');
    }

    /** @param array{path: list<string>, tag: string, value: mixed, unit: ?string, quality: string, ts: float} $s */
    private function add(array $s): void
    {
        $value = $s['value'];
        if ($value === null) {
            return;
        }
        $key = implode('/', [...$s['path'], $s['tag']]);
        if (!isset($this->tags[$key])) {
            if (count($this->tags) >= self::MAX_TAGS) {
                return; // borne mémoire : on ignore les nouveaux tags au-delà
            }
            $this->tags[$key] = [
                'path' => $s['path'], 'tag' => $s['tag'], 'unit' => null, 'n' => 0, 'bad' => 0, 'types' => [],
                'min' => INF, 'max' => -INF, 'inc' => 0, 'dec' => 0, 'last' => null, 'distinct' => [], 'tsList' => [],
            ];
        }
        $t = &$this->tags[$key];
        ++$t['n'];
        if ($s['unit'] !== null && $s['unit'] !== '') {
            $t['unit'] = $s['unit'];
        }
        if ($s['quality'] !== 'good') {
            ++$t['bad']; // une valeur douteuse ne doit pas fausser plages ni types

            return;
        }
        $type = match (true) {
            is_bool($value) => 'boolean',
            is_int($value), is_float($value) => 'number',
            is_string($value) => 'string',
            default => 'object',
        };
        if ($type === 'object') {
            return;
        }
        $t['types'][$type] = true;
        if ($type === 'number') {
            $t['min'] = min($t['min'], $value);
            $t['max'] = max($t['max'], $value);
            if (is_int($t['last']) || is_float($t['last'])) {
                if ($value > $t['last']) {
                    ++$t['inc'];
                } elseif ($value < $t['last']) {
                    ++$t['dec'];
                }
            }
        } elseif (count($t['distinct']) <= self::MAX_DISTINCT) {
            $t['distinct'][(string) (is_bool($value) ? ($value ? 'true' : 'false') : $value)] = true;
        }
        $t['last'] = $value;
        if (count($t['tsList']) < self::MAX_TIMESTAMPS) {
            $t['tsList'][] = $s['ts'];
        }
    }
}
