<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'engineering_unit')]
#[ApiResource(operations: [new Get(), new GetCollection()], security: "is_granted('PUBLIC_ACCESS')")]
class Unit
{
    #[ORM\Id]
    #[ORM\Column(length: 40)]
    public string $code;

    #[ORM\Column(length: 100)]
    public string $label;

    #[ORM\Column(length: 30)]
    public string $symbol;

    public function __construct(string $code, string $label, string $symbol)
    {
        $this->code = $code;
        $this->label = $label;
        $this->symbol = $symbol;
    }
}
