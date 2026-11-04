<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\AppUser;
use App\Entity\UserTag;
use App\Security\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class PersonalTagsController extends AbstractController
{
    #[Route('/api/me/tags', name: 'api_me_tags', methods: ['GET'])]
    public function __invoke(EntityManagerInterface $em, WorkspaceAccess $access): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof AppUser) {
            return new JsonResponse(['error' => 'Authentication required'], 401);
        }
        $items = [];
        foreach ($em->getRepository(UserTag::class)->findBy(['owner' => $user]) as $preference) {
            if (!$access->canView($user, $preference->tag->source->workspace)) continue;
            $items[] = [
                'tagId' => $preference->tag->id,
                'displayName' => $preference->displayName,
                'favorite' => $preference->favorite,
            ];
        }
        return new JsonResponse(['tags' => $items], headers: ['Cache-Control' => 'private, no-store']);
    }
}
