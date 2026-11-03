<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\TagValue;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class TagValuesController
{
    #[Route('/api/tag-values', name: 'api_tag_values', methods: ['GET'])]
    public function __invoke(EntityManagerInterface $em): JsonResponse
    {
        $values = $em->getRepository(TagValue::class)->findAll();
        $result = [];
        foreach ($values as $item) {
            $result[] = [
                'tagId' => $item->tag->id,
                'value' => $item->value,
                'observedAt' => $item->observedAt->format(\DateTimeInterface::ATOM),
            ];
        }

        return new JsonResponse(['values' => $result], headers: ['Cache-Control' => 'no-store']);
    }
}
