<?php
// src/Domain/ValueObject/BibleTarget.php

namespace App\Domain\ValueObject;

use InvalidArgumentException;

class BibleTarget
{
    private function __construct(
        private readonly TargetType $type,
        private readonly string $bookCode,  // Например: 'GEN', 'JHN'
        private readonly ?int $chapter = null,
        private readonly ?int $verse = null
    ) {}

    public static function forBook(string $bookCode): self
    {
        return new self(TargetType::BOOK, strtoupper($bookCode));
    }

    public static function forChapter(string $bookCode, int $chapter): self
    {
        if ($chapter <= 0) {
            throw new InvalidArgumentException("Номер главы должен быть больше нуля.");
        }
        return new self(TargetType::CHAPTER, strtoupper($bookCode), $chapter);
    }

    public static function forVerse(string $bookCode, int $chapter, int $verse): self
    {
        if ($chapter <= 0 || $verse <= 0) {
            throw new InvalidArgumentException("Номер главы и стиха должен быть больше нуля.");
        }
        return new self(TargetType::VERSE, strtoupper($bookCode), $chapter, $verse);
    }

    public function getType(): TargetType
    {
        return $this->type;
    }

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
}

