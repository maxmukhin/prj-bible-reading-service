<?php
// src/Infrastructure/Persistence/SqliteNoteRepository.php

namespace App\Infrastructure\Persistence;

use App\Domain\Model\Note;
use App\Domain\ValueObject\BibleTarget;
use App\Domain\Repository\NoteRepositoryInterface;
use PDO;
use DateTimeImmutable;

class SqliteNoteRepository implements NoteRepositoryInterface
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = new PDO("sqlite:/app/data/bible.db");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Создаем таблицу заметок
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS notes (
                id TEXT PRIMARY KEY,
                user_id TEXT NOT NULL,
                target_type TEXT NOT NULL,
                book_code TEXT NOT NULL,
                chapter INTEGER,
                verse INTEGER,
                content TEXT NOT NULL,
                created_at TEXT NOT NULL
            )
        ");
    }

    public function save(Note $note): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO notes (id, user_id, target_type, book_code, chapter, verse, content, created_at)
            VALUES (:id, :user_id, :target_type, :book_code, :chapter, :verse, :content, :created_at)
            ON CONFLICT(id) DO UPDATE SET content = :content
        ");

        $target = $note->getTarget();

        $stmt->execute([
            'id'          => $note->getId(),
            'user_id'     => $note->getUserId(),
            'target_type' => $target->getType()->value,
            'book_code'   => $target->getBookCode(),
            'chapter'     => $target->getChapter(),
            'verse'       => $target->getVerse(),
            'content'     => $note->getContent(),
            'created_at'  => $note->getCreatedAt()->format(DATE_ATOM)
        ]);
    }

    public function findById(string $id): ?Note
    {
        $stmt = $this->pdo->prepare("SELECT * FROM notes WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->mapRowToNote($row) : null;
    }

    public function findByUserAndTarget(string $userId, BibleTarget $target): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM notes 
            WHERE user_id = :user_id 
              AND target_type = :target_type 
              AND book_code = :book_code 
              AND (chapter = :chapter OR chapter IS NULL)
              AND (verse = :verse OR verse IS NULL)
            ORDER BY created_at DESC
        ");

        $stmt->execute([
            'user_id'     => $userId,
            'target_type' => $target->getType()->value,
            'book_code'   => $target->getBookCode(),
            'chapter'     => $target->getChapter(),
            'verse'       => $target->getVerse()
        ]);

        return array_map([$this, 'mapRowToNote'], $stmt->fetchAll());
    }

    /**
     * Оптимизированный хелпер: получить ВСЕ заметки пользователя для конкретной главы
     * (и уровня главы, и уровня конкретных стихов), чтобы не делать по 30 запросов на страницу.
     */
    public function findAllForChapterView(string $userId, string $bookCode, int $chapter): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM notes 
            WHERE user_id = :user_id 
              AND book_code = :book_code 
              AND chapter = :chapter
            ORDER BY verse ASC, created_at DESC
        ");

        $stmt->execute([
            'user_id'   => $userId,
            'book_code' => strtoupper($bookCode),
            'chapter'   => $chapter
        ]);

        return array_map([$this, 'mapRowToNote'], $stmt->fetchAll());
    }

    public function findByUsersAndTarget(array $userIds, BibleTarget $target): array
    {
        if (empty($userIds)) return [];

        $inClause = implode(',', array_fill(0, count($userIds), '?'));

        $sql = "SELECT * FROM notes WHERE user_id IN ($inClause) 
                AND target_type = ? AND book_code = ? AND chapter = ? AND verse = ?
                ORDER BY created_at DESC";

        $stmt = $this->pdo->prepare($sql);

        $params = array_merge(
            $userIds,
            [$target->getType()->value, $target->getBookCode(), $target->getChapter(), $target->getVerse()]
        );

        $stmt->execute($params);
        return array_map([$this, 'mapRowToNote'], $stmt->fetchAll());
    }

    private function mapRowToNote(array $row): Note
    {
        $targetType = \App\Domain\ValueObject\TargetType::from($row['target_type']);

        $target = match($targetType) {
            \App\Domain\ValueObject\TargetType::BOOK => BibleTarget::forBook($row['book_code']),
            \App\Domain\ValueObject\TargetType::CHAPTER => BibleTarget::forChapter($row['book_code'], (int)$row['chapter']),
            \App\Domain\ValueObject\TargetType::VERSE => BibleTarget::forVerse($row['book_code'], (int)$row['chapter'], (int)$row['verse']),
        };

        return new Note(
            $row['id'],
            $row['user_id'],
            $target,
            $row['content'],
            new DateTimeImmutable($row['created_at'])
        );
    }
}