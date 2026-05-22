<?php
// bin/import.php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Infrastructure\Service\BibleImporter;

// Проверяем аргументы командной строки
if ($argc < 2) {
    echo "Использование: php bin/import.php [ПУТЬ_К_JSON]\n";
    echo "Пример: php bin/import.php data/import/rst.json\n";
    exit(1);
}

$filePath = $argv[1];

echo "=== Запуск импорта перевода [{$filePath}] ===\n";
$start = microtime(true);

try {
    $importer = new BibleImporter();
    $importer->importFromJson($filePath);

    $time = round(microtime(true) - $start, 3);
    echo "SUCCESS: Импорт успешно завершен за {$time} сек.\n";
} catch (\Throwable $e) {
    echo "ERROR: Ошибка импорта: " . $e->getMessage() . "\n";
    exit(1);
}

