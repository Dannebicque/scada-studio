<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\DataSource;
use App\Entity\Tag;
use App\Entity\Unit;
use App\Entity\Workspace;
use PHPUnit\Framework\TestCase;

final class TagTest extends TestCase
{
    public function testTagIsReadOnlyByDefault(): void
    {
        $workspace = new Workspace('Demo', 'demo');
        $source = new DataSource($workspace, 'plc1', 'PLC 1', 'mqtt');
        $unit = new Unit('Cel', 'degree Celsius', '°C');
        $tag = new Tag($source, 'temperature', 'Temperature', 'float', $unit);

        self::assertTrue($tag->readOnly);
        self::assertFalse($source->enabled);
        self::assertSame($unit, $tag->unit);
        self::assertSame($workspace, $source->workspace);
    }

    public function testUnsupportedTypeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $workspace = new Workspace('Demo', 'demo');
        $source = new DataSource($workspace, 'plc1', 'PLC 1', 'mqtt');
        new Tag($source, 'temperature', 'Temperature', 'object');
    }
}
