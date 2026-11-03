<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\AppUser;
use App\Entity\Workspace;
use App\Entity\WorkspaceMember;
use App\Security\WorkspaceVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ManageWorkspaceMembersController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    #[Route('/api/workspaces/{id}/members/csrf', methods: ['GET'])]
    public function token(string $id, Request $request): JsonResponse
    {
        $workspace = $this->em->getRepository(Workspace::class)->find($id);
        if (!$workspace || !$this->isGranted(WorkspaceVoter::MANAGE, $workspace)) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }
        $session = $request->getSession();
        $key = 'csrf_workspace_members_'.$id;
        if (!$session->has($key)) $session->set($key, bin2hex(random_bytes(32)));
        return new JsonResponse(['token' => $session->get($key)], headers: ['Cache-Control' => 'private, no-store']);
    }

    #[Route('/api/workspaces/{id}/members', methods: ['POST'])]
    public function add(string $id, Request $request): JsonResponse
    {
        $workspace = $this->authorizedWorkspace($id, $request);
        if ($workspace instanceof JsonResponse) return $workspace;
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !is_string($data['email'] ?? null) || !is_string($data['role'] ?? null)) {
            return new JsonResponse(['error' => 'Invalid payload'], 400);
        }
        $email = mb_strtolower(trim($data['email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($data['role'], ['viewer', 'editor', 'owner'], true)) {
            return new JsonResponse(['error' => 'Invalid email or role'], 422);
        }
        $user = $this->em->getRepository(AppUser::class)->findOneBy(['email' => $email]);
        if (!$user) return new JsonResponse(['error' => 'Account not found'], 404);
        if ($this->em->getRepository(WorkspaceMember::class)->findOneBy(['workspace' => $workspace, 'user' => $user])) {
            return new JsonResponse(['error' => 'Already a member'], 409);
        }
        $this->em->persist(new WorkspaceMember($workspace, $user, $data['role']));
        $this->em->flush();
        return new JsonResponse(['success' => true], 201);
    }

    #[Route('/api/workspaces/{id}/members/{userId}', methods: ['PATCH'])]
    public function change(string $id, string $userId, Request $request): JsonResponse
    {
        $workspace = $this->authorizedWorkspace($id, $request);
        if ($workspace instanceof JsonResponse) return $workspace;
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !in_array($data['role'] ?? null, ['viewer', 'editor', 'owner'], true)) {
            return new JsonResponse(['error' => 'Invalid role'], 422);
        }
        $member = $this->findMember($workspace, $userId);
        if (!$member) return new JsonResponse(['error' => 'Member not found'], 404);
        if ($member->role === 'owner' && $data['role'] !== 'owner' && $this->ownerCount($workspace) <= 1) {
            return new JsonResponse(['error' => 'Cannot demote last owner'], 409);
        }
        $member->role = $data['role'];
        $this->em->flush();
        return new JsonResponse(['success' => true]);
    }

    #[Route('/api/workspaces/{id}/members/{userId}', methods: ['DELETE'])]
    public function remove(string $id, string $userId, Request $request): JsonResponse
    {
        $workspace = $this->authorizedWorkspace($id, $request);
        if ($workspace instanceof JsonResponse) return $workspace;
        $member = $this->findMember($workspace, $userId);
        if (!$member) return new JsonResponse(['error' => 'Member not found'], 404);
        if ($member->role === 'owner' && $this->ownerCount($workspace) <= 1) {
            return new JsonResponse(['error' => 'Cannot remove last owner'], 409);
        }
        $this->em->remove($member);
        $this->em->flush();
        return new JsonResponse(['success' => true]);
    }

    private function authorizedWorkspace(string $id, Request $request): Workspace|JsonResponse
    {
        if (!$this->getUser() instanceof AppUser) return new JsonResponse(['error' => 'Authentication required'], 401);
        $workspace = $this->em->getRepository(Workspace::class)->find($id);
        if (!$workspace) return new JsonResponse(['error' => 'Not found'], 404);
        if (!$this->isGranted(WorkspaceVoter::MANAGE, $workspace)) return new JsonResponse(['error' => 'Forbidden'], 403);
        $token = $request->headers->get('X-CSRF-Token', '');
        $expected = $request->getSession()->get('csrf_workspace_members_'.$id);
        if (!is_string($expected) || !is_string($token) || !hash_equals($expected, $token)) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        }
        return $workspace;
    }

    private function findMember(Workspace $workspace, string $userId): ?WorkspaceMember
    {
        $user = $this->em->getRepository(AppUser::class)->find($userId);
        return $user ? $this->em->getRepository(WorkspaceMember::class)->findOneBy(['workspace' => $workspace, 'user' => $user]) : null;
    }

    private function ownerCount(Workspace $workspace): int
    {
        return $this->em->getRepository(WorkspaceMember::class)->count(['workspace' => $workspace, 'role' => 'owner']);
    }
}
