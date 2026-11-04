<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'uns_asset')]
#[ORM\UniqueConstraint(name: 'uniq_uns_asset_parent_key', columns: ['workspace_id', 'parent_id', 'asset_key'])]
class UnsAsset
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    public string $id;

    #[ORM\ManyToOne(targetEntity: Workspace::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Workspace $workspace;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'parent_id', nullable: true, onDelete: 'CASCADE')]
    public ?self $parent;

    #[ORM\Column(name: 'asset_key', length: 100)]
    public string $key;

    #[ORM\Column(length: 150)]
    public string $name;

    #[ORM\Column(length: 40)]
    public string $type;

    public function __construct(Workspace $workspace, string $key, string $name, string $type, ?self $parent = null)
    {
        if ($parent !== null && $parent->workspace->id !== $workspace->id) {
            throw new \InvalidArgumentException('Parent must belong to the same workspace.');
        }
        $this->id = Uuid::v7()->toRfc4122();
        $this->workspace = $workspace;
        $this->parent = $parent;
        $this->key = $key;
        $this->name = $name;
        $this->type = $type;
    }
}
