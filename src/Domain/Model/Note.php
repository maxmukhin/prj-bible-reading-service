<?php
// src/Domain/Model/Note.php

namespace App\Domain\Model;

use DateTimeImmutable;

class Note
{
    public function __construct(
        private string $id,
        private string $userId,
        private NoteTarget $target,
        private string $content,
        private DateTimeImmutable $createdAt
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getTarget(): NoteTarget
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
}
