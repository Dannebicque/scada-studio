<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\AppUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class SessionCsrfController extends AbstractController
{
    #[Route('/api/me/csrf', name: 'api_me_csrf', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        if (!$this->getUser() instanceof AppUser) {
            return new JsonResponse(['error' => 'Authentication required'], 401);
        }
        return new JsonResponse(['token' => $this->container->get('security.csrf.token_manager')->getToken('dashboard_write')->getValue()], headers: ['Cache-Control' => 'private, no-store']);
    }
}
