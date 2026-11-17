<?php

declare(strict_types=1);

namespace App\Telemetry;

/**
 * Paramètres de connexion du consommateur MQTT, lus dans l'environnement (jamais en argument de
 * ligne de commande : les arguments sont visibles dans la liste des processus).
 *
 * MQTT_HOST (défaut 127.0.0.1), MQTT_PORT (défaut 1883), MQTT_USERNAME, MQTT_PASSWORD,
 * MQTT_TLS=1 pour chiffrer la connexion, MQTT_CAFILE pour une autorité de certification privée.
 */
final class MqttConnection
{
    private const HOST_PATTERN = '/^[A-Za-z0-9]([A-Za-z0-9.\-]{0,251}[A-Za-z0-9])?$/';

    private function __construct(
        public readonly string $host,
        public readonly int $port,
        public readonly ?string $username,
        public readonly ?string $password,
        public readonly bool $tls,
        public readonly ?string $cafile,
        public readonly bool $explicit,
    ) {
    }

    /** @param array<string, mixed> $env */
    public static function fromEnv(array $env): self
    {
        $host = self::string($env, 'MQTT_HOST');
        $explicit = $host !== null;
        $host ??= '127.0.0.1';
        // Commence par un caractère alphanumérique : un hôte ne peut pas être pris pour une option.
        if (preg_match(self::HOST_PATTERN, $host) !== 1) {
            throw new \InvalidArgumentException('MQTT_HOST is not a valid host name or address.');
        }

        $cafile = self::string($env, 'MQTT_CAFILE');
        $tls = in_array(strtolower(self::string($env, 'MQTT_TLS') ?? ''), ['1', 'true', 'yes', 'on'], true) || $cafile !== null;

        $rawPort = self::string($env, 'MQTT_PORT') ?? ($tls ? '8883' : '1883');
        if (!ctype_digit($rawPort) || (int) $rawPort < 1 || (int) $rawPort > 65535) {
            throw new \InvalidArgumentException('MQTT_PORT must be between 1 and 65535.');
        }

        $username = self::string($env, 'MQTT_USERNAME');
        $password = self::string($env, 'MQTT_PASSWORD');
        if (($username === null) !== ($password === null)) {
            throw new \InvalidArgumentException('MQTT_USERNAME and MQTT_PASSWORD must be set together.');
        }
        foreach ([$username, $password] as $secret) {
            if ($secret !== null && preg_match('/[\r\n\0]/', $secret) === 1) {
                throw new \InvalidArgumentException('MQTT credentials must not contain line breaks.');
            }
        }

        return new self($host, (int) $rawPort, $username, $password, $tls, $cafile, $explicit);
    }

    /** Environnement du processus (variables réelles, puis .env chargé par Symfony). */
    public static function fromProcessEnvironment(): self
    {
        return self::fromEnv(array_merge(getenv(), $_SERVER, $_ENV));
    }

    /**
     * Crée le fichier d'options 0600 contenant les identifiants (null si le broker est anonyme).
     * L'appelant le supprime avec removeOptionsFile() dès que mosquitto_sub est arrêté.
     */
    public function createOptionsFile(): ?string
    {
        $contents = $this->optionsFileContents();
        if ($contents === null) {
            return null;
        }
        $path = tempnam(sys_get_temp_dir(), 'scada-mqtt-');
        if ($path === false) {
            throw new \RuntimeException('Cannot create the MQTT options file.');
        }
        chmod($path, 0600);
        file_put_contents($path, $contents);

        return $path;
    }

    public static function removeOptionsFile(?string $path): void
    {
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
    }

    public function isLoopback(): bool
    {
        return in_array($this->host, ['127.0.0.1', 'localhost', '::1'], true);
    }

    public function hasCredentials(): bool
    {
        return $this->username !== null;
    }

    public function describe(): string
    {
        return ($this->tls ? 'mqtts://' : 'mqtt://').$this->host.':'.$this->port;
    }

    /**
     * Arguments de mosquitto_sub. Les identifiants n'y figurent pas : ils passent par le fichier
     * d'options (voir optionsFileContents) que l'appelant crée en mode 0600.
     *
     * @param list<string> $filters
     * @return list<string>
     */
    public function subscriberArguments(array $filters, ?string $optionsFile = null): array
    {
        $args = ['mosquitto_sub', '-h', $this->host, '-p', (string) $this->port, '-q', '0'];
        if ($this->tls) {
            // Sans --cafile, mosquitto_sub utilise les autorités du système.
            $args[] = $this->cafile !== null ? '--cafile' : '--capath';
            $args[] = $this->cafile ?? '/etc/ssl/certs';
        }
        if ($optionsFile !== null) {
            array_push($args, '-o', $optionsFile);
        }
        foreach ($filters as $filter) {
            array_push($args, '-t', $filter);
        }
        array_push($args, '-F', '%t %p');

        return $args;
    }

    /** Contenu du fichier d'options (identifiants), null si le broker est anonyme. */
    public function optionsFileContents(): ?string
    {
        if ($this->username === null || $this->password === null) {
            return null;
        }

        return '-u '.self::quote($this->username)."\n".'-P '.self::quote($this->password)."\n";
    }

    private static function quote(string $value): string
    {
        return '"'.addcslashes($value, "\\\"").'"';
    }

    /** @param array<string, mixed> $env */
    private static function string(array $env, string $key): ?string
    {
        $value = $env[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
