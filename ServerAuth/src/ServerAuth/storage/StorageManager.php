<?php

declare(strict_types=1);

namespace ServerAuth\storage;

use ServerAuth\ServerAuthPlugin;
use pocketmine\utils\Config;

class StorageManager {
    
    private ServerAuthPlugin $plugin;
    private string $playersFolder;
    
    /** @var array<string, array> Кэш загруженных данных игроков */
    private array $playerCache = [];
    
    public function __construct(ServerAuthPlugin $plugin) {
        $this->plugin = $plugin;
        $this->playersFolder = $plugin->getDataFolder() . 
            $plugin->getConfig()->getNested("storage.players-folder", "players") . "/";
        
        // Создание папки для хранения данных игроков
        if (!is_dir($this->playersFolder)) {
            mkdir($this->playersFolder, 0755, true);
        }
    }
    
    /**
     * Проверить существует ли игрок
     */
    public function playerExists(string $playerName): bool {
        $filePath = $this->getPlayerFilePath($playerName);
        return file_exists($filePath);
    }
    
    /**
     * Загрузить данные игрока
     * @return array|null Данные игрока или null если не найден
     */
    public function loadPlayer(string $playerName): ?array {
        // Проверка кэша
        $cacheKey = strtolower($playerName);
        if (isset($this->playerCache[$cacheKey])) {
            return $this->playerCache[$cacheKey];
        }
        
        $filePath = $this->getPlayerFilePath($playerName);
        
        if (!file_exists($filePath)) {
            return null;
        }
        
        try {
            $config = new Config($filePath, Config::JSON);
            $data = $config->getAll();
            
            if (empty($data)) {
                return null;
            }
            
            // Кэширование
            $this->playerCache[$cacheKey] = $data;
            
            return $data;
        } catch (\Exception $e) {
            $this->plugin->getLogger()->warning("Ошибка загрузки данных игрока {$playerName}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Сохранить данные игрока
     * @param string $playerName Имя игрока
     * @param array $data Данные для сохранения
     * @return bool Успешность сохранения
     */
    public function savePlayer(string $playerName, array $data): bool {
        try {
            $filePath = $this->getPlayerFilePath($playerName);
            $config = new Config($filePath, Config::JSON);
            
            $config->setAll($data);
            $config->save();
            
            // Обновление кэша
            $cacheKey = strtolower($playerName);
            $this->playerCache[$cacheKey] = $data;
            
            return true;
        } catch (\Exception $e) {
            $this->plugin->getLogger()->warning("Ошибка сохранения данных игрока {$playerName}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Удалить данные игрока
     * @param string $playerName Имя игрока
     * @return bool Успешность удаления
     */
    public function deletePlayer(string $playerName): bool {
        $filePath = $this->getPlayerFilePath($playerName);
        
        if (!file_exists($filePath)) {
            return false;
        }
        
        try {
            unlink($filePath);
            
            // Очистка кэша
            $cacheKey = strtolower($playerName);
            unset($this->playerCache[$cacheKey]);
            
            return true;
        } catch (\Exception $e) {
            $this->plugin->getLogger()->warning("Ошибка удаления данных игрока {$playerName}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Получить путь к файлу данных игрока
     */
    private function getPlayerFilePath(string $playerName): string {
        // Использование lowercase имени для совместимости
        $safeName = strtolower(trim($playerName));
        $safeName = preg_replace("/[^a-z0-9_-]/", "", $safeName);
        
        return $this->playersFolder . $safeName . ".json";
    }
    
    /**
     * Очистить кэш игрока
     */
    public function clearCache(string $playerName): void {
        $cacheKey = strtolower($playerName);
        unset($this->playerCache[$cacheKey]);
    }
    
    /**
     * Очистить весь кэш
     */
    public function clearAllCache(): void {
        $this->playerCache = [];
    }
    
    /**
     * Сохранить всех игроков из кэша
     */
    public function saveAllFromCache(): void {
        foreach ($this->playerCache as $playerName => $data) {
            $this->savePlayer($playerName, $data);
        }
    }
    
    /**
     * Получить список всех зарегистрированных игроков
     * @return array<string> Список имен игроков
     */
    public function getAllPlayers(): array {
        $players = [];
        
        if (!is_dir($this->playersFolder)) {
            return $players;
        }
        
        $files = scandir($this->playersFolder);
        
        foreach ($files as $file) {
            if ($file === "." || $file === "..") {
                continue;
            }
            
            $extension = pathinfo($file, PATHINFO_EXTENSION);
            if ($extension === "json") {
                $playerName = pathinfo($file, PATHINFO_FILENAME);
                $players[] = $playerName;
            }
        }
        
        return $players;
    }
    
    /**
     * Получить количество зарегистрированных игроков
     */
    public function getRegisteredCount(): int {
        return count($this->getAllPlayers());
    }
}
