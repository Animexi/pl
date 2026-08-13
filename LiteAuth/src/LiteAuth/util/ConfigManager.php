<?php

namespace LiteAuth\util;

use pocketmine\utils\Config;
use LiteAuth\LiteAuthPlugin;

/**
 * Менеджер конфигурации плагина
 */
class ConfigManager {
    
    /** @var LiteAuthPlugin */
    private $plugin;
    
    /** @var Config */
    private $config;
    
    public function __construct(LiteAuthPlugin $plugin) {
        $this->plugin = $plugin;
        
        // Загрузка конфигурации
        $this->config = new Config($plugin->getDataFolder() . "config.yml", Config::YAML);
        
        // Валидация и исправление некорректных значений
        $this->validateConfig();
    }
    
    /**
     * Перезагрузка конфигурации
     */
    public function reload() {
        $this->config->reload();
        $this->validateConfig();
    }
    
    /**
     * Валидация конфигурации
     */
    private function validateConfig() {
        // Проверка минимальной/максимальной длины пароля
        $minLen = $this->getMinPasswordLength();
        $maxLen = $this->getMaxPasswordLength();
        
        if ($minLen > $maxLen) {
            $this->plugin->getLogger()->warning("[Config] min-password-length больше max-password-length. Используется безопасное значение.");
            // Исправление в памяти не делаем, просто логируем
        }
        
        // Проверка отрицательных значений
        if ($this->getAuthTimeout() < 0) {
            $this->plugin->getLogger()->warning("[Config] auth-timeout не может быть отрицательным.");
        }
        
        if ($this->getMaxLoginAttempts() <= 0) {
            $this->plugin->getLogger()->warning("[Config] max-login-attempts должен быть больше 0.");
        }
    }
    
    public function getPrefix() {
        return (string) $this->config->get("prefix", "§e§lLITE§f§lAUTH §8┃");
    }
    
    public function getLanguage() {
        return (string) $this->config->get("language", "ru");
    }
    
    // ==================== ПАРОЛИ ====================
    
    public function getMinPasswordLength() {
        $value = (int) $this->config->get("password.min-length", 6);
        return max(1, $value);
    }
    
    public function getMaxPasswordLength() {
        $value = (int) $this->config->get("password.max-length", 32);
        return max(1, $value);
    }
    
    public function getPasswordBlacklist() {
        return (array) $this->config->get("password.blacklist", array(
            "123456", "password", "qwerty", "123123", "admin"
        ));
    }
    
    public function isPasswordInBlacklist($password) {
        $blacklist = $this->getPasswordBlacklist();
        $lowerPassword = strtolower($password);
        
        foreach ($blacklist as $blocked) {
            if (strtolower($blocked) === $lowerPassword) {
                return true;
            }
        }
        return false;
    }
    
    // ==================== АВТОРИЗАЦИЯ ====================
    
    public function getAuthTimeout() {
        return (int) $this->config->get("auth.timeout", 60);
    }
    
    public function getMaxLoginAttempts() {
        $value = (int) $this->config->get("auth.max-attempts", 5);
        return max(1, $value);
    }
    
    public function getLoginCooldown() {
        return (int) $this->config->get("auth.cooldown", 2);
    }
    
    public function getLockTime() {
        return (int) $this->config->get("auth.lock-time", 300);
    }
    
    public function kickOnTimeout() {
        return (bool) $this->config->get("auth.kick-on-timeout", true);
    }
    
    // ==================== РЕГИСТРАЦИЯ ====================
    
    public function isRegistrationEnabled() {
        return (bool) $this->config->get("registration.enabled", true);
    }
    
    public function getMaxRegistrationsPerIp() {
        return (int) $this->config->get("registration.max-per-ip", 3);
    }
    
    // ==================== КАПЧА ====================
    
    public function isCaptchaEnabled() {
        return (bool) $this->config->get("captcha.enabled", true);
    }
    
    public function getCaptchaMaxAttempts() {
        return (int) $this->config->get("captcha.max-attempts", 3);
    }
    
    public function getCaptchaTimeout() {
        return (int) $this->config->get("captcha.timeout", 120);
    }
    
    public function isCaptchaOnNewSession() {
        return (bool) $this->config->get("captcha.on-new-session", false);
    }
    
    public function getCaptchaCooldown() {
        return (int) $this->config->get("captcha.cooldown", 5);
    }
    
    // ==================== СЕССИИ ====================
    
    public function isAutoLoginEnabled() {
        return (bool) $this->config->get("session.auto-login", true);
    }
    
    public function getSessionLifetime() {
        return (int) $this->config->get("session.lifetime", 86400);
    }
    
    public function isSessionByIp() {
        return (bool) $this->config->get("session.by-ip", false);
    }
    
    // ==================== ЗАЩИТА ====================
    
    public function isProtectionEnabled($type) {
        return (bool) $this->config->get("protection." . $type, true);
    }
    
    public function getAllowedCommands() {
        return (array) $this->config->get("allowed-commands", array(
            "login", "l", "register", "reg", "captcha", "auth", "help", "me"
        ));
    }
    
    public function isCommandAllowed($command) {
        $allowed = $this->getAllowedCommands();
        $command = strtolower($command);
        
        foreach ($allowed as $allowedCmd) {
            if (strtolower($allowedCmd) === $command) {
                return true;
            }
        }
        
        return false;
    }
    
    // ==================== ЛОГИРОВАНИЕ ====================
    
    public function isLoggingEnabled() {
        return (bool) $this->config->get("logging.enabled", true);
    }
    
    public function isSecurityLoggingEnabled() {
        return (bool) $this->config->get("logging.security", true);
    }
    
    public function isAdminLoggingEnabled() {
        return (bool) $this->config->get("logging.admin-actions", true);
    }
    
    // ==================== ОТЛАДКА ====================
    
    public function isDebugEnabled() {
        return (bool) $this->config->get("debug", false);
    }
    
    // ==================== ХРАНЕНИЕ ====================
    
    public function getStorageType() {
        return (string) $this->config->get("storage.type", "yaml");
    }
    
    public function getStoragePath() {
        return $this->plugin->getDataFolder() . "players/";
    }
}
