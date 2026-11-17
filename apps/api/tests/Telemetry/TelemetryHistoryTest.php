<?php

declare(strict_types=1);

namespace App\Tests\Telemetry;

use App\Telemetry\TelemetryHistory;
use PHPUnit\Framework\TestCase;

final class TelemetryHistoryTest extends TestCase
{
    private static function at(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-11-17T10:'.$time.'Z');
    }

    public function testNumbersAndBooleansAreHistorisedStringsAreNot(): void
    {
        self::assertSame(21.5, TelemetryHistory::toNumber(21.5));
        self::assertSame(3.0, TelemetryHistory::toNumber(3));
        self::assertSame(1.0, TelemetryHistory::toNumber(true));
        self::assertSame(0.0, TelemetryHistory::toNumber(false));
        self::assertNull(TelemetryHistory::toNumber('RUNNING'));
        self::assertNull(TelemetryHistory::toNumber(NAN));
        self::assertNull(TelemetryHistory::toNumber(INF));
        self::assertNull(TelemetryHistory::toNumber(null));
    }

    public function testWritesOnFirstSampleChangeOrElapsedInterval(): void
    {
        self::assertTrue(TelemetryHistory::shouldStore(null, null, 20.0, self::at('00:00')), 'premier échantillon');
        self::assertTrue(TelemetryHistory::shouldStore(20.0, self::at('00:00'), 20.5, self::at('00:01')), 'valeur changée');
        self::assertFalse(TelemetryHistory::shouldStore(20.0, self::at('00:00'), 20.0, self::at('00:05')), 'inchangée, intervalle non écoulé');
        self::assertTrue(TelemetryHistory::shouldStore(20.0, self::at('00:00'), 20.0, self::at('00:10')), 'intervalle écoulé');
        self::assertTrue(TelemetryHistory::shouldStore(20.0, null, 20.0, self::at('00:01')), 'ancienne ligne sans sampledAt');
    }

    public function testBucketSizeBoundsThePointCount(): void
    {
        $to = self::at('00:00');

        self::assertSame(30, TelemetryHistory::bucketSeconds($to->modify('-1 hour'), $to, 120));
        self::assertSame(2016, TelemetryHistory::bucketSeconds($to->modify('-7 days'), $to, 300));
        self::assertSame(1, TelemetryHistory::bucketSeconds($to->modify('-5 seconds'), $to, 120));
        self::assertSame(360, TelemetryHistory::bucketSeconds($to->modify('-1 hour'), $to, 5), 'plancher de 10 points');
        self::assertSame(12, TelemetryHistory::bucketSeconds($to->modify('-1 hour'), $to, 100000), 'plafond de 300 points');
    }

    public function testRetentionDefaultsAndBounds(): void
    {
        self::assertSame(7, TelemetryHistory::retentionDays([]));
        self::assertSame(30, TelemetryHistory::retentionDays(['TELEMETRY_RETENTION_DAYS' => '30']));
        self::assertSame(7, TelemetryHistory::retentionDays(['TELEMETRY_RETENTION_DAYS' => '0']));
        self::assertSame(7, TelemetryHistory::retentionDays(['TELEMETRY_RETENTION_DAYS' => '9999']));
        self::assertSame(7, TelemetryHistory::retentionDays(['TELEMETRY_RETENTION_DAYS' => 'abc']));
    }
}
