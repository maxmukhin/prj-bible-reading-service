<?php
// src/Domain/Model/FriendRequest.php

namespace App\Domain\Model;

use App\Domain\ValueObject\RequestStatus;
use DateTimeImmutable;
use DomainException;

class FriendRequest
{
    private RequestStatus $status;

    public function __construct(
        private readonly string $id,
        private readonly string $senderId,
        private readonly string $receiverId,
        private readonly DateTimeImmutable $createdAt
    ) {
        if ($this->senderId === $this->receiverId) {
            throw new DomainException("Вы не можете отправить запрос в друзья самому себе.");
        }

        $this->status = RequestStatus::PENDING;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSenderId(): string
    {
        return $this->senderId;
    }

    public function getReceiverId(): string
    {
        return $this->receiverId;
    }

    public function getStatus(): RequestStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Бизнес-логика: подтверждение дружбы
     */
    public function accept(): void
    {
        if ($this->status !== RequestStatus::PENDING) {
            throw new DomainException("Можно подтвердить только запросы в статусе ожидания (Pending).");
        }

        $this->status = RequestStatus::ACCEPTED;
    }

    /**
     * Метод для гидрации сущности из БД (инфраструктурный хелпер)
     */
    public static function fromPersistence(
        string $id,
        string $senderId,
        string $receiverId,
        string $status,
        DateTimeImmutable $createdAt
    ): self {
        $request = new self($id, $senderId, $receiverId, $createdAt);
        $request->status = RequestStatus::from($status);
        return $request;
    }
}
