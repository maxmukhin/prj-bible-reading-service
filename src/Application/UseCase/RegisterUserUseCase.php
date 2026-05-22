<?php
// src/Application/UseCase/RegisterUserUseCase.php

namespace App\Application\UseCase;

use App\Domain\Model\User;
use App\Domain\Repository\UserRepositoryInterface;
use Exception;

class RegisterUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    public function execute(string $username, string $plainPassword): void
    {
        if (empty(trim($username)) || empty(trim($plainPassword))) {
            throw new Exception("Имя пользователя и пароль не могут быть пустыми.");
        }

        if ($this->userRepository->findByUsername($username)) {
            throw new Exception("Пользователь с таким именем уже зарегистрирован.");
        }

        // Генерируем надежный строковый ID
        $id = bin2hex(random_bytes(16));

        // Хэшируем пароль (Argon2id поддерживается в PHP 8.3 из коробки)
        $passwordHash = password_hash($plainPassword, PASSWORD_ARGON2ID);

        $user = new User($id, $username, $passwordHash);

        $this->userRepository->save($user);
    }
}

