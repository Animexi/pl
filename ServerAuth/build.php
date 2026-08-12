<?php

/**
 * Скрипт сборки ServerAuth.phar
 * Использование: php build.php
 */

$pharPath = __DIR__ . "/ServerAuth.phar";
$sourceDir = __DIR__;

// Удаление старого файла если существует
if (file_exists($pharPath)) {
    unlink($pharPath);
    echo "Старый файл удален\n";
}

echo "Создание Phar архива...\n";

try {
    $phar = new Phar($pharPath, 0, "ServerAuth.phar");
    
    // Добавление метаданных
    $phar->setMetadata([
        "name" => "ServerAuth",
        "version" => "2.0.0",
        "author" => "ServerAuth Team"
    ]);
    
    // Добавление файлов из src/
    $phar->buildFromDirectory($sourceDir . "/src", "/\.php$/");
    echo "Исходный код добавлен\n";
    
    // Добавление ресурсов
    $phar->addFile($sourceDir . "/plugin.yml", "plugin.yml");
    echo "plugin.yml добавлен\n";
    
    $phar->addFile($sourceDir . "/resources/config.yml", "resources/config.yml");
    echo "config.yml добавлен\n";
    
    $phar->addFile($sourceDir . "/resources/messages.yml", "resources/messages.yml");
    echo "messages.yml добавлен\n";
    
    // Установка точки входа
    $phar->setStub("<?php __HALT_COMPILER();");
    
    echo "\n✅ Сборка завершена успешно!\n";
    echo "Файл: " . $pharPath . "\n";
    echo "Размер: " . round(filesize($pharPath) / 1024, 2) . " KB\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка при сборке: " . $e->getMessage() . "\n";
    exit(1);
}
