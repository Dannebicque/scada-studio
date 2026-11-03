<?php

declare(strict_types=1);

namespace App\Telemetry;

use App\Entity\Tag;
use App\Entity\TagValue;
use Doctrine\ORM\EntityManagerInterface;

final class TelemetryIngestor
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function ingest(string $topic, string $payload): bool
    {
        if (strlen($topic) > 512 || strlen($payload) > 4096) {
            return false;
        }
        $parts = explode('/', $topic);
        if (count($parts) !== 4 || $parts[0] !== 'scada' || in_array('', $parts, true)) {
            return false;
        }
        try {
            $message = json_decode($payload, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }
        if (!is_array($message) || !array_key_exists('value', $message)) {
            return false;
        }

        $tag = $this->em->getRepository(Tag::class)->createQueryBuilder('t')
            ->join('t.source', 's')
            ->join('s.workspace', 'w')
            ->where('w.slug = :workspace AND s.key = :source AND t.key = :tag')
            ->setParameter('workspace', $parts[1])
            ->setParameter('source', $parts[2])
            ->setParameter('tag', $parts[3])
            ->getQuery()->getOneOrNullResult();

        if (!$tag || $tag->source->connectorType !== 'mqtt' || !$tag->source->enabled) {
            return false;
        }

        $observedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if (array_key_exists('observedAt', $message)) {
            if (!is_string($message['observedAt']) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $message['observedAt'])) {
                return false;
            }
            try {
                $observedAt = new \DateTimeImmutable($message['observedAt']);
            } catch (\Exception) {
                return false;
            }
            $observedAt = $observedAt->setTimezone(new \DateTimeZone('UTC'));
            if ($observedAt->getTimestamp() > time() + 300) {
                return false;
            }
        }

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
