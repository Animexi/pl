<?php

namespace LiteAuth\storage;

use LiteAuth\LiteAuthPlugin;
use LiteAuth\model\PlayerAuthData;

/**
 * Менеджер хранилища данных игроков
 * Использует YAML файлы для совместимости со старыми версиями
 */
class StorageManager {
    
    /** @var LiteAuthPlugin */
    private $plugin;
    
    /** @var array Кэш загруженных данных */
    private $cache = array();
    
    public function __construct(LiteAuthPlugin $plugin) {
        $this->plugin = $plugin;
        
        // Создание директории для данных
        @mkdir($plugin->getDataFolder() . "players/");
    }
    
    /**
     * Получить путь к файлу игрока
     * @param string $username
     * @return string
     */
    private function getPlayerFile($username) {
        // Нормализация имени: только безопасные символы
        $cleanName = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $username));
        return $this->plugin->getDataFolder() . "players/" . $cleanName . ".yml";
    }
    
    /**
     * Загрузить данные игрока
     * @param string $username
     * @return PlayerAuthData|null
     */
    public function load($username) {
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
            
            // Парсинг YAML вручную (простой формат)
            $data = $this->parseYaml($content);
            
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
     * @param PlayerAuthData $data
     * @return bool
     */
    public function save(PlayerAuthData $data) {
        try {
            $file = $this->getPlayerFile($data->getUsername());
            $yamlData = $this->arrayToYaml($data->toArray());
            
            $result = file_put_contents($file, $yamlData);
            
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
     * @param string $username
     * @return bool
     */
    public function exists($username) {
        $cleanName = strtolower($username);
        
        if (isset($this->cache[$cleanName])) {
            return true;
        }
        
        return file_exists($this->getPlayerFile($username));
    }
    
    /**
     * Удалить аккаунт игрока
     * @param string $username
     * @return bool
     */
    public function delete($username) {
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
     * @param string $username
     */
    public function clearCache($username) {
        $cleanName = strtolower($username);
        unset($this->cache[$cleanName]);
    }
    
    /**
     * Очистить весь кэш
     */
    public function clearAllCache() {
        $this->cache = array();
    }
    
    /**
     * Простой парсер YAML для совместимости
     * @param string $yaml
     * @return array|null
     */
    private function parseYaml($yaml) {
        $result = array();
        $lines = explode("\n", $yaml);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Пропуск пустых строк и комментариев
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            
            // Разбор ключ: значение
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Удаление кавычек
                $value = trim($value, '"\'');
                
                // Преобразование типов
                if (is_numeric($value)) {
                    $value = $value + 0;
                } elseif ($value === 'true') {
                    $value = true;
                } elseif ($value === 'false') {
                    $value = false;
                }
                
                $result[$key] = $value;
            }
        }
        
        return count($result) > 0 ? $result : null;
    }
    
    /**
     * Преобразование массива в простой YAML формат
     * @param array $data
     * @return string
     */
    private function arrayToYaml($data) {
        $yaml = "# LiteAuth Player Data\n";
        
        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (is_string($value)) {
                // Экранирование специальных символов
                $value = '"' . str_replace('"', '\\"', $value) . '"';
            }
            
            $yaml .= "{$key}: {$value}\n";
        }
        
        return $yaml;
    }
}
