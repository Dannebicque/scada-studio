<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'workspace')]
#[ORM\UniqueConstraint(name: 'uniq_workspace_slug', columns: ['slug'])]
#[ApiResource(operations: [new Get(), new GetCollection()], security: "is_granted('PUBLIC_ACCESS')")]
class Workspace
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    public string $id;

    #[ORM\Column(length: 100)]
    public string $name;

    #[ORM\Column(length: 100)]
    public string $slug;

    public function __construct(string $name, string $slug)
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->name = $name;
        $this->slug = $slug;
    }
}
