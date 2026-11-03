<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tag_value')]
class TagValue
{
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: Tag::class)]
    #[ORM\JoinColumn(name: 'tag_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    public Tag $tag;

    #[ORM\Column(type: 'json')]
    public mixed $value;

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $observedAt;

    public function __construct(Tag $tag, mixed $value, ?\DateTimeImmutable $observedAt = null)
    {
        $this->tag = $tag;
        $this->update($value, $observedAt);
    }

    public function update(mixed $value, ?\DateTimeImmutable $observedAt = null): void
    {
        $valid = match ($this->tag->valueType) {
            'boolean' => is_bool($value),
            'integer' => is_int($value),
            'float' => is_float($value) || is_int($value),
            'string' => is_string($value),
            default => false,
        };
        if (!$valid || (is_float($value) && !is_finite($value))) {
            throw new \InvalidArgumentException('Value does not match tag type.');
        }
        $this->value = $value;
        $this->observedAt = $observedAt ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
