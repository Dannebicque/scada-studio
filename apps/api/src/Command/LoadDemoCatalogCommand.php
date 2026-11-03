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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:demo:catalog', description: 'Load a reproducible read-only workshop catalog (development only).')]
final class LoadDemoCatalogCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'prod' === 'prod') {
            $output->writeln('<error>Demo catalog cannot be loaded in production.</error>');
            return Command::FAILURE;
        }

        $workspaces = $this->em->getRepository(Workspace::class);
        $workspace = $workspaces->findOneBy(['slug' => 'atelier-demo']);
        if (!$workspace) {
            $workspace = new Workspace('Atelier de démonstration', 'atelier-demo');
            $this->em->persist($workspace);
        }

        $units = $this->em->getRepository(Unit::class);
        $unitDefinitions = [
            'Cel' => ['Degré Celsius', '°C'],
            'P1' => ['Pourcentage', '%'],
            'count' => ['Comptage', ''],
        ];
        foreach ($unitDefinitions as $code => [$label, $symbol]) {
            $unit = $units->find($code);
            if (!$unit) {
                $unit = new Unit($code, $label, $symbol);
                $this->em->persist($unit);
            }
            $unitMap[$code] = $unit;
        }

        $sources = $this->em->getRepository(DataSource::class);
        $source = $sources->findOneBy(['workspace' => $workspace, 'key' => 'automate-01']);
        if (!$source) {
            $source = new DataSource($workspace, 'automate-01', 'Automate atelier', 'mqtt');
            $this->em->persist($source);
        }

        $tags = $this->em->getRepository(Tag::class);
        foreach ([
            ['temperature', 'Température atelier', 'float', 'Cel'],
            ['humidite', 'Humidité relative', 'float', 'P1'],
            ['pieces_produites', 'Pièces produites', 'integer', 'count'],
            ['convoyeur_actif', 'Convoyeur actif', 'boolean', null],
        ] as [$key, $name, $type, $unitCode]) {
            if (!$tags->findOneBy(['source' => $source, 'key' => $key])) {
                $this->em->persist(new Tag($source, $key, $name, $type, $unitCode ? $unitMap[$unitCode] : null));
            }
        }

        $this->em->flush();
        $output->writeln('<info>Demo catalog ready: 1 workspace, 1 source, 4 tags.</info>');
        return Command::SUCCESS;
    }
}
