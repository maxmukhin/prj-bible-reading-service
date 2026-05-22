<?php
// src/Domain/Repository/FriendRequestRepositoryInterface.php

namespace App\Domain\Repository;

interface FriendRequestRepositoryInterface
{
    public function addFriendRequest(string $userId, string $friendId): void;

    public function getFriendIds(string $userId): array;



    //public function save(FriendRequest $request): void;
    //
    //public function findById(string $id): ?FriendRequest;
    //
    ///**
    // * Найти существующий запрос между двумя пользователями (в любую сторону)
    // */
    //public function findBetweenUsers(string $userA, string $userB): ?FriendRequest;
    //
    ///**
    // * Получить все входящие запросы в статусе PENDING для конкретного пользователя
    // */
    //public function findPendingIncomingForUser(string $userId): array;
    //
    ///**
    // * Получить ID всех подтвержденных друзей пользователя
    // * (Возвращает массив строк-ID, чтобы эффективно использовать в WHERE IN подзапросах заметок)
    // */
    //public function findFriendIdsForUser(string $userId): array;
}
