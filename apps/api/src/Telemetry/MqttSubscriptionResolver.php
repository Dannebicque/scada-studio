<?php

declare(strict_types=1);

namespace App\Telemetry;

use App\Entity\DataSource;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Filtres d'abonnement du consommateur : le contrat historique plus UN filtre par préfixe UNS de
 * source MQTT active. Jamais de "#" : le worker n'écoute que ce qui est déclaré dans le catalogue.
 */
final class MqttSubscriptionResolver
{
    public const LEGACY_FILTER = 'scada/+/+/+';
    public const MAX_PREFIXES = 200;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** @return list<string> */
    public function topicFilters(): array
    {
        /** @var list<string> $prefixes */
        $prefixes = $this->em->createQueryBuilder()
            ->select('s.topicPrefix')
            ->from(DataSource::class, 's')
            ->where('s.connectorType = :type AND s.enabled = true AND s.topicPrefix IS NOT NULL')
            ->setParameter('type', 'mqtt')
            ->orderBy('s.topicPrefix')
            ->setMaxResults(self::MAX_PREFIXES)
            ->getQuery()
            ->getSingleColumnResult();

        return [self::LEGACY_FILTER, ...array_map(static fn (string $prefix): string => $prefix.'/+', $prefixes)];
    }
}
