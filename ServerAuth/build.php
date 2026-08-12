<?php

declare(strict_types=1);

/**
 * Скрипт сборки ServerAuth.phar
 * 
 * Использование: php -dphar.readonly=0 build.php
 */

$phar = new Phar(__DIR__ . '/ServerAuth.phar', 0, 'ServerAuth.phar');

// Добавление файлов из src/
$phar->buildFromDirectory(__DIR__ . '/src');

// Добавление ресурсов
$phar->addFile(__DIR__ . '/plugin.yml');
$phar->addFile(__DIR__ . '/resources/config.yml');
$phar->addFile(__DIR__ . '/resources/messages.yml');

// Установка точки входа
$phar->setStub($phar->createDefaultStub('ServerAuth/ServerAuthPlugin.php'));

echo "ServerAuth.phar успешно создан!\n";
