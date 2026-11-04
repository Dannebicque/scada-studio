<?php
declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AccountEndpointsTest extends WebTestCase
{
    public function testAnonymousPersonalDashboardsAreDenied(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/me/dashboards');
        self::assertResponseStatusCodeSame(401);
    }

    public function testAnonymousTagPreferencesAreDenied(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/me/tags');
        self::assertResponseStatusCodeSame(401);
    }

    public function testNoUnsafeDashboardWriteRouteExists(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/me/dashboards', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        self::assertNotSame(201, $client->getResponse()->getStatusCode());
    }
}
