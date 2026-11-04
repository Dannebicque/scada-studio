<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'tag')]
#[ORM\UniqueConstraint(name: 'uniq_tag_source_key', columns: ['source_id', 'tag_key'])]
#[ApiResource(operations: [new Get(), new GetCollection()])]
class Tag
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    public string $id;

    #[ORM\ManyToOne(targetEntity: DataSource::class)]
    #[ORM\JoinColumn(name: 'source_id', nullable: false, onDelete: 'CASCADE')]
    public DataSource $source;

    #[ORM\Column(name: 'tag_key', length: 150)]
    public string $key;

    #[ORM\Column(length: 150)]
    public string $name;

    #[ORM\Column(length: 20)]
    public string $valueType;

    #[ORM\ManyToOne(targetEntity: Unit::class)]
    #[ORM\JoinColumn(name: 'unit_code', referencedColumnName: 'code', nullable: true, onDelete: 'SET NULL')]
    public ?Unit $unit = null;

    #[ORM\Column(type: 'boolean')]
    public bool $readOnly = true;

    public function __construct(DataSource $source, string $key, string $name, string $valueType, ?Unit $unit = null)
    {
        if (!in_array($valueType, ['boolean', 'integer', 'float', 'string'], true)) {
            throw new \InvalidArgumentException('Unsupported tag value type.');
        }

        $this->id = Uuid::v7()->toRfc4122();
        $this->source = $source;
        $this->key = $key;
        $this->name = $name;
        $this->valueType = $valueType;
        $this->unit = $unit;
    }
}
