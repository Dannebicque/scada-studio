<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TagHistorySecurityTest extends WebTestCase
{
    public function testAnonymousCannotReadTagHistory(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tags/00000000-0000-0000-0000-000000000000/history');
        self::assertResponseStatusCodeSame(401);
    }
}
