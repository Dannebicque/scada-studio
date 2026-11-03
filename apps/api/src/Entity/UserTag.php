<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'user_tag')]
#[ORM\UniqueConstraint(name: 'uniq_user_tag_owner_tag', columns: ['owner_id', 'tag_id'])]
class UserTag
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    public string $id;

    #[ORM\ManyToOne(targetEntity: AppUser::class)]
    #[ORM\JoinColumn(name: 'owner_id', nullable: false, onDelete: 'CASCADE')]
    public AppUser $owner;

    #[ORM\ManyToOne(targetEntity: Tag::class)]
    #[ORM\JoinColumn(name: 'tag_id', nullable: false, onDelete: 'CASCADE')]
    public Tag $tag;

    #[ORM\Column(length: 150, nullable: true)]
    public ?string $displayName = null;

    #[ORM\Column(type: 'boolean')]
    public bool $favorite = false;

    public function __construct(AppUser $owner, Tag $tag)
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->owner = $owner;
        $this->tag = $tag;
    }
}
