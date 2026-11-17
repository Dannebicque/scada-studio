<?php
declare(strict_types=1);

namespace App\Tests\Telemetry;

use App\Entity\DataSource;
use App\Entity\Tag;
use App\Entity\TagValue;
use App\Entity\Workspace;
use App\Telemetry\MqttSubscriptionResolver;
use App\Telemetry\TelemetryIngestor;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class UnsIngestionIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    /** @var list<string> */
    private array $workspaceIds = [];

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
        $db = $this->em->getConnection();
        foreach ($this->workspaceIds as $id) {
            $db->executeStatement('DELETE FROM tag_value WHERE tag_id IN (SELECT t.id FROM tag t JOIN data_source s ON s.id = t.source_id WHERE s.workspace_id = :id)', ['id' => $id]);
            $db->executeStatement('DELETE FROM tag WHERE source_id IN (SELECT id FROM data_source WHERE workspace_id = :id)', ['id' => $id]);
            $db->executeStatement('DELETE FROM data_source WHERE workspace_id = :id', ['id' => $id]);
            $db->executeStatement('DELETE FROM workspace WHERE id = :id', ['id' => $id]);
        }
        parent::tearDown();
    }

    private function workspace(): Workspace
    {
        $workspace = new Workspace('UNS integration', 'uns-it-'.bin2hex(random_bytes(6)));
        $this->em->persist($workspace);
        $this->em->flush();
        $this->workspaceIds[] = $workspace->id;

        return $workspace;
    }

    public function testUnsTopicIngestionAndSubscriptionFilters(): void
    {
        $workspace = $this->workspace();
        $prefix = 'ituns'.bin2hex(random_bytes(4)).'/line1/filler';
        $source = new DataSource($workspace, 'filler', 'Filler', 'mqtt');
        $source->topicPrefix = $prefix;
        $tag = new Tag($source, 'temperature', 'Temperature', 'float');
        $this->em->persist($source);
        $this->em->persist($tag);
        $this->em->flush();

        $ingestor = static::getContainer()->get(TelemetryIngestor::class);
        $resolver = static::getContainer()->get(MqttSubscriptionResolver::class);
        $topic = $prefix.'/temperature';

        // Source désactivée : ni ingestion, ni abonnement.
        self::assertFalse($ingestor->ingest($topic, '{"value":20}'));
        self::assertNotContains($prefix.'/+', $resolver->topicFilters());

        $source->enabled = true;
        $this->em->flush();
        self::assertContains(MqttSubscriptionResolver::LEGACY_FILTER, $resolver->topicFilters());
        self::assertContains($prefix.'/+', $resolver->topicFilters());
        self::assertNotContains('#', $resolver->topicFilters());

        self::assertTrue($ingestor->ingest($topic, '{"value":61.5,"observedAt":"2026-11-03T16:00:02Z","unit":"°C","quality":"good"}'));
        self::assertFalse($ingestor->ingest($topic, '{"value":50,"observedAt":"2026-11-03T16:00:01Z"}'), 'horodatage plus ancien');
        self::assertFalse($ingestor->ingest($topic, '{"value":99,"observedAt":"2026-11-03T16:00:09Z","quality":"excellent"}'), 'qualité inconnue');
        self::assertFalse($ingestor->ingest($prefix.'/inconnu', '{"value":1}'), 'tag inconnu');
        self::assertFalse($ingestor->ingest('autre/prefixe/temperature', '{"value":1}'), 'préfixe inconnu');
        self::assertFalse($ingestor->ingest($topic, '{"value":"texte"}'), 'type invalide pour un tag float');

        $this->em->clear();
        $saved = $this->em->find(TagValue::class, $tag->id);
        self::assertNotNull($saved);
        self::assertSame(61.5, $saved->value);
        self::assertSame('good', $saved->quality);

        // Une mesure de mauvaise qualité est conservée avec sa qualité, pas ignorée.
        self::assertTrue($ingestor->ingest($topic, '{"value":99.5,"observedAt":"2026-11-03T16:00:09Z","quality":"bad"}'));
        $this->em->clear();
        $saved = $this->em->find(TagValue::class, $tag->id);
        self::assertSame('bad', $saved->quality);
        self::assertSame(99.5, $saved->value);
    }

    public function testTopicPrefixIsUniqueAcrossWorkspaces(): void
    {
        $prefix = 'ituniq'.bin2hex(random_bytes(4)).'/line1';
        $a = new DataSource($this->workspace(), 'one', 'One', 'mqtt');
        $a->topicPrefix = $prefix;
        $this->em->persist($a);
        $this->em->flush();

        $this->expectException(UniqueConstraintViolationException::class);
        $this->em->getConnection()->executeStatement(
            'INSERT INTO data_source (id, workspace_id, source_key, name, connector_type, enabled, topic_prefix) VALUES (:id, :ws, :k, :n, :c, false, :p)',
            ['id' => bin2hex(random_bytes(16)), 'ws' => $this->workspace()->id, 'k' => 'two', 'n' => 'Two', 'c' => 'mqtt', 'p' => $prefix],
        );
    }

    public function testProvisionCommandCreatesSourcesAndTagsIdempotently(): void
    {
        $workspace = $this->workspace();
        $root = 'itprov'.bin2hex(random_bytes(4)).'/site';
        $model = [
            'root' => $root,
            'assets' => [
                ['topic' => $root.'/area/line1/filler', 'path' => ['area', 'line1', 'filler'], 'tags' => [
                    ['name' => 'temperature', 'kind' => 'number', 'unit' => '°C'],
                    ['name' => 'running', 'kind' => 'boolean'],
                    ['name' => 'state', 'kind' => 'string'],
                    ['name' => 'bad tag!', 'kind' => 'number'],
                    ['name' => 'mystery', 'kind' => 'unknown'],
                ]],
                ['topic' => 'scada/reserved/x', 'path' => ['x'], 'tags' => [['name' => 't', 'kind' => 'number']]],
            ],
        ];
        $file = tempnam(sys_get_temp_dir(), 'uns-model');
        file_put_contents($file, json_encode($model));

        try {
            $tester = new CommandTester((new Application(self::$kernel))->find('app:uns:provision'));
            self::assertSame(0, $tester->execute(['workspace' => $workspace->slug, 'model' => $file]));
            self::assertStringContainsString('1 source(s) and 3 tag(s) created', $tester->getDisplay());

            $this->em->clear();
            $source = $this->em->getRepository(DataSource::class)->findOneBy(['topicPrefix' => $root.'/area/line1/filler']);
            self::assertNotNull($source);
            self::assertSame('area-line1-filler', $source->key);
            self::assertFalse($source->enabled, 'les sources provisionnées sont désactivées par défaut');
            $types = [];
            foreach ($this->em->getRepository(Tag::class)->findBy(['source' => $source]) as $t) {
                $types[$t->key] = $t->valueType;
            }
            ksort($types);
            self::assertSame(['running' => 'boolean', 'state' => 'string', 'temperature' => 'float'], $types);

            self::assertSame(0, $tester->execute(['workspace' => $workspace->slug, 'model' => $file]));
            self::assertStringContainsString('0 source(s) and 0 tag(s) created', $tester->getDisplay());
        } finally {
            @unlink($file);
        }
    }

    public function testProvisionRejectsUnknownWorkspace(): void
    {
        $tester = new CommandTester((new Application(self::$kernel))->find('app:uns:provision'));
        self::assertSame(1, $tester->execute(['workspace' => 'does-not-exist', 'model' => '/nonexistent.json']));
    }
}
