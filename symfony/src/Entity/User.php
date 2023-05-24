<?php

namespace Gamecon\Symfony\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gamecon\Symfony\Doctrine\UserSetGameconEmailListener;
use Gamecon\Symfony\Generator\GameconUserIdPseudoGenerator;
use Gamecon\Symfony\Repository\UserRepository;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\EntityListeners([UserSetGameconEmailListener::class])]
class User implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Column]
    #[ORM\CustomIdGenerator(GameconUserIdPseudoGenerator::class)]
    private ?int $id = null;

    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getRoles(): array
    {
        return [];
    }

    public function eraseCredentials()
    {
        // TODO: Implement eraseCredentials() method.
    }

    public function getUserIdentifier(): string
    {
        return (string)$this->getId();
    }

}
