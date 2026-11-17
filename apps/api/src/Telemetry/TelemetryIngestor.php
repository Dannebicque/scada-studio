<?php

declare(strict_types=1);

namespace App\Telemetry;

use App\Entity\Tag;
use App\Entity\TagValue;
use Doctrine\ORM\EntityManagerInterface;

final class TelemetryIngestor
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MqttMessageParser $parser,
        private readonly TelemetryHistory $history,
    ) {
    }

    public function ingest(string $topic, string $payload): bool
    {
        $message = $this->parser->parse($topic, $payload);
        if ($message === null) {
            return false;
        }

        $query = $this->em->getRepository(Tag::class)->createQueryBuilder('t')
            ->join('t.source', 's')
            ->join('s.workspace', 'w')
            ->setParameter('tag', $message['tag']);
        if ($message['prefix'] === null) {
            $query->where('w.slug = :workspace AND s.key = :source AND t.key = :tag')
                ->setParameter('workspace', $message['workspace'])
                ->setParameter('source', $message['source']);
        } else {
            // UNS : topicPrefix est unique sur l'instance, donc la résolution est sans ambiguïté.
            $query->where('s.topicPrefix = :prefix AND t.key = :tag')
                ->setParameter('prefix', $message['prefix']);
        }
        $tag = $query->getQuery()->getOneOrNullResult();

        if (!$tag || $tag->source->connectorType !== 'mqtt' || !$tag->source->enabled) {
            return false;
        }

        $observedAt = $message['observedAt'];

        $current = $this->em->find(TagValue::class, $tag->id);
        if ($current && $observedAt <= $current->observedAt) {
            return false;
        }

        $previousValue = $current?->value;
        $store = TelemetryHistory::shouldStore($previousValue, $current?->sampledAt, $message['value'], $observedAt);
        try {
            if ($current) {
                $current->update($message['value'], $observedAt);
                $value = $current;
            } else {
                $value = new TagValue($tag, $message['value'], $observedAt);
                $this->em->persist($value);
            }
            if ($store && TelemetryHistory::toNumber($message['value']) !== null) {
                $value->sampledAt = $observedAt;
            }
            $this->em->flush();
        } catch (\InvalidArgumentException) {
            return false;
        }
        if ($store) {
            // Après le flush : la valeur courante est acquise même si l'écriture de l'historique échoue.
            try {
                $this->history->record($tag->id, $message['value'], $observedAt);
            } catch (\Throwable) {
                // L'historique est secondaire : ne jamais perdre la valeur courante pour lui.
            }
        }

        return true;
    }
}
