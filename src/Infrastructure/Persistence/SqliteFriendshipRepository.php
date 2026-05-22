<?php
namespace App\Infrastructure\Persistence;

use PDO;

class SqliteFriendshipRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = new PDO("sqlite:/app/data/bible.db");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS friendships (
                user_id TEXT NOT NULL,
                friend_id TEXT NOT NULL,
                status TEXT NOT NULL, -- 'pending' или 'accepted'
                PRIMARY KEY (user_id, friend_id)
            );
        ");
    }

    public function addFriendRequest(string $userId, string $friendId): void
    {
        $stmt = $this->pdo->prepare("
            INSERT OR IGNORE INTO friendships (user_id, friend_id, status) 
            VALUES (:user_id, :friend_id, 'accepted') -- Для простоты делаем авто-апрув
        ");
        $stmt->execute(['user_id' => $userId, 'friend_id' => $friendId]);
    }

    public function getFriendIds(string $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT friend_id FROM friendships WHERE user_id = :user_id AND status = 'accepted'");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
}

