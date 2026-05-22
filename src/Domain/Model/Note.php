<?php
// src/Domain/Model/Note.php

namespace App\Domain\Model;

use App\Domain\ValueObject\BibleTarget;
use DateTimeImmutable;

class Note
{
    public function __construct(
        private readonly string $id,
        private readonly string $userId,
        private readonly BibleTarget $target,
        private string $content,
        private readonly DateTimeImmutable $createdAt
    ) {
        if (empty(trim($this->content))) {
            throw new \InvalidArgumentException("Текст заметки не может быть пустым.");
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getTarget(): BibleTarget
    {
        return $this->target;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Бизнес-логика: изменение контента заметки
     */
    public function edit(string $newContent): void
    {
        if (empty(trim($newContent))) {
            throw new \InvalidArgumentException("Текст заметки не может быть пустым.");
        }
        $this->content = $newContent;
    }
}

