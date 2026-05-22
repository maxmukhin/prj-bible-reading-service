<?php
// src/Infrastructure/Service/BibleImporter.php

namespace App\Infrastructure\Service;

use PDO;
use RuntimeException;

class BibleImporter
{
    private PDO $pdo;

    private const BOOK_MAP = [
        1 => 'GEN', 2 => 'EXO', 3 => 'LEV', 4 => 'NUM', 5 => 'DEU',
        6 => 'JOSH', 7 => 'JUDG', 8 => 'RUTH', 9 => '1SAM', 10 => '2SAM',
        11 => '1KNG', 12 => '2KNG', 13 => '1CHR', 14 => '2CHR', 15 => 'EZRA',
        16 => 'NEH', 17 => 'ESTH', 18 => 'JOB', 19 => 'PSLM', 20 => 'PROV',
        21 => 'ECCL', 22 => 'SONG', 23 => 'ISA', 24 => 'JER', 25 => 'LAM',
        26 => 'EZEK', 27 => 'DAN', 28 => 'HOS', 29 => 'JOEL', 30 => 'AMOS',
        31 => 'OBAD', 32 => 'JON', 33 => 'MIC', 34 => 'NAHM', 35 => 'HAB',
        36 => 'ZEPH', 37 => 'HAG', 38 => 'ZECH', 39 => 'MAL',

        40 => 'MAT', 41 => 'MRK', 42 => 'LUK', 43 => 'JHN', 44 => 'ACTS',
        45 => 'ROM', 46 => '1COR', 47 => '2COR', 48 => 'GAL', 49 => 'EPH',
        50 => 'PHP', 51 => 'COL', 52 => '1THS', 53 => '2THS', 54 => '1TIM',
        55 => '2TIM', 56 => 'TIT', 57 => 'PHM', 58 => 'HEB', 59 => 'JAS',
        60 => '1PET', 61 => '2PET', 62 => '1JHN', 63 => '2JHN', 64 => '3JHN',
        65 => 'JUDE', 66 => 'REV'
    ];

    public function __construct()
    {
        $this->pdo = new PDO("sqlite:/app/data/bible.db");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Гарантируем создание структуры таблицы в рамках CLI-процесса
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS bible_verses (
                version TEXT NOT NULL,
                book_code TEXT NOT NULL,
                book_order INTEGER NOT NULL,
                book_name TEXT NOT NULL,
                chapter INTEGER NOT NULL,
                verse INTEGER NOT NULL,
                verse_text TEXT NOT NULL,
                PRIMARY KEY (version, book_code, chapter, verse)
            )
        ");
    }

    public function importFromJson(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("Файл не найден: {$filePath}");
        }

        $jsonData = file_get_contents($filePath);
        $data = json_decode($jsonData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Ошибка парсинга JSON: " . json_last_error_msg());
        }

        // Извлекаем код модуля из метаданных (например, "synodal") и переводим в верхний регистр
        $versionCode = strtoupper($data['metadata']['module'] ?? 'SYNODAL');

        if (!isset($data['verses']) || !is_array($data['verses'])) {
            throw new RuntimeException("Неверная структура: отсутствует массив 'verses'.");
        }

        // Отключаем синхронизацию с диском на время транзакции для молниеносной вставки
        $this->pdo->exec("PRAGMA synchronous = OFF");
        $this->pdo->exec("PRAGMA journal_mode = MEMORY");

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO bible_verses (version, book_code, book_order, book_name, chapter, verse, verse_text)
                VALUES (:version, :book, :book_order, :book_name, :chapter, :verse, :text)
                ON CONFLICT(version, book_code, chapter, verse) DO UPDATE SET
                    verse_text = :text,
                    book_name = :book_name,
                    book_order = :book_order
            ");

            foreach ($data['verses'] as $v) {
                $bookId = (int)$v['book'];
                $bookCode = self::BOOK_MAP[$bookId] ?? 'BOOK_' . $bookId;

                $v['book_name'] = mb_ucfirst($v['book_name']);

                $stmt->execute([
                    'version'    => $versionCode,
                    'book'       => $bookCode,
                    'book_order' => $bookId, // Сохраняем оригинальный числовой ID из JSON
                    'book_name'  => trim($v['book_name'] ?? $bookCode),
                    'chapter'    => (int)$v['chapter'],
                    'verse'      => (int)$v['verse'],
                    'text'       => trim($v['text'])
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
