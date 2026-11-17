<?php

declare(strict_types=1);

namespace App\Tests\Uns;

use App\Uns\UnsCollector;
use App\Uns\UnsModelBuilder;
use PHPUnit\Framework\TestCase;

final class UnsInferenceTest extends TestCase
{
    private const ASSET = 'acme/troyes/packaging/line1/filler';

    private function feed(UnsCollector $collector, string $tag, array $values, string $unit = '', int $stepMs = 1000): void
    {
        $t0 = strtotime('2026-11-17T10:00:00Z') * 1000;
        foreach ($values as $i => $value) {
            $payload = ['value' => $value, 'observedAt' => gmdate('Y-m-d\TH:i:s', intdiv($t0 + $i * $stepMs, 1000)).'Z', 'quality' => 'good'];
            if ($unit !== '') {
                $payload['unit'] = $unit;
            }
            $collector->addFrame(self::ASSET.'/'.$tag, json_encode($payload), 0.0);
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function tagsByName(array $model): array
    {
        $byName = [];
        foreach ($model['assets'][0]['tags'] as $tag) {
            $byName[$tag['name']] = $tag;
        }

        return $byName;
    }

    public function testClassifiesTypesAndProposesWidgets(): void
    {
        $c = new UnsCollector();
        $this->feed($c, 'running', [true, false, true]);
        $this->feed($c, 'fault_alarm', [false, false, false]);
        $this->feed($c, 'state', ['RUN', 'STOP', 'RUN']);
        $this->feed($c, 'temperature', [20.5, 21.7, 19.2, 22.0], '°C');
        $this->feed($c, 'fill_level', [10, 30, 20, 40], '%');
        $this->feed($c, 'setpoint', [50, 50, 50]);
        $this->feed($c, 'pieces', [1, 2, 3, 4, 5]);

        $model = UnsModelBuilder::buildModel($c);
        $tags = $this->tagsByName($model);

        self::assertSame('acme/troyes/packaging/line1', $model['root']);
        self::assertSame(['filler'], $model['assets'][0]['path']);
        self::assertSame('core.status', $tags['running']['widget']);
        self::assertSame('core.alarm', $tags['fault_alarm']['widget']);
        self::assertSame('core.state', $tags['state']['widget']);
        self::assertSame(['RUN', 'STOP'], $tags['state']['values']);
        self::assertSame('core.timeseries', $tags['temperature']['widget']);
        self::assertSame('°C', $tags['temperature']['unit']);
        self::assertSame('core.gauge', $tags['fill_level']['widget']);
        self::assertSame('constant', $tags['setpoint']['kind']);
        self::assertSame('counter', $tags['pieces']['kind']);
        self::assertSame(1.0, $tags['temperature']['intervalSeconds']);
        self::assertSame(5, $tags['temperature']['staleAfterSeconds']);
    }

    public function testBadQualitySamplesDoNotSkewTheModel(): void
    {
        $c = new UnsCollector();
        $c->addFrame(self::ASSET.'/pressure', '{"value":5.0,"quality":"good"}', 0.0);
        $c->addFrame(self::ASSET.'/pressure', '{"value":9999,"quality":"bad"}', 0.0);
        $c->addFrame(self::ASSET.'/pressure', '{"value":6.0,"quality":"good"}', 0.0);

        $tag = $this->tagsByName(UnsModelBuilder::buildModel($c))['pressure'];

        self::assertSame(6.0, (float) $tag['max']);
        self::assertSame(1, $tag['badSamples']);
    }

    public function testSupportsAssetFramesAndIgnoresTransportTopics(): void
    {
        $c = new UnsCollector();
        $c->addFrame('acme/troyes/_status', 'online', 0.0);
        $c->addFrame('acme/troyes/packaging/line1/filler', '{"tags":{"speed":{"value":12.5,"unit":"m/min"},"on":{"value":true}}}', 0.0);
        $c->addFrame('$SYS/broker/uptime', '42', 0.0);

        $model = UnsModelBuilder::buildModel($c);

        self::assertCount(1, $model['assets']);
        $tags = $this->tagsByName($model);
        self::assertSame(['on', 'speed'], array_keys($tags));
        self::assertSame('m/min', $tags['speed']['unit']);
        self::assertSame(3, $model['frames']);
    }

    public function testBuildsOnePagePerAreaWithGridLayout(): void
    {
        $c = new UnsCollector();
        $this->feed($c, 'temperature', [1.0, 2.0, 3.0], '°C');
        $c->addFrame('acme/troyes/utilities/boiler/temperature', '{"value":80.1}', 0.0);
        $c->addFrame('acme/troyes/utilities/boiler/temperature', '{"value":81.0}', 0.0);

        $pages = UnsModelBuilder::buildPages(UnsModelBuilder::buildModel($c));

        self::assertCount(2, $pages);
        foreach ($pages as $page) {
            self::assertSame(1, $page['schemaVersion']);
            self::assertSame('dashboard', $page['kind']);
            foreach ($page['elements'] as $element) {
                self::assertLessThanOrEqual(12, $element['layout']['x'] + $element['layout']['width']);
            }
        }
    }

    public function testBoundsTheNumberOfTags(): void
    {
        $c = new UnsCollector();
        for ($i = 0; $i < UnsCollector::MAX_TAGS + 50; ++$i) {
            $c->addFrame('acme/site/asset/tag'.$i, '{"value":1}', 0.0);
        }

        self::assertCount(UnsCollector::MAX_TAGS, $c->tags());
    }
}
