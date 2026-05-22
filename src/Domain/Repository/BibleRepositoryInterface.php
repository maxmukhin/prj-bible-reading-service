<?php
// src/Domain/Repository/BibleRepositoryInterface.php

namespace App\Domain\Repository;

interface BibleRepositoryInterface
{
    /**
     * Получить список доступных версий (переводов) Библии
     */
    public function getAvailableVersions(): array;

    /**
     * Получить список книг для конкретной версии
     * @return array Массив вида [['code' => 'GEN', 'name' => 'Бытие'], ...]
     */
    public function getBooks(string $version): array;

    /**
     * Получить все стихи конкретной главы книги
     * @return array Массив вида [[ 'verse' => 1, 'text' => '...' ], ...]
     */
    public function getChapterVerses(string $version, string $bookCode, int $chapter): array;

    /**
     * Проверить существование следующей/предыдущей главы для навигации
     */
    public function hasChapter(string $version, string $bookCode, int $chapter): bool;
}
