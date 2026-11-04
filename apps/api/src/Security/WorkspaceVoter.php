<?php
declare(strict_types=1);

namespace App\Security;

use App\Entity\AppUser;
use App\Entity\Workspace;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

final class WorkspaceVoter extends Voter
{
    public const VIEW = 'WORKSPACE_VIEW';
    public const EDIT = 'WORKSPACE_EDIT';
    public const MANAGE = 'WORKSPACE_MANAGE';

    public function __construct(private readonly WorkspaceAccess $access) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Workspace && in_array($attribute, [self::VIEW, self::EDIT, self::MANAGE], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof AppUser || !$subject instanceof Workspace) return false;

        return match ($attribute) {
            self::VIEW => $this->access->canView($user, $subject),
            self::EDIT => $this->access->canEdit($user, $subject),
            self::MANAGE => $this->access->canManage($user, $subject),
            default => false,
        };
    }
}
