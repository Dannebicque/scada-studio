<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'data_source')]
#[ORM\UniqueConstraint(name: 'uniq_source_workspace_key', columns: ['workspace_id', 'source_key'])]
#[ApiResource(operations: [new Get(), new GetCollection()])]
class DataSource
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    public string $id;

    #[ORM\ManyToOne(targetEntity: Workspace::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Workspace $workspace;

    #[ORM\Column(name: 'source_key', length: 100)]
    public string $key;

    #[ORM\Column(length: 150)]
    public string $name;

    #[ORM\Column(length: 100)]
    public string $connectorType;

    #[ORM\Column(type: 'boolean')]
    public bool $enabled = false;

    public function __construct(Workspace $workspace, string $key, string $name, string $connectorType)
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->workspace = $workspace;
        $this->key = $key;
        $this->name = $name;
        $this->connectorType = $connectorType;
    }
}
