<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Historique numérique d'un tag (booléens stockés en 0/1). Table de série temporelle en ajout seul :
 * les écritures passent par TelemetryHistory (INSERT … ON CONFLICT DO NOTHING), cette entité ne sert
 * qu'à décrire le schéma à Doctrine.
 */
#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'tag_sample')]
#[ORM\Index(name: 'idx_tag_sample_observed_at', columns: ['observed_at'])]
class TagSample
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Tag::class)]
    #[ORM\JoinColumn(name: 'tag_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    public Tag $tag;

    #[ORM\Id]
    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $observedAt;

    #[ORM\Column(type: 'float')]
    public float $value;

    public function __construct(Tag $tag, \DateTimeImmutable $observedAt, float $value)
    {
        $this->tag = $tag;
        $this->observedAt = $observedAt;
        $this->value = $value;
    }
}
