<?php
// src/Application/UseCase/UpdateNoteUseCase.php

namespace App\Application\UseCase;

use App\Domain\Repository\NoteRepositoryInterface;
use App\Domain\Model\Note;
use RuntimeException;

class UpdateNoteUseCase
{
    public function __construct(
        private NoteRepositoryInterface $noteRepository
    ) {}

    public function execute(string $noteId, string $userId, string $newContent): Note
    {
        $note = $this->noteRepository->findById($noteId);
        if (!$note) {
            throw new RuntimeException("Заметка не найдена.");
        }

        if ($note->getUserId() !== $userId) {
            throw new RuntimeException("Доступ ограничен. Вы не являетесь автором этой заметки.");
        }

        // Создаем обновленную доменную модель (сохраняя оригинальный ID и дату создания)
        $updatedNote = new Note(
            $note->getId(),
            $note->getUserId(),
            $note->getTarget(),
            trim($newContent),
            $note->getCreatedAt()
        );

        $this->noteRepository->save($updatedNote);

        return $updatedNote;
    }
}

