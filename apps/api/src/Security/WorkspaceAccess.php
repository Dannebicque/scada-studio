<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\AppUser;
use App\Entity\Workspace;
use App\Entity\WorkspaceMember;
use Doctrine\ORM\EntityManagerInterface;

final class WorkspaceAccess
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function canView(?AppUser $user, Workspace $workspace): bool
    {
        return $this->membership($user, $workspace) !== null;
    }

    public function canEdit(?AppUser $user, Workspace $workspace): bool
    {
        return in_array($this->membership($user, $workspace)?->role, ['editor', 'owner'], true);
    }

    public function canManage(?AppUser $user, Workspace $workspace): bool
    {
        return $this->membership($user, $workspace)?->role === 'owner';
    }

    private function membership(?AppUser $user, Workspace $workspace): ?WorkspaceMember
    {
        if ($user === null) {
            return null;
        }

        return $this->em->getRepository(WorkspaceMember::class)->findOneBy([
            'user' => $user,
            'workspace' => $workspace,
        ]);
    }
}
