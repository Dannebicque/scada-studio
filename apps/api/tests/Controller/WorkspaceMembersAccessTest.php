<?php
declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WorkspaceMembersAccessTest extends WebTestCase
{
    public function testAnonymousCannotListWorkspaceMembers(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/workspaces/00000000-0000-0000-0000-000000000000/members');
        self::assertResponseStatusCodeSame(401);
    }
}
