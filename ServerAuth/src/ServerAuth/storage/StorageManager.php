<?php

declare(strict_types=1);

namespace ServerAuth\storage;

use ServerAuth\ServerAuthPlugin;
use ServerAuth\model\PlayerAuthData;

/**
 * Менеджер хранилища данных игроков
 */
class StorageManager {
    
    private ServerAuthPlugin $plugin;
    
    /** @var array<string, PlayerAuthData> Кэш загруженных данных */
    private array $cache = [];
    
    public function __construct(ServerAuthPlugin $plugin) {
        $this->plugin = $plugin;
        
        // Создание директории для данных
        @mkdir($plugin->getDataFolder() . "players/");
    }
    
    /**
     * Получить путь к файлу игрока
     */
    private function getPlayerFile(string $username): string {
        $cleanName = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $username));
        return $this->plugin->getDataFolder() . "players/" . $cleanName . ".dat";
    }
    
    /**
     * Загрузить данные игрока
     */
    public function load(string $username): ?PlayerAuthData {
        $cleanName = strtolower($username);
        
        // Проверка кэша
        if (isset($this->cache[$cleanName])) {
            return $this->cache[$cleanName];
        }
        
        $file = $this->getPlayerFile($username);
        
        if (!file_exists($file)) {
            return null;
        }
        
        try {
            $content = file_get_contents($file);
            if ($content === false) {
                $this->plugin->getLogger()->warning("Не удалось прочитать файл игрока: {$username}");
                return null;
            }
            
            $data = json_decode($content, true);
            
            if ($data === null || !is_array($data)) {
                $this->plugin->getLogger()->warning("Повреждённый файл игрока: {$username}");
                return null;
            }
            
            $authData = PlayerAuthData::fromArray($data);
            
            if ($authData !== null) {
                $this->cache[$cleanName] = $authData;
            }
            
            return $authData;
            
        } catch (\Exception $e) {
            $this->plugin->getLogger()->error("Ошибка загрузки данных игрока {$username}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Сохранить данные игрока
     */
    public function save(PlayerAuthData $data): bool {
        try {
            $file = $this->getPlayerFile($data->getUsername());
            $jsonData = json_encode($data->toArray(), JSON_PRETTY_PRINT);
            
            if ($jsonData === false) {
                $this->plugin->getLogger()->error("Ошибка сериализации данных игрока: " . $data->getUsername());
                return false;
            }
            
            $result = file_put_contents($file, $jsonData);
            
            if ($result !== false) {
                // Обновление кэша
                $cleanName = strtolower($data->getUsername());
                $this->cache[$cleanName] = $data;
                return true;
            }
            
            $this->plugin->getLogger()->error("Не удалось сохранить данные игрока: " . $data->getUsername());
            return false;
            
        } catch (\Exception $e) {
            $this->plugin->getLogger()->error("Ошибка сохранения данных игрока {$data->getUsername()}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Проверить существование аккаунта
     */
    public function exists(string $username): bool {
        $cleanName = strtolower($username);
        
        if (isset($this->cache[$cleanName])) {
            return true;
        }
        
        return file_exists($this->getPlayerFile($username));
    }
    
    /**
     * Удалить аккаунт игрока
     */
    public function delete(string $username): bool {
        try {
            $file = $this->getPlayerFile($username);
            
            if (file_exists($file)) {
                $result = unlink($file);
                
                if ($result) {
                    // Удаление из кэша
                    $cleanName = strtolower($username);
                    unset($this->cache[$cleanName]);
                    return true;
                }
            }
            
            return false;
            
        } catch (\Exception $e) {
            $this->plugin->getLogger()->error("Ошибка удаления аккаунта {$username}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Очистить кэш игрока
     */
    public function clearCache(string $username): void {
        $cleanName = strtolower($username);
        unset($this->cache[$cleanName]);
    }
    
    /**
     * Очистить весь кэш
     */
    public function clearAllCache(): void {
        $this->cache = [];
    }
    
    /**
     * Получить все аккаунты
     * 
     * @return array<string> Список имён игроков
     */
    public function getAllAccounts(): array {
        $accounts = [];
        $dir = $this->plugin->getDataFolder() . "players/";
        
        if (is_dir($dir)) {
            $files = scandir($dir);
            
            if ($files !== false) {
                foreach ($files as $file) {
                    if (str_ends_with($file, '.dat')) {
                        $name = substr($file, 0, -4);
                        $accounts[] = $name;
                    }
                }
            }
        }
        
        return $accounts;
    }
}
