<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\AppUser;
use App\Entity\DataSource;
use App\Entity\Tag;
use App\Entity\UserDashboard;
use App\Entity\UserTag;
use App\Entity\Workspace;
use App\Entity\WorkspaceMember;
use PHPUnit\Framework\TestCase;

final class AccountOwnershipTest extends TestCase
{
    public function testWorkspaceMembershipRolesAreValidated(): void
    {
        $workspace = new Workspace('Factory', 'factory');
        $user = new AppUser('  PERSON@EXAMPLE.COM ', 'hashed-password');
        self::assertSame('person@example.com', $user->getUserIdentifier());
        self::assertContains('ROLE_USER', $user->getRoles());
        self::assertSame('editor', (new WorkspaceMember($workspace, $user, 'editor'))->role);

        $this->expectException(\InvalidArgumentException::class);
        new WorkspaceMember($workspace, $user, 'superadmin');
    }

    public function testDashboardsAndTagPreferencesBelongToUsers(): void
    {
        $workspace = new Workspace('Factory', 'factory');
        $user = new AppUser('person@example.com', 'hashed-password');
        $dashboard = new UserDashboard($workspace, $user, 'My dashboard', 'mine');
        self::assertSame($user, $dashboard->owner);
        self::assertSame([], $dashboard->widgets);

        $source = new DataSource($workspace, 'PLC', 'plc', 'mqtt');
        $tag = new Tag($source, 'temperature', 'Temperature', 'float');
        $preference = new UserTag($user, $tag);
        self::assertSame($tag, $preference->tag);
        self::assertFalse($preference->favorite);
    }
}
