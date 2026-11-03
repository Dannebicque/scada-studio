<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\DataSource;
use App\Entity\Tag;
use App\Entity\TagValue;
use App\Entity\Workspace;
use PHPUnit\Framework\TestCase;

final class TagValueTest extends TestCase
{
    public function testRejectsWrongType(): void
    {
        $tag = new Tag(new DataSource(new Workspace('Demo', 'demo'), 'plc', 'PLC', 'mqtt'), 'running', 'Running', 'boolean');
        $this->expectException(\InvalidArgumentException::class);
        new TagValue($tag, 'true');
    }

    public function testStoresTimestampedValue(): void
    {
        $tag = new Tag(new DataSource(new Workspace('Demo', 'demo'), 'plc', 'PLC', 'mqtt'), 'temperature', 'Temperature', 'float');
        $sample = new TagValue($tag, 21.4);
        self::assertSame(21.4, $sample->value);
        self::assertInstanceOf(\DateTimeImmutable::class, $sample->observedAt);
    }
}
