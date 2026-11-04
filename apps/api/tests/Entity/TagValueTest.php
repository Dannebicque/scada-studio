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

    public function testRejectsNonFiniteFloat(): void
    {
        $tag = new Tag(new DataSource(new Workspace('Demo', 'demo'), 'plc', 'PLC', 'mqtt'), 'temperature', 'Temperature', 'float');
        $this->expectException(\InvalidArgumentException::class);
        new TagValue($tag, INF);
    }

    public function testRejectsFloatForIntegerTag(): void
    {
        $tag = new Tag(new DataSource(new Workspace('Demo', 'demo'), 'plc', 'PLC', 'mqtt'), 'count', 'Count', 'integer');
        $this->expectException(\InvalidArgumentException::class);
        new TagValue($tag, 1.5);
    }

    public function testAcceptsBooleanFalse(): void
    {
        $tag = new Tag(new DataSource(new Workspace('Demo', 'demo'), 'plc', 'PLC', 'mqtt'), 'running', 'Running', 'boolean');
        $sample = new TagValue($tag, false);
        self::assertFalse($sample->value);
    }

    public function testStoresTimestampedValue(): void
    {
        $tag = new Tag(new DataSource(new Workspace('Demo', 'demo'), 'plc', 'PLC', 'mqtt'), 'temperature', 'Temperature', 'float');
        $sample = new TagValue($tag, 21.4);
        self::assertSame(21.4, $sample->value);
        self::assertInstanceOf(\DateTimeImmutable::class, $sample->observedAt);
    }
}
