<?php
declare(strict_types=1);
namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class UnsExplorerSecurityTest extends WebTestCase
{
    public function testAnonymousCannotReadNamespace(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/workspaces/00000000-0000-0000-0000-000000000000/uns');
        self::assertResponseStatusCodeSame(401);
    }
}
