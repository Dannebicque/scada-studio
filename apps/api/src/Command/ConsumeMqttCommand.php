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

        $attempt = 0;
        while (true) {
            // A process argument array avoids shell interpolation.
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
        $output->writeln('<info>Listening for MQTT telemetry on localhost:1883. Press Ctrl+C to stop.</info>');
        $startedAt = time();

        while (($line = fgets($pipes[1], 8192)) !== false) {
            $parts = explode(' ', trim($line), 2);
            if (count($parts) !== 2) {
                continue;
            }
            try {
                $accepted = $this->ingestor->ingest($parts[0], $parts[1]);
                $output->writeln(($accepted ? '<info>Accepted</info> ' : '<comment>Ignored</comment> ').$parts[0]);
            } catch (\Throwable $e) {
                $output->writeln('<error>Ingestion failed: '.$e->getMessage().'</error>');
                break;
            }
        }

        fclose($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $code = proc_close($process);
        if ($code === 0) {
            return Command::SUCCESS;
        }
        $attempt = time() - $startedAt > 60 ? 0 : min($attempt + 1, 6);
        $delay = min(30, 2 ** $attempt);
        $output->writeln('<comment>Subscriber disconnected: '.trim($error).'; reconnecting in '.$delay.'s.</comment>');
        sleep($delay);
        }

    }
}
