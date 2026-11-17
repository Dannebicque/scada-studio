<?php

declare(strict_types=1);

namespace App\Telemetry;

use Doctrine\DBAL\Connection;

/**
 * Historique des valeurs numériques (et booléennes, en 0/1) des tags : écriture à cadence limitée,
 * lecture agrégée par intervalles, purge par ancienneté. Les chaînes ne sont pas historisées.
 */
final class TelemetryHistory
{
    /** Au plus un échantillon toutes les N secondes par tag, sauf changement de valeur. */
    public const MIN_INTERVAL_SECONDS = 10;
    public const DEFAULT_RETENTION_DAYS = 7;
    public const MAX_POINTS = 300;
    public const MIN_POINTS = 10;

    public function __construct(private readonly Connection $db)
    {
    }

    /** Valeur numérique historisable, ou null (chaîne, NaN, infini…). */
    public static function toNumber(mixed $value): ?float
    {
        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }
        if ((is_int($value) || is_float($value)) && is_finite((float) $value)) {
            return (float) $value;
        }

        return null;
    }

    /** Écrire si la valeur change, au premier échantillon, ou si l'intervalle minimal est écoulé. */
    public static function shouldStore(mixed $previousValue, ?\DateTimeImmutable $sampledAt, mixed $value, \DateTimeImmutable $observedAt, int $minInterval = self::MIN_INTERVAL_SECONDS): bool
    {
        return $sampledAt === null
            || $previousValue !== $value
            || $observedAt->getTimestamp() - $sampledAt->getTimestamp() >= $minInterval;
    }

    public static function bucketSeconds(\DateTimeImmutable $from, \DateTimeImmutable $to, int $maxPoints): int
    {
        $maxPoints = max(self::MIN_POINTS, min(self::MAX_POINTS, $maxPoints));

        return max(1, (int) ceil(($to->getTimestamp() - $from->getTimestamp()) / $maxPoints));
    }

    /** @param array<string, mixed> $env */
    public static function retentionDays(array $env): int
    {
        $raw = $env['TELEMETRY_RETENTION_DAYS'] ?? null;

        return is_string($raw) && ctype_digit($raw) && (int) $raw >= 1 && (int) $raw <= 365 ? (int) $raw : self::DEFAULT_RETENTION_DAYS;
    }

    public function record(string $tagId, mixed $value, \DateTimeImmutable $observedAt): bool
    {
        $number = self::toNumber($value);
        if ($number === null) {
            return false;
        }

        return $this->db->executeStatement(
            'INSERT INTO tag_sample (tag_id, observed_at, value) VALUES (:tag, :at, :value) ON CONFLICT (tag_id, observed_at) DO NOTHING',
            ['tag' => $tagId, 'at' => $observedAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'), 'value' => $number],
        ) > 0;
    }

    public function prune(int $days): int
    {
        $limit = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('-'.$days.' days');

        return (int) $this->db->executeStatement('DELETE FROM tag_sample WHERE observed_at < :limit', ['limit' => $limit->format('Y-m-d H:i:s')]);
    }

    /**
     * Série agrégée par intervalles réguliers (moyenne, minimum, maximum), jamais plus de MAX_POINTS points.
     *
     * @return array{bucketSeconds: int, points: list<array{t: string, avg: float, min: float, max: float, n: int}>}
     */
    public function series(string $tagId, \DateTimeImmutable $from, \DateTimeImmutable $to, int $maxPoints): array
    {
        $bucket = self::bucketSeconds($from, $to, $maxPoints);
        $rows = $this->db->fetchAllAssociative(
            "SELECT EXTRACT(EPOCH FROM date_bin(make_interval(secs => CAST(:bucket AS double precision)), observed_at, TIMESTAMP '2000-01-01 00:00:00')) AS t,
                    AVG(value) AS avg, MIN(value) AS min, MAX(value) AS max, COUNT(*) AS n
             FROM tag_sample
             WHERE tag_id = :tag AND observed_at >= :from AND observed_at <= :to
             GROUP BY 1 ORDER BY 1",
            ['bucket' => $bucket, 'tag' => $tagId, 'from' => $from->format('Y-m-d H:i:s'), 'to' => $to->format('Y-m-d H:i:s')],
        );

        $points = [];
        foreach ($rows as $row) {
            $points[] = [
                't' => gmdate('Y-m-d\TH:i:s', (int) $row['t']).'Z',
                'avg' => round((float) $row['avg'], 6),
                'min' => (float) $row['min'],
                'max' => (float) $row['max'],
                'n' => (int) $row['n'],
            ];
        }

        return ['bucketSeconds' => $bucket, 'points' => $points];
    }
}
