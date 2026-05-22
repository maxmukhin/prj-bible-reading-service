<?php
// src/Application/UseCase/SaveNoteUseCase.php

namespace App\Application\UseCase;

use App\Domain\Model\Note;
use App\Domain\Model\NoteTarget;
use App\Infrastructure\Persistence\SqliteNoteRepository;
use DateTimeImmutable;

class SaveNoteUseCase
{
    public function __construct(
        private SqliteNoteRepository $noteRepository
    ) {}

    /**
     * Унифицированный метод создания или обновления заметки через Domain Model
     */
    public function execute(
        ?string $noteId,
        string $userId,
        string $bookCode,
        int $chapter,
        ?int $verse,
        string $content
    ): Note {
        // Конструируем Value Object целиком на уровне UseCase
        $target = new NoteTarget($bookCode, $chapter, $verse);

        if (!empty($noteId)) {
            // Редактирование: создаем объект со старым ID и новыми координатами/контентом
            $note = new Note($noteId, $userId, $target, $content, new DateTimeImmutable());
            $this->noteRepository->update($note);
        } else {
            // Создание: генерируем новый ID
            $newId = bin2hex(random_bytes(8));
            $note = new Note($newId, $userId, $target, $content, new DateTimeImmutable());
            $this->noteRepository->save($note);
        }

        return $note;
    }
}
