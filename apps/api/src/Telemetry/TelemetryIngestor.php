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
    ) {
    }

    public function ingest(string $topic, string $payload): bool
    {
        $message = $this->parser->parse($topic, $payload);
        if ($message === null) {
            return false;
        }

        $tag = $this->em->getRepository(Tag::class)->createQueryBuilder('t')
            ->join('t.source', 's')
            ->join('s.workspace', 'w')
            ->where('w.slug = :workspace AND s.key = :source AND t.key = :tag')
            ->setParameter('workspace', $message['workspace'])
            ->setParameter('source', $message['source'])
            ->setParameter('tag', $message['tag'])
            ->getQuery()->getOneOrNullResult();

        if (!$tag || $tag->source->connectorType !== 'mqtt' || !$tag->source->enabled) {
            return false;
        }

        $observedAt = $message['observedAt'];

        $current = $this->em->find(TagValue::class, $tag->id);
        if ($current && $observedAt <= $current->observedAt) {
            return false;
        }

        try {
            if ($current) {
                $current->update($message['value'], $observedAt);
            } else {
                $this->em->persist(new TagValue($tag, $message['value'], $observedAt));
            }
            $this->em->flush();
        } catch (\InvalidArgumentException) {
            return false;
        }

        return true;
    }
}
