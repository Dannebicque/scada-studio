<?php
declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PersonalWriteSecurityTest extends WebTestCase
{
    public function testAnonymousCannotGetPersonalCsrfToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/me/personal-csrf');
        self::assertResponseStatusCodeSame(401);
    }

    public function testAnonymousCannotSaveTagPreferences(): void
    {
        $client = static::createClient();
        $client->request('PUT', '/api/me/tags/00000000-0000-0000-0000-000000000000',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"favorite":true,"displayName":null}');
        self::assertResponseStatusCodeSame(401);
    }

    public function testAnonymousCannotCreatePersonalDashboard(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/me/dashboards',
            server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        self::assertResponseStatusCodeSame(401);
    }

    public function testAnonymousCannotDeletePersonalDashboard(): void
    {
        $client = static::createClient();
        $client->request('DELETE', '/api/me/dashboards/00000000-0000-0000-0000-000000000000');
        self::assertResponseStatusCodeSame(401);
    }
}
