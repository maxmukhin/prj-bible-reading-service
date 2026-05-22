<?php
// src/Domain/Repository/NoteRepositoryInterface.php

namespace App\Domain\Repository;

use App\Domain\Model\Note;
use App\Domain\ValueObject\BibleTarget;

interface NoteRepositoryInterface
{
    public function save(Note $note): void;

    public function findById(string $id): ?Note;

    /**
     * Найти все заметки конкретного пользователя по заданной координате Писания
     */
    public function findByUserAndTarget(string $userId, BibleTarget $target): array;

    /**
     * Найти заметки списка пользователей (друзей) для конкретной книги/главы
     */
    public function findByUsersAndTarget(array $userIds, BibleTarget $target): array;
}

