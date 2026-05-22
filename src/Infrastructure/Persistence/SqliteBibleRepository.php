<?php
// src/Infrastructure/Persistence/SqliteBibleRepository.php

namespace App\Infrastructure\Persistence;

use App\Domain\Repository\BibleRepositoryInterface;
use PDO;

class SqliteBibleRepository implements BibleRepositoryInterface
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = new PDO("sqlite:/app/data/bible.db");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Создаем таблицу с составным уникальным индексом для мгновенного поиска
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

        $this->seedDemoData();
    }

    public function getAvailableVersions(): array
    {
        return [
            ['code' => 'SYNODAL', 'name' => 'Синодальный перевод (1876)']
        ];
    }

    public function getBooks(string $version): array
    {
        $stmt = $this->pdo->prepare("
            SELECT book_code as code, book_name as name 
            FROM bible_verses 
            WHERE version = :version
            GROUP BY book_code, book_name, book_order
            ORDER BY book_order ASC
        ");
        $stmt->execute([
            'version' => strtoupper($version),
        ]);

        return $stmt->fetchAll();
    }

    public function getChapterVerses(string $version, string $bookCode, int $chapter): array
    {
        $stmt = $this->pdo->prepare("
            SELECT verse, verse_text as text 
            FROM bible_verses 
            WHERE version = :version AND book_code = :book_code AND chapter = :chapter
            ORDER BY verse ASC
        ");
        $stmt->execute([
            'version' => strtoupper($version),
            'book_code' => strtoupper($bookCode),
            'chapter' => $chapter
        ]);

        return $stmt->fetchAll();
    }

    public function hasChapter(string $version, string $bookCode, int $chapter): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1 FROM bible_verses 
            WHERE version = :version AND book_code = :book_code AND chapter = :chapter 
            LIMIT 1
        ");
        $stmt->execute([
            'version' => strtoupper($version),
            'book_code' => strtoupper($bookCode),
            'chapter' => $chapter
        ]);
        return (bool)$stmt->fetch();
    }

    /**
     * Наполняем базу демонстрационными данными, если она пуста
     */
    private function seedDemoData(): void
    {
        $count = $this->pdo->query("SELECT COUNT(*) FROM bible_verses")->fetchColumn();
        if ($count > 0) return;

        $demoVerses = [
            ['RST', 'GEN', 1, 1, 'В начале сотворил Бог небо и землю.'],
            ['RST', 'GEN', 1, 2, 'Земля же была безвидна и пуста, и тьма над бездною, и Дух Божий носился над водою.'],
            ['RST', 'GEN', 1, 3, 'И сказал Бог: да будет свет. И стал свет.'],
            ['RST', 'GEN', 1, 4, 'И увидел Бог свет, что он хорош, и отделил Бог свет от тьмы.'],
            ['RST', 'GEN', 2, 1, 'Так совершены небо и землю и все воинство их.'] // Для теста перехода на 2 главу
        ];

        $stmt = $this->pdo->prepare("
            INSERT OR IGNORE INTO bible_verses (version, book_code, chapter, verse, verse_text)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($demoVerses as $verse) {
            $stmt->execute($verse);
        }
    }
}

