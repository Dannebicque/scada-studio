<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'dashboard')]
#[ORM\UniqueConstraint(name: 'uniq_dashboard_workspace_slug', columns: ['workspace_id', 'slug'])]
#[ApiResource(operations: [new Get(), new GetCollection()])]
class Dashboard
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    public string $id;

    #[ORM\ManyToOne(targetEntity: Workspace::class)]
    #[ORM\JoinColumn(name: 'workspace_id', nullable: false, onDelete: 'CASCADE')]
    public Workspace $workspace;

    #[ORM\Column(length: 120)]
    public string $name;

    #[ORM\Column(length: 120)]
    public string $slug;

    /** @var list<array{type: string, tagId: string, title: string}> */
    #[ORM\Column(type: 'json')]
    public array $widgets = [];

    public function __construct(Workspace $workspace, string $name, string $slug)
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->workspace = $workspace;
        $this->name = $name;
        $this->slug = $slug;
    }
}
