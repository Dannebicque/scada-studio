<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'workspace_member')]
#[ORM\UniqueConstraint(name: 'uniq_workspace_member', columns: ['workspace_id', 'user_id'])]
class WorkspaceMember
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Workspace::class)]
    #[ORM\JoinColumn(name: 'workspace_id', nullable: false, onDelete: 'CASCADE')]
    public Workspace $workspace;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: AppUser::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    public AppUser $user;

    #[ORM\Column(length: 20)]
    public string $role;

    public function __construct(Workspace $workspace, AppUser $user, string $role = 'viewer')
    {
        if (!in_array($role, ['viewer', 'editor', 'owner'], true)) {
            throw new \InvalidArgumentException('Unsupported workspace role.');
        }
        $this->workspace = $workspace;
        $this->user = $user;
        $this->role = $role;
    }
}
