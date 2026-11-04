<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'user_dashboard')]
#[ORM\UniqueConstraint(name: 'uniq_user_dashboard_slug', columns: ['workspace_id', 'owner_id', 'slug'])]
class UserDashboard
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    public string $id;

    #[ORM\ManyToOne(targetEntity: Workspace::class)]
    #[ORM\JoinColumn(name: 'workspace_id', nullable: false, onDelete: 'CASCADE')]
    public Workspace $workspace;

    #[ORM\ManyToOne(targetEntity: AppUser::class)]
    #[ORM\JoinColumn(name: 'owner_id', nullable: false, onDelete: 'CASCADE')]
    public AppUser $owner;

    #[ORM\Column(length: 120)]
    public string $name;

    #[ORM\Column(length: 120)]
    public string $slug;

    /** @var list<array{type: string, tagId: string, title: string}> */
    #[ORM\Column(type: 'json')]
    public array $widgets = [];

    public function __construct(Workspace $workspace, AppUser $owner, string $name, string $slug)
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->workspace = $workspace;
        $this->owner = $owner;
        $this->name = $name;
        $this->slug = $slug;
    }
}
