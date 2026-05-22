<?php
// public/index.php

use App\Kernel;

// Подключаем автозагрузчик, который теперь существует в папке vendor
require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};

