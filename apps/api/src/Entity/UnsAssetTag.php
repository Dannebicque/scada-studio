<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'uns_asset_tag')]
class UnsAssetTag
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: UnsAsset::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public UnsAsset $asset;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Tag::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Tag $tag;

    public function __construct(UnsAsset $asset, Tag $tag)
    {
        if ($asset->workspace->id !== $tag->source->workspace->id) {
            throw new \InvalidArgumentException('Tag must belong to the asset workspace.');
        }
        $this->asset = $asset;
        $this->tag = $tag;
    }
}
