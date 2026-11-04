<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\AppUser;
use App\Entity\Tag;
use App\Entity\UserDashboard;
use App\Entity\Workspace;
use App\Security\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class PersonalDashboardWriteController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly WorkspaceAccess $access) {}

    #[Route('/api/me/dashboards', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof AppUser) return new JsonResponse(['error' => 'Authentication required'], 401);
        if (!$this->validCsrf($request)) return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !is_string($data['workspaceId'] ?? null) || !is_string($data['name'] ?? null)
            || !is_string($data['slug'] ?? null) || !is_array($data['widgets'] ?? null)) {
            return new JsonResponse(['error' => 'Invalid payload'], 422);
        }
        $workspace = $this->em->getRepository(Workspace::class)->find($data['workspaceId']);
        if (!$workspace || !$this->access->canView($user, $workspace)) return new JsonResponse(['error' => 'Workspace not found'], 404);
        $name = trim($data['name']);
        $slug = trim($data['slug']);
        if ($name === '' || mb_strlen($name) > 120 || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || strlen($slug) > 120) {
            return new JsonResponse(['error' => 'Invalid name or slug'], 422);
        }
        if (!$this->validWidgets($data['widgets'], $workspace)) return new JsonResponse(['error' => 'Invalid widgets'], 422);
        if ($this->em->getRepository(UserDashboard::class)->findOneBy(['owner' => $user, 'workspace' => $workspace, 'slug' => $slug])) {
            return new JsonResponse(['error' => 'Slug already exists'], 409);
        }
        $dashboard = new UserDashboard($workspace, $user, $name, $slug);
        $dashboard->widgets = $data['widgets'];
        $this->em->persist($dashboard);
        $this->em->flush();
        return new JsonResponse(['id' => $dashboard->id], 201, ['Cache-Control' => 'private, no-store']);
    }

    #[Route('/api/me/dashboards/{id}', methods: ['DELETE'])]
    public function remove(string $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof AppUser) return new JsonResponse(['error' => 'Authentication required'], 401);
        if (!$this->validCsrf($request)) return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        $dashboard = $this->em->getRepository(UserDashboard::class)->findOneBy(['id' => $id, 'owner' => $user]);
        if (!$dashboard || !$this->access->canView($user, $dashboard->workspace)) return new JsonResponse(['error' => 'Not found'], 404);
        $this->em->remove($dashboard);
        $this->em->flush();
        return new JsonResponse(['success' => true], headers: ['Cache-Control' => 'private, no-store']);
    }

    private function validCsrf(Request $request): bool
    {
        $expected = $request->getSession()->get('csrf_personal');
        $actual = $request->headers->get('X-CSRF-Token');
        return is_string($expected) && is_string($actual) && hash_equals($expected, $actual);
    }

    private function validWidgets(array $widgets, Workspace $workspace): bool
    {
        if (!array_is_list($widgets) || count($widgets) > 100) return false;
        foreach ($widgets as $widget) {
            if (!is_array($widget) || !is_string($widget['type'] ?? null) || !is_string($widget['tagId'] ?? null)
                || !is_string($widget['title'] ?? null) || mb_strlen($widget['title']) > 120
                || !in_array($widget['type'], ['value', 'gauge', 'status', 'chart'], true)) return false;
            $tag = $this->em->getRepository(Tag::class)->find($widget['tagId']);
            if (!$tag || $tag->source->workspace->id !== $workspace->id) return false;
        }
        return true;
    }
}
