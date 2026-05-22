<?php
// src/Application/UseCase/CreateNoteUseCase.php

namespace App\Application\UseCase;

use App\Domain\Model\Note;
use App\Domain\ValueObject\BibleTarget;
use App\Domain\Repository\NoteRepositoryInterface;
use DateTimeImmutable;

class CreateNoteUseCase
{
    public function __construct(
        private NoteRepositoryInterface $noteRepository
    ) {}

    public function execute(string $userId, string $bookCode, int $chapter, ?int $verse, string $content): void
    {
        // 1. Формируем правильный таргет на основании наличия или отсутствия стиха
        if ($verse && $verse > 0) {
            $target = BibleTarget::forVerse($bookCode, $chapter, $verse);
        } else {
            $target = BibleTarget::forChapter($bookCode, $chapter);
        }

        // 2. Генерируем уникальный ID бизнес-сущности
        $id = bin2hex(random_bytes(16));

        // 3. Создаем доменную модель (внутри сработает встроенная бизнес-валидация контента)
        $note = new Note(
            $id,
            $userId,
            $target,
            $content,
            new DateTimeImmutable()
        );

        // 4. Сохраняем порт в инфраструктуру
        $this->noteRepository->save($note);
    }
}