<?php

declare(strict_types=1);

namespace App\Uns;

use App\Telemetry\MqttConnection;

/**
 * Écoute brièvement un préfixe UNS du broker configuré (MQTT_*), en lecture seule, et en déduit
 * un modèle et des pages dashboard. Rien n'est publié ni enregistré.
 */
final class UnsDiscovery
{
    public const MIN_SECONDS = 5;
    public const MAX_SECONDS = 30;
    private const MAX_FRAMES = 20000;
    private const MAX_LINE_BYTES = 16384;

    /**
     * @return array{frames: int, truncated: bool, model: array<string, mixed>, pages: list<array<string, mixed>>}
     */
    public function discover(MqttConnection $connection, string $prefix, int $seconds): array
    {
        if (UnsPolicy::segments($prefix) === null) {
            throw new \InvalidArgumentException('Invalid UNS prefix.');
        }
        $seconds = max(self::MIN_SECONDS, min(self::MAX_SECONDS, $seconds));

        $optionsFile = $connection->createOptionsFile();
        $process = null;
        $pipes = [];
        try {
            // Jamais "#" seul : le filtre est toujours borné par le préfixe demandé.
            $process = proc_open(
                $connection->subscriberArguments([$prefix.'/#'], $optionsFile),
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
            );
            if (!is_resource($process)) {
                throw new \RuntimeException('Cannot start mosquitto_sub. Install the mosquitto clients.');
            }
            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);

            $collector = new UnsCollector();
            $truncated = false;
            $buffer = '';
            $error = '';
            $deadline = microtime(true) + $seconds;

            while (microtime(true) < $deadline) {
                $read = [$pipes[1], $pipes[2]];
                $write = $except = [];
                if (@stream_select($read, $write, $except, 0, 200000) === false) {
                    break;
                }
                foreach ($read as $stream) {
                    $chunk = fread($stream, 8192);
                    if ($chunk === false || $chunk === '') {
                        continue;
                    }
                    if ($stream === $pipes[2]) {
                        $error = substr($error.$chunk, -2048);
                        continue;
                    }
                    $buffer .= $chunk;
                    if (strlen($buffer) > self::MAX_LINE_BYTES && !str_contains($buffer, "\n")) {
                        $buffer = '';
                    }
                    while (($pos = strpos($buffer, "\n")) !== false) {
                        $line = substr($buffer, 0, $pos);
                        $buffer = substr($buffer, $pos + 1);
                        $parts = explode(' ', trim($line), 2);
                        if (count($parts) === 2) {
                            $collector->addFrame($parts[0], $parts[1]);
                        }
                    }
                }
                if ($collector->frames() >= self::MAX_FRAMES) {
                    $truncated = true;
                    break;
                }
                $status = proc_get_status($process);
                if (!$status['running']) {
                    // mosquitto_sub s'arrête seul seulement sur erreur (connexion refusée, authentification…).
                    if ($collector->frames() === 0) {
                        throw new \RuntimeException('Broker unreachable: '.(trim($error) ?: 'subscriber exited with code '.$status['exitcode']));
                    }
                    break;
                }
            }
            // Un broker joignable mais muet n'est pas une erreur : le modèle est simplement vide.

            $model = UnsModelBuilder::buildModel($collector, $prefix);

            return [
                'frames' => $collector->frames(),
                'truncated' => $truncated || count($collector->tags()) >= UnsCollector::MAX_TAGS,
                'model' => $model,
                'pages' => UnsModelBuilder::buildPages($model),
            ];
        } finally {
            if (is_resource($process)) {
                proc_terminate($process);
                foreach ($pipes as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                proc_close($process);
            }
            MqttConnection::removeOptionsFile($optionsFile);
        }
    }
}
