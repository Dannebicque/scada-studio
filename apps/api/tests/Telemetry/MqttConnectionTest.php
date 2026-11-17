<?php

declare(strict_types=1);

namespace App\Tests\Telemetry;

use App\Telemetry\MqttConnection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MqttConnectionTest extends TestCase
{
    public function testDefaultsTargetLocalAnonymousBroker(): void
    {
        $connection = MqttConnection::fromEnv([]);

        self::assertSame('mqtt://127.0.0.1:1883', $connection->describe());
        self::assertFalse($connection->explicit);
        self::assertTrue($connection->isLoopback());
        self::assertNull($connection->optionsFileContents());
        self::assertSame(
            ['mosquitto_sub', '-h', '127.0.0.1', '-p', '1883', '-q', '0', '-t', 'scada/+/+/+', '-F', '%t %p'],
            $connection->subscriberArguments(['scada/+/+/+']),
        );
    }

    public function testRemoteTlsBrokerWithCredentialsKeepsSecretsOutOfArguments(): void
    {
        $connection = MqttConnection::fromEnv([
            'MQTT_HOST' => 'broker.example.org',
            'MQTT_PORT' => '8883',
            'MQTT_TLS' => '1',
            'MQTT_USERNAME' => 'scada-ro',
            'MQTT_PASSWORD' => 's3cr"et\\',
        ]);

        $args = $connection->subscriberArguments(['acme/troyes/+'], '/tmp/opts');

        self::assertTrue($connection->explicit);
        self::assertSame('mqtts://broker.example.org:8883', $connection->describe());
        self::assertContains('-o', $args);
        self::assertContains('--capath', $args);
        self::assertNotContains('s3cr"et\\', $args);
        self::assertNotContains('scada-ro', $args);
        self::assertSame("-u \"scada-ro\"\n-P \"s3cr\\\"et\\\\\"\n", $connection->optionsFileContents());
    }

    public function testPrivateCertificateAuthorityImpliesTlsAndDefaultTlsPort(): void
    {
        $connection = MqttConnection::fromEnv(['MQTT_HOST' => 'mqtt.internal', 'MQTT_CAFILE' => '/etc/scada/ca.pem']);

        self::assertTrue($connection->tls);
        self::assertSame(8883, $connection->port);
        self::assertContains('--cafile', $connection->subscriberArguments([]));
    }

    /** @param array<string, string> $env */
    #[DataProvider('invalidEnvironments')]
    public function testRejectsInvalidConfiguration(array $env): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MqttConnection::fromEnv($env);
    }

    /** @return iterable<string, array{array<string, string>}> */
    public static function invalidEnvironments(): iterable
    {
        yield 'host looks like an option' => [['MQTT_HOST' => '-oProxyCommand=x']];
        yield 'host with space' => [['MQTT_HOST' => 'a b']];
        yield 'port out of range' => [['MQTT_PORT' => '70000']];
        yield 'port not numeric' => [['MQTT_PORT' => '18x3']];
        yield 'username without password' => [['MQTT_USERNAME' => 'u']];
        yield 'password with newline' => [['MQTT_USERNAME' => 'u', 'MQTT_PASSWORD' => "a\n-h evil"]];
    }
}
