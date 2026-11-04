<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\AppUser;
use App\Entity\Tag;
use App\Entity\UserTag;
use App\Security\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class PersonalPreferencesWriteController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly WorkspaceAccess $access) {}

    #[Route('/api/me/personal-csrf', methods: ['GET'])]
    public function csrf(Request $request): JsonResponse
    {
        if (!$this->getUser() instanceof AppUser) return new JsonResponse(['error' => 'Authentication required'], 401);
        $session = $request->getSession();
        if (!$session->has('csrf_personal')) $session->set('csrf_personal', bin2hex(random_bytes(32)));
        return new JsonResponse(['token' => $session->get('csrf_personal')], headers: ['Cache-Control' => 'private, no-store']);
    }

    #[Route('/api/me/tags/{tagId}', methods: ['PUT'])]
    public function tag(string $tagId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof AppUser) return new JsonResponse(['error' => 'Authentication required'], 401);
        $expected = $request->getSession()->get('csrf_personal');
        $token = $request->headers->get('X-CSRF-Token');
        if (!is_string($expected) || !is_string($token) || !hash_equals($expected, $token)) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
        }
        $tag = $this->em->getRepository(Tag::class)->find($tagId);
        if (!$tag || !$this->access->canView($user, $tag->source->workspace)) {
            return new JsonResponse(['error' => 'Tag not found'], 404);
        }
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !is_bool($data['favorite'] ?? null) || !array_key_exists('displayName', $data)
            || ($data['displayName'] !== null && !is_string($data['displayName']))) {
            return new JsonResponse(['error' => 'Invalid payload'], 422);
        }
        $name = $data['displayName'] === null ? null : trim($data['displayName']);
        if ($name !== null && mb_strlen($name) > 150) return new JsonResponse(['error' => 'Name too long'], 422);
        $preference = $this->em->getRepository(UserTag::class)->findOneBy(['owner' => $user, 'tag' => $tag]);
        if (!$preference) {
            $preference = new UserTag($user, $tag);
            $this->em->persist($preference);
        }
        $preference->favorite = $data['favorite'];
        $preference->displayName = $name === '' ? null : $name;
        $this->em->flush();
        return new JsonResponse(['tagId' => $tag->id, 'favorite' => $preference->favorite, 'displayName' => $preference->displayName], headers: ['Cache-Control' => 'private, no-store']);
    }
}
