<?php
// src/Infrastructure/Persistence/SqliteUserRepository.php

namespace App\Infrastructure\Persistence;

use App\Domain\Model\User;
use App\Domain\Repository\UserRepositoryInterface;
use PDO;

class SqliteUserRepository implements UserRepositoryInterface
{
    private PDO $pdo;

    public function __construct()
    {
        // Внутри Docker путь к базе фиксирован через примонтированную папку
        $dbPath = '/app/data/bible.db';

        $this->pdo = new PDO("sqlite:" . $dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Инициализация таблицы при первом запуске
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id TEXT PRIMARY KEY,
                username TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL
            )
        ");
    }

    public function save(User $user): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO users (id, username, password_hash) 
            VALUES (:id, :username, :password_hash)
            ON CONFLICT(id) DO UPDATE SET
                username = :username,
                password_hash = :password_hash
        ");

        $stmt->execute([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'password_hash' => $user->getPasswordHash()
        ]);
    }

    public function findById(string $id): ?User
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return new User($row['id'], $row['username'], $row['password_hash']);
    }

    public function findByUsername(string $username): ?User
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $row = $stmt->fetch();

        if (!$row) return null;

        return new User($row['id'], $row['username'], $row['password_hash']);
    }
}

