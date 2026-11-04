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
use Symfony\Component\Routing\Attribute\Route;

final class WorkspaceMembersController extends AbstractController
{
    #[Route('/api/workspaces/{id}/members', name: 'api_workspace_members', methods: ['GET'])]
    public function __invoke(string $id, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->getUser() instanceof AppUser) {
            return new JsonResponse(['error' => 'Authentication required'], 401);
        }
        $workspace = $em->getRepository(Workspace::class)->find($id);
        if (!$workspace) return new JsonResponse(['error' => 'Not found'], 404);
        if (!$this->isGranted(WorkspaceVoter::MANAGE, $workspace)) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $members = $em->getRepository(WorkspaceMember::class)->findBy(['workspace' => $workspace]);
        return new JsonResponse([
            'members' => array_map(static fn (WorkspaceMember $member): array => [
                'userId' => $member->user->id,
                'email' => $member->user->email,
                'role' => $member->role,
            ], $members),
        ], headers: ['Cache-Control' => 'private, no-store']);
    }
}
