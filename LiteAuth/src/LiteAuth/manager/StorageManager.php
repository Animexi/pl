<?php

declare(strict_types=1);

namespace LiteAuth\manager;

use LiteAuth\LiteAuthPlugin;
use pocketmine\Player;
use pocketmine\utils\Config;

class StorageManager {

    private $plugin;
    private $playerData = [];

    public function __construct(LiteAuthPlugin $plugin) {
        $this->plugin = $plugin;
    }

    public function normalizeName(string $name): string {
        return strtolower($name);
    }

    public function getPlayerFile(string $name): string {
        return $this->plugin->getDataFolder() . "players/" . $this->normalizeName($name) . ".yml";
    }

    public function playerExists(string $name): bool {
        return file_exists($this->getPlayerFile($name));
    }

    public function loadPlayer(string $name): ?Config {
        $file = $this->getPlayerFile($name);
        if (!file_exists($file)) {
            return null;
        }
        
        try {
            return new Config($file, Config::YAML);
        } catch (\Exception $e) {
            $this->plugin->getLogger()->error("Failed to load player data: " . $name);
            return null;
        }
    }

    public function savePlayer(string $name, array $data): bool {
        try {
            $config = new Config($this->getPlayerFile($name), Config::YAML);
            foreach ($data as $key => $value) {
                $config->set($key, $value);
            }
            $config->save();
            return true;
        } catch (\Exception $e) {
            $this->plugin->getLogger()->error("Failed to save player data: " . $name);
            return false;
        }
    }

    public function deletePlayer(string $name): bool {
        $file = $this->getPlayerFile($name);
        if (file_exists($file)) {
            return unlink($file);
        }
        return false;
    }

    public function getPlayerData(string $name): ?array {
        $config = $this->loadPlayer($name);
        if ($config === null) {
            return null;
        }
        return $config->getAll();
    }

    public function updatePlayerField(string $name, string $field, $value): bool {
        $data = $this->getPlayerData($name);
        if ($data === null) {
            $data = [];
        }
        $data[$field] = $value;
        return $this->savePlayer($name, $data);
    }

    public function getRegistrationsByIp(string $ip): int {
        $count = 0;
        $dir = $this->plugin->getDataFolder() . "players/";
        
        if (!is_dir($dir)) {
            return 0;
        }

        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === "." || $file === "..") {
                continue;
            }
            
            $filePath = $dir . $file;
            if (is_file($filePath) && pathinfo($file, PATHINFO_EXTENSION) === "yml") {
                try {
                    $config = new Config($filePath, Config::YAML);
                    if ($config->get("last-ip") === $ip) {
                        $count++;
                    }
                } catch (\Exception $e) {
                    // Ignore corrupted files
                }
            }
        }
        
        return $count;
    }
}
