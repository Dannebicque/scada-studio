<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class UnsDiscoverySecurityTest extends WebTestCase
{
    private const WORKSPACE = '00000000-0000-0000-0000-000000000000';

    public function testAnonymousCannotReadDiscoveryToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/workspaces/'.self::WORKSPACE.'/uns/discovery/csrf');
        self::assertResponseStatusCodeSame(401);
    }

    public function testAnonymousCannotRunDiscovery(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/workspaces/'.self::WORKSPACE.'/uns/discover', server: ['CONTENT_TYPE' => 'application/json'], content: '{"prefix":"acme/troyes","seconds":5}');
        self::assertResponseStatusCodeSame(401);
    }

    public function testAnonymousCannotProvision(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/workspaces/'.self::WORKSPACE.'/uns/provision', server: ['CONTENT_TYPE' => 'application/json'], content: '{"model":{"assets":[]},"topics":[]}');
        self::assertResponseStatusCodeSame(401);
    }
}
