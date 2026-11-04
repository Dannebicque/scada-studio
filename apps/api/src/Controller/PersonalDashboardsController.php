<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\AppUser;
use App\Entity\UserDashboard;
use App\Security\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class PersonalDashboardsController extends AbstractController
{
    #[Route('/api/me/dashboards', name: 'api_me_dashboards', methods: ['GET'])]
    public function __invoke(EntityManagerInterface $em, WorkspaceAccess $access): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof AppUser) {
            return new JsonResponse(['error' => 'Authentication required'], 401);
        }
        $items = [];
        foreach ($em->getRepository(UserDashboard::class)->findBy(['owner' => $user]) as $dashboard) {
            if (!$access->canView($user, $dashboard->workspace)) continue;
            $items[] = [
                'id' => $dashboard->id,
                'workspaceId' => $dashboard->workspace->id,
                'name' => $dashboard->name,
                'slug' => $dashboard->slug,
                'widgets' => $dashboard->widgets,
            ];
        }
        return new JsonResponse(['dashboards' => $items], headers: ['Cache-Control' => 'private, no-store']);
    }
}
