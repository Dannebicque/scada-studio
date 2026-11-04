<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'uniq_app_user_email', columns: ['email'])]
class AppUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    public string $id;

    #[ORM\Column(length: 180)]
    public string $email;

    #[ORM\Column(length: 255)]
    private string $passwordHash;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    public function __construct(string $email, string $passwordHash)
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->email = mb_strtolower(trim($email));
        $this->passwordHash = $passwordHash;
    }

    public function getUserIdentifier(): string { return $this->email; }
    public function getPassword(): string { return $this->passwordHash; }
    public function setPasswordHash(string $hash): void { $this->passwordHash = $hash; }
    public function getRoles(): array { return array_values(array_unique([...$this->roles, 'ROLE_USER'])); }
    public function eraseCredentials(): void {}
}
