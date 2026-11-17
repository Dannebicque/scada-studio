<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AppUser;
use App\Entity\Tag;
use App\Security\WorkspaceAccess;
use App\Telemetry\TelemetryHistory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** Historique agrégé d'un tag, réservé aux membres de son workspace. */
final class TagHistoryController extends AbstractController
{
    private const RANGES = ['15m' => 900, '1h' => 3600, '6h' => 21600, '24h' => 86400, '7d' => 604800];

    #[Route('/api/tags/{id}/history', name: 'api_tag_history', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['GET'])]
    public function __invoke(string $id, Request $request, EntityManagerInterface $em, WorkspaceAccess $access, TelemetryHistory $history): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof AppUser) {
            return new JsonResponse(['error' => 'Authentication required'], 401);
        }
        $tag = $em->getRepository(Tag::class)->find($id);
        if (!$tag || !$access->canView($user, $tag->source->workspace)) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        $range = (string) $request->query->get('range', '1h');
        $points = $request->query->get('points', '120');
        if (!isset(self::RANGES[$range]) || !is_string($points) || !ctype_digit($points)) {
            return new JsonResponse(['error' => 'Invalid range or points'], 422);
        }

        $to = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $from = $to->modify('-'.self::RANGES[$range].' seconds');
        $supported = $tag->valueType !== 'string';
        $series = $supported ? $history->series($tag->id, $from, $to, (int) $points) : ['bucketSeconds' => 0, 'points' => []];

        return new JsonResponse([
            'tagId' => $tag->id,
            'valueType' => $tag->valueType,
            'unit' => $tag->unit?->symbol,
            'range' => $range,
            'from' => $from->format('Y-m-d\TH:i:s').'Z',
            'to' => $to->format('Y-m-d\TH:i:s').'Z',
            'supported' => $supported,
            'retentionDays' => TelemetryHistory::retentionDays(array_merge(getenv(), $_SERVER, $_ENV)),
        ] + $series, headers: ['Cache-Control' => 'private, no-store']);
    }
}
