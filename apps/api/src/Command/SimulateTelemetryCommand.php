<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Tag;
use App\Entity\TagValue;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:demo:telemetry', description: 'Generate one sample telemetry snapshot (development only).')]
final class SimulateTelemetryCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'prod') === 'prod') {
            $output->writeln('<error>Telemetry simulator is disabled in production.</error>');
            return Command::FAILURE;
        }

        $samples = [
            'temperature' => 21.5 + random_int(-20, 20) / 10,
            'humidite' => 45.0 + random_int(-80, 80) / 10,
            'pieces_produites' => random_int(0, 1000),
            'convoyeur_actif' => (bool) random_int(0, 1),
        ];
        $count = 0;
        foreach ($this->em->getRepository(Tag::class)->findAll() as $tag) {
            if ($tag->source->key !== 'automate-01' || $tag->source->workspace->slug !== 'atelier-demo' || !array_key_exists($tag->key, $samples)) {
                continue;
            }
            $value = $this->em->find(TagValue::class, $tag->id);
            if ($value) {
                $value->update($samples[$tag->key]);
            } else {
                $this->em->persist(new TagValue($tag, $samples[$tag->key]));
            }
            ++$count;
        }
        $this->em->flush();
        $output->writeln(sprintf('<info>%d simulated tag values updated.</info>', $count));
        return Command::SUCCESS;
    }
}
