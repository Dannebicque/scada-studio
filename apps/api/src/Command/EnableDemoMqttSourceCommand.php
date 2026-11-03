<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\DataSource;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:demo:enable-mqtt', description: 'Enable demo MQTT source for local development.')]
final class EnableDemoMqttSourceCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'prod') === 'prod') {
            $output->writeln('<error>Development only.</error>');
            return Command::FAILURE;
        }
        $source = $this->em->getRepository(DataSource::class)->createQueryBuilder('s')
            ->join('s.workspace', 'w')
            ->where('w.slug = :slug AND s.key = :key AND s.connectorType = :type')
            ->setParameter('slug', 'atelier-demo')
            ->setParameter('key', 'automate-01')
            ->setParameter('type', 'mqtt')
            ->getQuery()->getOneOrNullResult();
        if (!$source) {
            $output->writeln('<error>Run app:demo:catalog first.</error>');
            return Command::FAILURE;
        }
        $source->enabled = true;
        $this->em->flush();
        $output->writeln('<info>Demo MQTT source enabled.</info>');
        return Command::SUCCESS;
    }
}
