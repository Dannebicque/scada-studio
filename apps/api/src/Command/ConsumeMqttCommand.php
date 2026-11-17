<?php

declare(strict_types=1);

namespace App\Command;

use App\Telemetry\MqttConnection;
use App\Telemetry\MqttSubscriptionResolver;
use App\Telemetry\TelemetryIngestor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:mqtt:consume', description: 'Consume MQTT telemetry with mosquitto_sub (connection from MQTT_* environment variables).')]
final class ConsumeMqttCommand extends Command
{
    /** Intervalle de relecture du catalogue : une source activée est prise en compte sans redémarrage. */
    private const REFRESH_SECONDS = 30;

    private bool $stopping = false;

    public function __construct(
        private readonly TelemetryIngestor $ingestor,
        private readonly MqttSubscriptionResolver $subscriptions,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $environment = array_merge(getenv(), $_SERVER, $_ENV);
        try {
            $connection = MqttConnection::fromEnv($environment);
        } catch (\InvalidArgumentException $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');
            return Command::INVALID;
        }
        if (($environment['APP_ENV'] ?? 'prod') === 'prod') {
            // En production, rien n'est implicite : le broker doit être désigné, et un broker distant chiffré.
            if (!$connection->explicit) {
                $output->writeln('<error>Set MQTT_HOST to run the consumer in production.</error>');
                return Command::FAILURE;
            }
            if (!$connection->isLoopback() && !$connection->tls) {
                $output->writeln('<error>Refusing an unencrypted connection to a remote broker: set MQTT_TLS=1 (and MQTT_CAFILE for a private CA).</error>');
                return Command::FAILURE;
            }
        }

        $this->stopping = false;
        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, fn () => $this->stopping = true);
            pcntl_signal(SIGTERM, fn () => $this->stopping = true);
        }

        $attempt = 0;
        while (!$this->stopping) {
            // Relu à chaque (re)connexion, puis toutes les REFRESH_SECONDS pendant l'écoute.
            $filters = $this->currentFilters($output);
            $optionsFile = $this->writeOptionsFile($connection);
            try {
                $process = proc_open(
                    $connection->subscriberArguments($filters, $optionsFile),
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                    $pipes,
                );
            } catch (\Throwable $e) {
                $this->removeOptionsFile($optionsFile);
                $output->writeln('<error>Cannot start mosquitto_sub: '.$e->getMessage().'</error>');
                return Command::FAILURE;
            }
            if (!is_resource($process)) {
                $this->removeOptionsFile($optionsFile);
                $output->writeln('<error>Cannot start mosquitto_sub. Install mosquitto clients.</error>');
                return Command::FAILURE;
            }

            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $output->writeln('<info>Listening on '.$connection->describe().' ('.count($filters).' topic filter(s)). Press Ctrl+C to stop.</info>');
            $startedAt = time();
            $nextRefresh = $startedAt + self::REFRESH_SECONDS;
            $reload = false;
            $buffer = '';
            $error = '';

            try {
                while (!$this->stopping) {
                    $read = [$pipes[1], $pipes[2]];
                    $write = $except = [];
                    $ready = @stream_select($read, $write, $except, 0, 200000);
                    if ($ready === false) {
                        if ($this->stopping) {
                            break;
                        }
                        throw new \RuntimeException('Cannot poll MQTT subscriber pipes.');
                    }
                    foreach ($read as $stream) {
                        $chunk = fread($stream, 8192);
                        if ($chunk === false || $chunk === '') {
                            continue;
                        }
                        if ($stream === $pipes[2]) {
                            $error = substr($error.$chunk, -4096);
                            continue;
                        }
                        $buffer .= $chunk;
                        if (strlen($buffer) > 16384 && !str_contains($buffer, "\n")) {
                            $buffer = '';
                            $output->writeln('<comment>Discarded oversized MQTT frame.</comment>');
                        }
                        while (($pos = strpos($buffer, "\n")) !== false) {
                            $line = substr($buffer, 0, $pos);
                            $buffer = substr($buffer, $pos + 1);
                            $parts = explode(' ', trim($line), 2);
                            if (count($parts) !== 2) {
                                continue;
                            }
                            try {
                                $accepted = $this->ingestor->ingest($parts[0], $parts[1]);
                                $output->writeln(($accepted ? '<info>Accepted</info> ' : '<comment>Ignored</comment> ').$parts[0]);
                            } catch (\Throwable $e) {
                                $output->writeln('<error>Ingestion failed: '.$e->getMessage().'</error>');
                            }
                        }
                    }
                    if (time() >= $nextRefresh) {
                        $nextRefresh = time() + self::REFRESH_SECONDS;
                        if ($this->currentFilters($output) !== $filters) {
                            $output->writeln('<info>Subscriptions changed, reloading.</info>');
                            $reload = true;
                            break;
                        }
                    }
                    $status = proc_get_status($process);
                    if (!$status['running'] && feof($pipes[1]) && feof($pipes[2])) {
                        break;
                    }
                }
            } finally {
                if ($this->stopping || $reload) {
                    proc_terminate($process);
                }
                fclose($pipes[1]);
                fclose($pipes[2]);
                $code = proc_close($process);
                $this->removeOptionsFile($optionsFile);
            }

            if ($this->stopping) {
                break;
            }
            if ($reload) {
                $attempt = 0;
                continue;
            }
            $attempt = time() - $startedAt > 60 ? 0 : min($attempt + 1, 6);
            $delay = min(30, 2 ** $attempt);
            $reason = trim($error) ?: 'subscriber exited with code '.$code;
            $output->writeln('<comment>Subscriber disconnected: '.$reason.'; reconnecting in '.$delay.'s.</comment>');
            for ($i = 0; $i < $delay * 5 && !$this->stopping; ++$i) {
                usleep(200000);
            }
        }

        $output->writeln('<info>MQTT consumer stopped.</info>');
        return Command::SUCCESS;
    }

    /** @return list<string> */
    private function currentFilters(OutputInterface $output): array
    {
        try {
            return $this->subscriptions->topicFilters();
        } catch (\Throwable $e) {
            $output->writeln('<error>Cannot load UNS subscriptions, using legacy topics only: '.$e->getMessage().'</error>');
            return [MqttSubscriptionResolver::LEGACY_FILTER];
        }
    }

    /** Fichier temporaire 0600 contenant les identifiants, pour qu'ils ne figurent pas dans la liste des processus. */
    private function writeOptionsFile(MqttConnection $connection): ?string
    {
        $contents = $connection->optionsFileContents();
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

    private function removeOptionsFile(?string $path): void
    {
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
    }
}
