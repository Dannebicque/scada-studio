<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\DataSource;
use App\Entity\Tag;
use App\Entity\Unit;
use App\Entity\Workspace;
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
    private const SEGMENT = '/^[a-zA-Z0-9_-]{1,100}$/D';
    private const MAX_FILE_BYTES = 2_000_000;
    private const MAX_ASSETS = 500;

    public function __construct(private readonly EntityManagerInterface $em)
    {
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
            $output->writeln('<error>UNS provisioning cannot run in production.</error>');
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
        if (!is_array($model) || !is_array($model['assets'] ?? null) || !array_is_list($model['assets']) || count($model['assets']) > self::MAX_ASSETS) {
            $output->writeln('<error>Unrecognised model: "assets" list expected (max '.self::MAX_ASSETS.').</error>');
            return Command::FAILURE;
        }

        $sources = $this->em->getRepository(DataSource::class);
        $tags = $this->em->getRepository(Tag::class);
        $units = $this->em->getRepository(Unit::class);
        $createdSources = $createdTags = $skipped = 0;
        $enable = (bool) $input->getOption('enable');

        foreach ($model['assets'] as $asset) {
            $prefix = is_array($asset) && is_string($asset['topic'] ?? null) ? $asset['topic'] : '';
            $segments = explode('/', $prefix);
            $relative = is_array($asset['path'] ?? null) ? array_values(array_filter($asset['path'], 'is_string')) : [];
            $key = substr(strtolower(implode('-', $relative)), 0, 100);
            if (!$this->validPrefix($segments) || !preg_match(self::SEGMENT, $key) || !is_array($asset['tags'] ?? null)) {
                $output->writeln('<comment>Skipped invalid asset: '.substr($prefix, 0, 120).'</comment>');
                ++$skipped;
                continue;
            }

            $source = $sources->findOneBy(['workspace' => $workspace, 'key' => $key]);
            if ($source && $source->topicPrefix !== $prefix) {
                $output->writeln('<comment>Source "'.$key.'" already exists with another topic prefix: skipped.</comment>');
                ++$skipped;
                continue;
            }
            if (!$source) {
                if ($sources->findOneBy(['topicPrefix' => $prefix])) {
                    $output->writeln('<comment>Topic prefix "'.$prefix.'" already used by another source: skipped.</comment>');
                    ++$skipped;
                    continue;
                }
                $source = new DataSource($workspace, $key, ucfirst(str_replace(['-', '_'], ' ', $key)), 'mqtt');
                $source->topicPrefix = $prefix;
                $source->enabled = $enable;
                $this->em->persist($source);
                $this->em->flush(); // l'unicité du préfixe est vérifiée avant, la contrainte SQL reste le garde-fou
                ++$createdSources;
            }

            foreach ($asset['tags'] as $tag) {
                $name = is_array($tag) && is_string($tag['name'] ?? null) ? $tag['name'] : '';
                $type = match (is_array($tag) ? ($tag['kind'] ?? null) : null) {
                    'boolean' => 'boolean',
                    'string' => 'string',
                    'number', 'counter', 'constant' => 'float', // accepte int et float : pas de rejet si un décimal arrive
                    default => null,
                };
                if (!preg_match(self::SEGMENT, $name) || $type === null) {
                    ++$skipped;
                    continue;
                }
                if ($tags->findOneBy(['source' => $source, 'key' => $name])) {
                    continue;
                }
                $symbol = is_string($tag['unit'] ?? null) ? $tag['unit'] : null;
                $unit = $symbol !== null ? $units->findOneBy(['symbol' => $symbol]) : null;
                $this->em->persist(new Tag($source, $name, ucfirst(str_replace(['-', '_'], ' ', $name)), $type, $unit));
                ++$createdTags;
            }
            $this->em->flush();
        }

        $output->writeln(sprintf('<info>%d source(s) and %d tag(s) created, %d skipped%s.</info>', $createdSources, $createdTags, $skipped, $enable ? '' : ' (sources disabled: use --enable or enable them explicitly)'));
        return Command::SUCCESS;
    }

    /** @param list<string> $segments */
    private function validPrefix(array $segments): bool
    {
        // Au plus 11 segments : le tag ajoute le 12e. "scada" est réservé au contrat historique.
        if (count($segments) < 1 || count($segments) > 11 || $segments[0] === 'scada') {
            return false;
        }
        foreach ($segments as $segment) {
            if (!preg_match(self::SEGMENT, $segment)) {
                return false;
            }
        }
        return true;
    }
}
