<?php

namespace App\DTO;

use JMS\Serializer\Annotation as JMS;
use Symfony\Component\Validator\Constraints as Assert;

class UserDTO
{
    /**
     * @Assert\NotBlank()
     * @Assert\Email()
     */
    private string $username;

    /**
     * @JMS\Type("array<string>")
     */
    private array $roles = [];

    /**
     * @Assert\NotNull()
     */
    private float $balance;

    public static function getUserDTO(string $username, array $roles, float $balance)
    {
        return (new self)
            ->setUsername($username)
            ->setRoles($roles)
            ->setBalance($balance);
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username)
    {
        $this->username = $username;
        return $this;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function setRoles(array $roles)
    {
        $this->roles = $roles;
        return $this;
    }

    public function getBalance(): float
    {
        return $this->balance;
    }
    
    public function setBalance(float $balance)
    {
        $this->balance = $balance;
        return $this;
    }
}