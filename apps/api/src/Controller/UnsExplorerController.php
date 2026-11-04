<?php
declare(strict_types=1);
namespace App\Controller;

use App\Entity\AppUser;
use App\Entity\DataSource;
use App\Entity\Tag;
use App\Entity\Workspace;
use App\Security\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class UnsExplorerController extends AbstractController
{
    #[Route('/api/workspaces/{id}/uns', methods: ['GET'])]
    public function __invoke(string $id, EntityManagerInterface $em, WorkspaceAccess $access): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof AppUser) return new JsonResponse(['error' => 'Authentication required'], 401);
        $workspace = $em->getRepository(Workspace::class)->find($id);
        if (!$workspace || !$access->canView($user, $workspace)) return new JsonResponse(['error' => 'Not found'], 404);
        $items = [];
        foreach ($em->getRepository(DataSource::class)->findBy(['workspace' => $workspace]) as $source) {
            foreach ($em->getRepository(Tag::class)->findBy(['source' => $source]) as $tag) {
                $items[] = [
                    'tagId' => $tag->id, 'sourceId' => $source->id,
                    'path' => $workspace->slug.'/'.$source->key.'/'.$tag->key,
                    'name' => $tag->name, 'valueType' => $tag->valueType,
                    'unit' => $tag->unit?->symbol,
                ];
            }
        }
        return new JsonResponse(['workspaceId' => $workspace->id, 'projection' => 'source-tag-v1', 'tags' => $items], headers: ['Cache-Control' => 'private, no-store']);
    }
}
