<?php
declare(strict_types=1);

namespace App\Tests\Telemetry;

use App\Entity\DataSource;
use App\Entity\Tag;
use App\Entity\TagValue;
use App\Entity\Workspace;
use App\Telemetry\TelemetryIngestor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TelemetryIngestorIntegrationTest extends KernelTestCase
{
    public function testSourceStatusOrderingAndTypeValidation(): void
    {
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $ingestor = static::getContainer()->get(TelemetryIngestor::class);
        if (self::$kernel->getEnvironment() !== 'test'
            || $em->getConnection()->getDatabasePlatform()->getName() !== 'postgresql'
            || !str_ends_with((string) $em->getConnection()->getDatabase(), '_test')) {
            self::markTestSkipped('Requires a dedicated test database.');
        }
        $workspace = new Workspace('Integration', 'integration-'.bin2hex(random_bytes(6)));
        $source = new DataSource($workspace, 'plc', 'PLC', 'mqtt');
        $tag = new Tag($source, 'temperature', 'Temperature', 'float');
        foreach ([$workspace, $source, $tag] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
        try {
            $topic = 'scada/'.$workspace->slug.'/plc/temperature';
            self::assertFalse($ingestor->ingest($topic, '{"value":20}'));
            $source->enabled = true;
            $em->flush();
            self::assertTrue($ingestor->ingest($topic, '{"value":21.5,"observedAt":"2026-11-03T16:00:02Z"}'));
            self::assertFalse($ingestor->ingest($topic, '{"value":18,"observedAt":"2026-11-03T16:00:01Z"}'));
            self::assertFalse($ingestor->ingest($topic, '{"value":"invalid"}'));
            $em->clear();
            $saved = $em->find(TagValue::class, $tag->id);
            self::assertNotNull($saved);
            self::assertSame(21.5, $saved->value);
        } finally {
            $em->getConnection()->executeStatement('DELETE FROM tag_value WHERE tag_id = :id', ['id' => $tag->id]);
            $em->getConnection()->executeStatement('DELETE FROM tag WHERE id = :id', ['id' => $tag->id]);
            $em->getConnection()->executeStatement('DELETE FROM data_source WHERE id = :id', ['id' => $source->id]);
            $em->getConnection()->executeStatement('DELETE FROM workspace WHERE id = :id', ['id' => $workspace->id]);
        }
    }
}
