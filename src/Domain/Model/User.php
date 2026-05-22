<?php
// src/Domain/Model/User.php

namespace App\Domain\Model;

class User
{
    public function __construct(
        private readonly string $id,
        private readonly string $username,
        private string $passwordHash
    ) {
        if (empty(trim($this->username))) {
            throw new \InvalidArgumentException("Имя пользователя не может быть пустым.");
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function changePassword(string $newPasswordHash): void
    {
        $this->passwordHash = $newPasswordHash;
    }
}
