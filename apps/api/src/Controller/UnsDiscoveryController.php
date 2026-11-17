<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AppUser;
use App\Entity\Workspace;
use App\Security\WorkspaceVoter;
use App\Telemetry\MqttConnection;
use App\Uns\UnsCatalogProvisioner;
use App\Uns\UnsDiscovery;
use App\Uns\UnsPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Découverte et activation d'un espace de noms UNS depuis l'interface, réservées aux propriétaires
 * du workspace. Le broker est celui du serveur (MQTT_*) : l'API n'accepte jamais d'adresse en entrée.
 */
final class UnsDiscoveryController extends AbstractController
{
    private const MAX_BODY_BYTES = 2_000_000;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UnsDiscovery $discovery,
        private readonly UnsCatalogProvisioner $provisioner,
    ) {
    }

    #[Route('/api/workspaces/{id}/uns/discovery/csrf', methods: ['GET'])]
    public function token(string $id, Request $request): JsonResponse
    {
        $workspace = $this->em->getRepository(Workspace::class)->find($id);
        if (!$this->getUser() instanceof AppUser) {
            return new JsonResponse(['error' => 'Authentication required'], 401);
        }
        if (!$workspace || !$this->isGranted(WorkspaceVoter::MANAGE, $workspace)) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }
        $policy = UnsPolicy::fromProcessEnvironment();
        $session = $request->getSession();
        $key = self::csrfKey($id);
        if (!$session->has($key)) {
            $session->set($key, bin2hex(random_bytes(32)));
        }

        return new JsonResponse([
            'token' => $session->get($key),
            'enabled' => $policy->isConfigured(),
            'broker' => MqttConnection::fromProcessEnvironment()->describe(),
        ], headers: ['Cache-Control' => 'private, no-store']);
    }

    #[Route('/api/workspaces/{id}/uns/discover', methods: ['POST'])]
    public function discover(string $id, Request $request): JsonResponse
    {
        $workspace = $this->authorizedWorkspace($id, $request);
        if ($workspace instanceof JsonResponse) {
            return $workspace;
        }
        $data = $this->payload($request);
        if (!is_array($data) || !is_string($data['prefix'] ?? null) || !is_int($data['seconds'] ?? 15)) {
            return new JsonResponse(['error' => 'Invalid payload'], 400);
        }
        $prefix = trim($data['prefix'], " \t/");
        if (UnsPolicy::segments($prefix) === null) {
            return new JsonResponse(['error' => 'Invalid UNS prefix'], 422);
        }
        if (!UnsPolicy::fromProcessEnvironment()->allows($prefix)) {
            return new JsonResponse(['error' => 'This prefix is not allowed on this instance'], 403);
        }

        // Libère le verrou de session : l'écoute dure jusqu'à 30 s et ne doit pas bloquer les autres requêtes.
        $request->getSession()->save();
        try {
            $connection = MqttConnection::fromProcessEnvironment();
            $result = $this->discovery->discover($connection, $prefix, $data['seconds'] ?? 15);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 502);
        }

        return new JsonResponse(['prefix' => $prefix] + $result, headers: ['Cache-Control' => 'private, no-store']);
    }

    #[Route('/api/workspaces/{id}/uns/provision', methods: ['POST'])]
    public function provision(string $id, Request $request): JsonResponse
    {
        $workspace = $this->authorizedWorkspace($id, $request);
        if ($workspace instanceof JsonResponse) {
            return $workspace;
        }
        $data = $this->payload($request);
        if (!is_array($data) || !is_array($data['model'] ?? null) || !is_array($data['topics'] ?? null)
            || !array_is_list($data['topics']) || count($data['topics']) > UnsCatalogProvisioner::MAX_ASSETS
            || array_filter($data['topics'], static fn (mixed $t): bool => !is_string($t)) !== []) {
            return new JsonResponse(['error' => 'Invalid payload'], 400);
        }
        $policy = UnsPolicy::fromProcessEnvironment();
        foreach ($data['topics'] as $topic) {
            if (!$policy->allows($topic)) {
                return new JsonResponse(['error' => 'A selected topic is not allowed on this instance'], 403);
            }
        }

        try {
            $result = $this->provisioner->provision($workspace, $data['model'], ($data['enable'] ?? false) === true, $data['topics']);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return new JsonResponse($result, 201);
    }

    private static function csrfKey(string $id): string
    {
        return 'csrf_workspace_uns_'.$id;
    }

    /** @return array<string, mixed>|null */
    private function payload(Request $request): ?array
    {
        $body = $request->getContent();
        if (strlen($body) > self::MAX_BODY_BYTES) {
            return null;
        }
        $data = json_decode($body, true, 16);

        return is_array($data) ? $data : null;
    }

    private function authorizedWorkspace(string $id, Request $request): Workspace|JsonResponse
    {
        if (!$this->getUser() instanceof AppUser) {
            return new JsonResponse(['error' => 'Authentication required'], 401);
        }
        $workspace = $this->em->getRepository(Workspace::class)->find($id);
        if (!$workspace) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }
        if (!$this->isGranted(WorkspaceVoter::MANAGE, $workspace)) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }
        $token = $request->headers->get('X-CSRF-Token', '');
        $expected = $request->getSession()->get(self::csrfKey($id));
        if (!is_string($expected) || !hash_equals($expected, $token)) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        return $workspace;
    }
}
