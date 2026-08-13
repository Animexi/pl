<?php

declare(strict_types=1);

namespace ServerAuth\util;

use pocketmine\utils\Config;
use ServerAuth\ServerAuthPlugin;

/**
 * Менеджер конфигурации плагина
 */
class ConfigManager {
    
    private ServerAuthPlugin $plugin;
    private Config $config;
    
    public function __construct(ServerAuthPlugin $plugin) {
        $this->plugin = $plugin;
        
        // Создание config.yml если не существует
        $plugin->saveResource("config.yml", false);
        $this->config = new Config($plugin->getDataFolder() . "config.yml", Config::YAML);
    }
    
    /**
     * Перезагрузка конфигурации
     */
    public function reload(): void {
        $this->config->reload();
    }
    
    /**
     * Получить префикс сообщений
     */
    public function getPrefix(): string {
        return (string) $this->config->get("prefix", "§8[§6Server§8]");
    }
    
    /**
     * Максимальное количество попыток входа
     */
    public function getMaxLoginAttempts(): int {
        return (int) $this->config->get("security.max_login_attempts", 5);
    }
    
    /**
     * Cooldown между попытками входа (секунды)
     */
    public function getLoginCooldown(): int {
        return (int) $this->config->get("security.login_cooldown", 3);
    }
    
    /**
     * Время блокировки после превышения попыток (секунды)
     */
    public function getLockTime(): int {
        return (int) $this->config->get("security.lock_time", 300);
    }
    
    /**
     * Минимальная длина пароля
     */
    public function getMinPasswordLength(): int {
        return (int) $this->config->get("security.min_password_length", 4);
    }
    
    /**
     * Максимальная длина пароля
     */
    public function getMaxPasswordLength(): int {
        return (int) $this->config->get("security.max_password_length", 20);
    }
    
    /**
     * Включена ли защита от движения
     */
    public function isProtectionEnabled(string $type): bool {
        return (bool) $this->config->get("protection." . $type, true);
    }
    
    /**
     * Разрешённые команды до авторизации
     */
    public function getAllowedCommands(): array {
        return (array) $this->config->get("allowed_commands", [
            "login",
            "register",
            "changepassword",
            "auth"
        ]);
    }
    
    /**
     * Проверка, разрешена ли команда
     */
    public function isCommandAllowed(string $command): bool {
        $allowed = $this->getAllowedCommands();
        $command = strtolower($command);
        
        foreach ($allowed as $allowedCmd) {
            if (strtolower($allowedCmd) === $command) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Время сессии (авто-logout при бездействии)
     */
    public function getSessionTimeout(): int {
        return (int) $this->config->get("session_timeout", 0);
    }
    
    /**
     * Включено ли логирование событий
     */
    public function isLoggingEnabled(): bool {
        return (bool) $this->config->get("logging.enabled", true);
    }
    
    /**
     * Путь к хранилищу данных
     */
    public function getStoragePath(): string {
        return $this->plugin->getDataFolder() . "players/";
    }
    
    /**
     * Тип хранилища (file, yaml, json)
     */
    public function getStorageType(): string {
        return (string) $this->config->get("storage.type", "file");
    }
    
    /**
     * Получить все настройки безопасности
     */
    public function getSecuritySettings(): array {
        return (array) $this->config->get("security", []);
    }
    
    /**
     * Получить все настройки защиты
     */
    public function getProtectionSettings(): array {
        return (array) $this->config->get("protection", []);
    }
}
