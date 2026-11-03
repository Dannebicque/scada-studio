<?php
declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WorkspaceMemberMutationSecurityTest extends WebTestCase
{
    public function testAnonymousCannotReadCsrfToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/workspaces/00000000-0000-0000-0000-000000000000/members/csrf');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAnonymousCannotAddMembers(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/workspaces/00000000-0000-0000-0000-000000000000/members', server: ['CONTENT_TYPE' => 'application/json'], content: '{"email":"test@example.org","role":"owner"}');
        self::assertResponseStatusCodeSame(401);
    }
}
