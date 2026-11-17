<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Workspace;
use App\Uns\UnsCatalogProvisioner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:uns:provision', description: 'Create MQTT sources and tags from a tools/uns-infer uns-model.json (development only).')]
final class ProvisionUnsCatalogCommand extends Command
{
    private const MAX_FILE_BYTES = 2_000_000;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UnsCatalogProvisioner $provisioner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('workspace', InputArgument::REQUIRED, 'Workspace slug (must already exist).')
            ->addArgument('model', InputArgument::REQUIRED, 'Path to uns-model.json produced by tools/uns-infer.')
            ->addOption('enable', null, InputOption::VALUE_NONE, 'Enable created sources for ingestion (disabled by default).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'prod') === 'prod') {
            $output->writeln('<error>UNS provisioning from files cannot run in production: use the administration API.</error>');
            return Command::FAILURE;
        }

        $workspace = $this->em->getRepository(Workspace::class)->findOneBy(['slug' => (string) $input->getArgument('workspace')]);
        if (!$workspace) {
            $output->writeln('<error>Unknown workspace.</error>');
            return Command::FAILURE;
        }

        $path = (string) $input->getArgument('model');
        if (!is_file($path) || !is_readable($path) || filesize($path) > self::MAX_FILE_BYTES) {
            $output->writeln('<error>Model file missing, unreadable or larger than 2 MB.</error>');
            return Command::FAILURE;
        }
        try {
            $model = json_decode((string) file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $output->writeln('<error>Model file is not valid JSON.</error>');
            return Command::FAILURE;
        }

        $enable = (bool) $input->getOption('enable');
        try {
            $result = $this->provisioner->provision($workspace, is_array($model) ? $model : [], $enable);
        } catch (\InvalidArgumentException $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');
            return Command::FAILURE;
        }
        foreach ($result['messages'] as $message) {
            $output->writeln('<comment>'.$message.'</comment>');
        }

        $output->writeln(sprintf('<info>%d source(s) and %d tag(s) created, %d skipped%s.</info>', $result['createdSources'], $result['createdTags'], $result['skipped'], $enable ? '' : ' (sources disabled: use --enable or enable them explicitly)'));
        return Command::SUCCESS;
    }
}
