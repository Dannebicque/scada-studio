<?php
declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\DataSource;
use App\Entity\Tag;
use App\Entity\UnsAsset;
use App\Entity\UnsAssetTag;
use App\Entity\Workspace;
use PHPUnit\Framework\TestCase;

final class UnsAssetTest extends TestCase
{
    public function testHierarchyKeepsStableIdentity(): void
    {
        $workspace = new Workspace('Factory', 'factory');
        $site = new UnsAsset($workspace, 'site-a', 'Site A', 'site');
        $line = new UnsAsset($workspace, 'line-2', 'Line 2', 'line', $site);
        self::assertSame($site, $line->parent);
        self::assertNotSame($site->id, $line->id);
    }

    public function testRejectsParentFromAnotherWorkspace(): void
    {
        $site = new UnsAsset(new Workspace('A', 'a'), 'site', 'Site', 'site');
        $this->expectException(\InvalidArgumentException::class);
        new UnsAsset(new Workspace('B', 'b'), 'line', 'Line', 'line', $site);
    }

    public function testRejectsTagFromAnotherWorkspace(): void
    {
        $asset = new UnsAsset(new Workspace('A', 'a'), 'line', 'Line', 'line');
        $source = new DataSource(new Workspace('B', 'b'), 'mqtt', 'MQTT', 'mqtt');
        $tag = new Tag($source, 'temp', 'Temperature', 'float');
        $this->expectException(\InvalidArgumentException::class);
        new UnsAssetTag($asset, $tag);
    }
}
