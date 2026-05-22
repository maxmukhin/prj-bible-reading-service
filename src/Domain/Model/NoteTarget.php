<?php
// src/Domain/Model/NoteTarget.php

namespace App\Domain\Model;

/**
 * Value Object, инкапсулирующий координаты привязки заметки.
 */
class NoteTarget
{
    const VERSE_TYPE = 'verse';
    const CHAPTER_TYPE = 'chapter';

    public function __construct(
        private string $bookCode,
        private ?int $chapter = null,
        private ?int $verse = null
    ) {}

    public function getBookCode(): string
    {
        return $this->bookCode;
    }

    public function getChapter(): ?int
    {
        return $this->chapter;
    }

    public function getVerse(): ?int
    {
        return $this->verse;
    }

    /**
     * Проверяет, указывает ли цель на конкретный стих или на всю главу
     */
    public function isVerseTarget(): bool
    {
        return $this->verse !== null;
    }

    public function getTargetType(): string
    {
        return $this->isVerseTarget() ? self::VERSE_TYPE : self::CHAPTER_TYPE;
    }
}

