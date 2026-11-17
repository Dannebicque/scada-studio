<?php
declare(strict_types=1);

namespace App\Tests\Telemetry;

use App\Entity\DataSource;
use App\Entity\Tag;
use App\Entity\Workspace;
use App\Telemetry\TelemetryHistory;
use App\Telemetry\TelemetryIngestor;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TelemetryHistoryIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ?string $workspaceId = null;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        if (self::$kernel->getEnvironment() !== 'test'
            || !($this->em->getConnection()->getDatabasePlatform() instanceof PostgreSQLPlatform)
            || !str_ends_with((string) $this->em->getConnection()->getDatabase(), '_test')) {
            self::fail('Integration tests require APP_ENV=test and a dedicated PostgreSQL _test database.');
        }
    }

    protected function tearDown(): void
    {
        if ($this->workspaceId !== null) {
            $db = $this->em->getConnection();
            $db->executeStatement('DELETE FROM tag_value WHERE tag_id IN (SELECT t.id FROM tag t JOIN data_source s ON s.id = t.source_id WHERE s.workspace_id = :id)', ['id' => $this->workspaceId]);
            $db->executeStatement('DELETE FROM tag WHERE source_id IN (SELECT id FROM data_source WHERE workspace_id = :id)', ['id' => $this->workspaceId]);
            $db->executeStatement('DELETE FROM data_source WHERE workspace_id = :id', ['id' => $this->workspaceId]);
            $db->executeStatement('DELETE FROM workspace WHERE id = :id', ['id' => $this->workspaceId]);
        }
        parent::tearDown();
    }

    /** @return array{0: Tag, 1: string} tag et topic */
    private function tag(string $type): array
    {
        $workspace = new Workspace('History integration', 'hist-it-'.bin2hex(random_bytes(6)));
        $this->em->persist($workspace);
        $this->em->flush();
        $this->workspaceId = $workspace->id;
        $prefix = 'ithist'.bin2hex(random_bytes(4)).'/line1/filler';
        $source = new DataSource($workspace, 'filler', 'Filler', 'mqtt');
        $source->topicPrefix = $prefix;
        $source->enabled = true;
        $tag = new Tag($source, 'temperature', 'Temperature', $type);
        $this->em->persist($source);
        $this->em->persist($tag);
        $this->em->flush();

        return [$tag, $prefix.'/temperature'];
    }

    private function count(string $tagId): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM tag_sample WHERE tag_id = :id', ['id' => $tagId]);
    }

    public function testHistoryIsWrittenOnChangeOrAfterTheMinimalInterval(): void
    {
        [$tag, $topic] = $this->tag('float');
        $ingestor = static::getContainer()->get(TelemetryIngestor::class);

        self::assertTrue($ingestor->ingest($topic, '{"value":20.0,"observedAt":"2026-11-03T16:00:00Z"}'));
        self::assertTrue($ingestor->ingest($topic, '{"value":20.0,"observedAt":"2026-11-03T16:00:03Z"}'), 'valeur courante mise à jour');
        self::assertSame(1, $this->count($tag->id), 'inchangée et trop proche : pas d\'échantillon');
        self::assertTrue($ingestor->ingest($topic, '{"value":21.5,"observedAt":"2026-11-03T16:00:04Z"}'));
        self::assertSame(2, $this->count($tag->id), 'valeur changée : échantillon');
        self::assertTrue($ingestor->ingest($topic, '{"value":21.5,"observedAt":"2026-11-03T16:00:30Z"}'));
        self::assertSame(3, $this->count($tag->id), 'intervalle écoulé : échantillon');
    }

    public function testOnlyGoodQualityFeedsTheHistory(): void
    {
        [$tag, $topic] = $this->tag('float');
        $ingestor = static::getContainer()->get(TelemetryIngestor::class);

        self::assertTrue($ingestor->ingest($topic, '{"value":20.0,"observedAt":"2026-11-03T16:00:00Z"}'));
        self::assertTrue($ingestor->ingest($topic, '{"value":9999.0,"observedAt":"2026-11-03T16:00:20Z","quality":"bad"}'));
        self::assertTrue($ingestor->ingest($topic, '{"value":21.0,"observedAt":"2026-11-03T16:00:21Z","quality":"uncertain"}'));
        self::assertSame(1, $this->count($tag->id), 'seule la mesure good est historisée');
        self::assertTrue($ingestor->ingest($topic, '{"value":22.0,"observedAt":"2026-11-03T16:00:40Z","quality":"good"}'));
        self::assertSame(2, $this->count($tag->id));
    }

    public function testBooleanTagsAreStoredAsZeroOrOneAndStringsAreNot(): void
    {
        [$tag, $topic] = $this->tag('boolean');
        $ingestor = static::getContainer()->get(TelemetryIngestor::class);
        self::assertTrue($ingestor->ingest($topic, '{"value":true,"observedAt":"2026-11-03T16:00:00Z"}'));
        self::assertTrue($ingestor->ingest($topic, '{"value":false,"observedAt":"2026-11-03T16:00:02Z"}'));
        self::assertSame([1.0, 0.0], array_map('floatval', $this->em->getConnection()->fetchFirstColumn('SELECT value FROM tag_sample WHERE tag_id = :id ORDER BY observed_at', ['id' => $tag->id])));
    }

    public function testSeriesAggregatesAndPruneRemovesOldSamples(): void
    {
        [$tag] = $this->tag('float');
        $history = static::getContainer()->get(TelemetryHistory::class);
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        for ($i = 0; $i < 60; ++$i) {
            $history->record($tag->id, 20 + ($i % 10), $now->modify('-'.(3600 - $i * 60).' seconds'));
        }
        $history->record($tag->id, 5, $now->modify('-10 days'));

        $series = $history->series($tag->id, $now->modify('-1 hour'), $now, 60);

        self::assertSame(60, $series['bucketSeconds']);
        self::assertGreaterThanOrEqual(59, count($series['points']));
        self::assertLessThanOrEqual(61, count($series['points']));
        self::assertSame(20.0, min(array_column($series['points'], 'min')));
        self::assertSame(29.0, max(array_column($series['points'], 'max')));
        self::assertGreaterThanOrEqual(1, $history->prune(7));
        self::assertSame(60, $this->count($tag->id));
    }
}
