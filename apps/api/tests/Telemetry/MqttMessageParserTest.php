<?php

declare(strict_types=1);

namespace App\Tests\Telemetry;

use App\Telemetry\MqttMessageParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MqttMessageParserTest extends TestCase
{
    private const TOPIC = 'scada/atelier-demo/automate-01/temperature';

    public function testParsesValidFrame(): void
    {
        $message = (new MqttMessageParser())->parse(
            self::TOPIC,
            '{"value":22.4,"observedAt":"2026-11-03T16:00:00Z"}',
        );

        self::assertNotNull($message);
        self::assertSame('atelier-demo', $message['workspace']);
        self::assertSame('automate-01', $message['source']);
        self::assertSame('temperature', $message['tag']);
        self::assertSame(22.4, $message['value']);
        self::assertSame('2026-11-03T16:00:00+00:00', $message['observedAt']->format(\DateTimeInterface::ATOM));
    }

    public function testPreservesBooleanFalse(): void
    {
        $message = (new MqttMessageParser())->parse(self::TOPIC, '{"value":false}');
        self::assertNotNull($message);
        self::assertFalse($message['value']);
    }

    #[DataProvider('invalidFrames')]
    public function testRejectsInvalidFrame(string $topic, string $payload): void
    {
        self::assertNull((new MqttMessageParser())->parse($topic, $payload));
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidFrames(): iterable
    {
        yield 'wrong prefix' => ['other/atelier-demo/automate-01/temperature', '{"value":1}'];
        yield 'missing tag' => ['scada/atelier-demo/automate-01', '{"value":1}'];
        yield 'extra topic segment' => [self::TOPIC.'/extra', '{"value":1}'];
        yield 'empty topic segment' => ['scada//automate-01/temperature', '{"value":1}'];
        yield 'wildcard in topic' => ['scada/atelier-demo/+/temperature', '{"value":1}'];
        yield 'invalid json' => [self::TOPIC, '{'];
        yield 'missing value' => [self::TOPIC, '{"observedAt":"2026-11-03T16:00:00Z"}'];
        yield 'json scalar' => [self::TOPIC, '12'];
        yield 'json array' => [self::TOPIC, '[1,2]'];
        yield 'json array of object' => [self::TOPIC, '[{"value":1}]'];
        yield 'empty json object' => [self::TOPIC, '{}'];
        yield 'invalid calendar date' => [self::TOPIC, '{"value":1,"observedAt":"2026-02-30T12:00:00Z"}'];
        yield 'null timestamp' => [self::TOPIC, '{"value":1,"observedAt":null}'];
        yield 'invalid timestamp' => [self::TOPIC, '{"value":1,"observedAt":"yesterday"}'];
        yield 'future timestamp' => [self::TOPIC, '{"value":1,"observedAt":"2999-01-01T00:00:00Z"}'];
        yield 'oversized payload' => [self::TOPIC, str_repeat(' ', 4097)];
        yield 'oversized topic' => ['scada/'.str_repeat('a', 513).'/x/y', '{"value":1}'];
    }
}
