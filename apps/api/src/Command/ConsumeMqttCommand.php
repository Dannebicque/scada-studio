<?php

declare(strict_types=1);

namespace App\Command;

use App\Telemetry\TelemetryIngestor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:mqtt:consume', description: 'Consume local MQTT telemetry with mosquitto_sub.')]
final class ConsumeMqttCommand extends Command
{
    private bool $stopping = false;

    public function __construct(private readonly TelemetryIngestor $ingestor)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'prod') === 'prod') {
            $output->writeln('<error>This prototype consumer is for development only.</error>');
            return Command::FAILURE;
        }

        $this->stopping = false;
        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, fn () => $this->stopping = true);
            pcntl_signal(SIGTERM, fn () => $this->stopping = true);
        }

        $attempt = 0;
        while (!$this->stopping) {
            $process = proc_open(
                ['mosquitto_sub', '-h', '127.0.0.1', '-p', '1883', '-q', '0', '-t', 'scada/+/+/+', '-F', '%t %p'],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
            );
            if (!is_resource($process)) {
                $output->writeln('<error>Cannot start mosquitto_sub. Install mosquitto clients.</error>');
                return Command::FAILURE;
            }

            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $output->writeln('<info>Listening for MQTT telemetry on localhost:1883. Press Ctrl+C to stop.</info>');
            $startedAt = time();
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
                    $status = proc_get_status($process);
                    if (!$status['running'] && feof($pipes[1]) && feof($pipes[2])) {
                        break;
                    }
                }
            } finally {
                if ($this->stopping) {
                    proc_terminate($process);
                }
                fclose($pipes[1]);
                fclose($pipes[2]);
                $code = proc_close($process);
            }

            if ($this->stopping) {
                break;
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
}
