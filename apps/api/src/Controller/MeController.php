<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AppUser;
use App\Entity\WorkspaceMember;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class MeController extends AbstractController
{
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function __invoke(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof AppUser) {
            return new JsonResponse(['authenticated' => false, 'workspaces' => []], headers: ['Cache-Control' => 'no-store']);
        }

        $memberships = $em->getRepository(WorkspaceMember::class)->findBy(['user' => $user]);
        return new JsonResponse([
            'authenticated' => true,
            'user' => ['id' => $user->id, 'email' => $user->email],
            'workspaces' => array_map(static fn (WorkspaceMember $member): array => [
                'id' => $member->workspace->id,
                'name' => $member->workspace->name,
                'role' => $member->role,
            ], $memberships),
        ], headers: ['Cache-Control' => 'no-store']);
    }
}
