<?php
declare(strict_types=1);

namespace App\Controller;

use App\Dashboard\WidgetLayoutValidator;
use App\Entity\AppUser;
use App\Entity\UserDashboard;
use App\Entity\Workspace;
use App\Security\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class SavePersonalDashboardController extends AbstractController
{
    #[Route('/api/me/dashboards', name: 'api_me_dashboards_create', methods: ['POST'])]
    public function __invoke(
        Request $request,
        EntityManagerInterface $em,
        WorkspaceAccess $access,
        WidgetLayoutValidator $validator,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof AppUser) return new JsonResponse(['error' => 'Authentication required'], 401);
        if (!$this->isCsrfTokenValid('dashboard_write', (string) $request->headers->get('X-CSRF-Token'))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        }
        try {
            $body = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($body) || !is_string($body['workspaceId'] ?? null)
                || !is_string($body['name'] ?? null) || !is_string($body['slug'] ?? null)) {
                return new JsonResponse(['error' => 'Invalid request'], 400);
            }
            $workspace = $em->getRepository(Workspace::class)->find($body['workspaceId']);
            if (!$workspace || !$access->canEdit($user, $workspace)) return new JsonResponse(['error' => 'Forbidden'], 403);
            $name = trim($body['name']);
            $slug = trim($body['slug']);
            if ($name === '' || mb_strlen($name) > 120 || !preg_match('/^[a-z0-9-]{1,120}$/', $slug)) {
                return new JsonResponse(['error' => 'Invalid name or slug'], 400);
            }
            $widgets = $validator->validate($body['widgets'] ?? null, $workspace);
            if ($em->getRepository(UserDashboard::class)->findOneBy(['owner' => $user, 'workspace' => $workspace, 'slug' => $slug])) {
                return new JsonResponse(['error' => 'Slug already exists'], 409);
            }
            $dashboard = new UserDashboard($workspace, $user, $name, $slug);
            $dashboard->widgets = $widgets;
            $em->persist($dashboard);
            $em->flush();
            return new JsonResponse(['id' => $dashboard->id], 201, ['Cache-Control' => 'no-store']);
        } catch (\JsonException|\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }
}
