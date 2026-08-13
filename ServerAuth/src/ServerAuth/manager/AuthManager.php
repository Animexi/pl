<?php

declare(strict_types=1);

namespace ServerAuth\manager;

use pocketmine\Player;
use ServerAuth\ServerAuthPlugin;
use ServerAuth\model\AuthState;
use ServerAuth\model\PlayerAuthData;
use ServerAuth\storage\StorageManager;
use ServerAuth\util\ConfigManager;
use ServerAuth\util\MessageManager;
use ServerAuth\util\PasswordManager;

/**
 * Основной менеджер авторизации
 */
class AuthManager {
    
    private ServerAuthPlugin $plugin;
    private StorageManager $storageManager;
    private ConfigManager $configManager;
    private MessageManager $messageManager;
    private PasswordManager $passwordManager;
    
    /** @var array<string, int> Состояния игроков по UUID */
    private array $playerStates = [];
    
    /** @var array<string, int> Время последней попытки входа */
    private array $lastAttemptTime = [];
    
    public function __construct(
        ServerAuthPlugin $plugin,
        StorageManager $storageManager,
        ConfigManager $configManager,
        MessageManager $messageManager,
        PasswordManager $passwordManager
    ) {
        $this->plugin = $plugin;
        $this->storageManager = $storageManager;
        $this->configManager = $configManager;
        $this->messageManager = $messageManager;
        $this->passwordManager = $passwordManager;
    }
    
    /**
     * Получить состояние игрока
     */
    public function getState(Player $player): int {
        $uuid = $player->getUniqueId()->toString();
        return $this->playerStates[$uuid] ?? AuthState::UNREGISTERED;
    }
    
    /**
     * Установить состояние игрока
     */
    public function setState(Player $player, int $state): void {
        $uuid = $player->getUniqueId()->toString();
        $this->playerStates[$uuid] = $state;
    }
    
    /**
     * Проверить, зарегистрирован ли игрок
     */
    public function isRegistered(Player $player): bool {
        return $this->getState($player) !== AuthState::UNREGISTERED;
    }
    
    /**
     * Проверить, авторизован ли игрок
     */
    public function isLoggedIn(Player $player): bool {
        return $this->getState($player) === AuthState::LOGGED_IN;
    }
    
    /**
     * Загрузить данные игрока при подключении
     */
    public function onPlayerJoin(Player $player): void {
        $username = $player->getName();
        $uuid = $player->getUniqueId()->toString();
        
        // Загрузка данных из хранилища
        $authData = $this->storageManager->load($username);
        
        if ($authData !== null) {
            // Игрок зарегистрирован
            $this->setState($player, AuthState::REGISTERED_NOT_LOGGED);
            
            // Проверка блокировки
            if ($authData->isLocked()) {
                $lockTimeRemaining = ceil(($authData->getLockedUntil() - time()) / 60);
                $this->messageManager->send($player, "login.locked", ["MINUTES" => $lockTimeRemaining]);
                $this->logSecurity("Игрок {$username} заблокирован на {$lockTimeRemaining} мин.");
            } else {
                // Показать приветственное сообщение для зарегистрированного
                $this->messageManager->send($player, "welcome.registered_message");
            }
            
            $this->logInfo("Игрок {$username} подключился (зарегистрирован, ожидает входа)");
            
        } else {
            // Новый игрок
            $this->setState($player, AuthState::UNREGISTERED);
            $this->messageManager->send($player, "welcome.new_message");
            $this->logInfo("Новый игрок подключился: {$username}");
        }
    }
    
    /**
     * Регистрация игрока
     */
    public function register(Player $player, string $password, string $confirmPassword): bool {
        $username = $player->getName();
        $uuid = $player->getUniqueId()->toString();
        
        // Проверка состояния
        if ($this->getState($player) !== AuthState::UNREGISTERED) {
            $this->messageManager->send($player, "register.already_registered");
            return false;
        }
        
        // Проверка существования аккаунта
        if ($this->storageManager->exists($username)) {
            $this->messageManager->send($player, "register.already_registered");
            return false;
        }
        
        // Проверка паролей
        if ($password !== $confirmPassword) {
            $this->messageManager->send($player, "register.passwords_not_match");
            return false;
        }
        
        // Проверка длины пароля
        $minLength = $this->configManager->getMinPasswordLength();
        $maxLength = $this->configManager->getMaxPasswordLength();
        
        if (empty($password)) {
            $this->messageManager->send($player, "register.empty_password");
            return false;
        }
        
        if (!$this->passwordManager->isPasswordValid($password, $minLength, $maxLength)) {
            if (strlen($password) < $minLength) {
                $this->messageManager->send($player, "register.password_too_short", ["MIN_LENGTH" => $minLength]);
            } else {
                $this->messageManager->send($player, "register.password_too_long", ["MAX_LENGTH" => $maxLength]);
            }
            return false;
        }
        
        // Создание аккаунта
        try {
            $salt = $this->passwordManager->generateSalt();
            $passwordHash = $this->passwordManager->hashPassword($password, $salt);
            
            $authData = new PlayerAuthData(
                $username,
                $uuid,
                $passwordHash,
                $salt,
                time(),
                0,
                0,
                0
            );
            
            if ($this->storageManager->save($authData)) {
                $this->setState($player, AuthState::REGISTERED_NOT_LOGGED);
                $this->messageManager->send($player, "register.success");
                $this->logInfo("Игрок {$username} успешно зарегистрирован");
                return true;
            }
            
            $this->messageManager->send($player, "error.internal");
            $this->logError("Не удалось сохранить данные регистрации игрока {$username}");
            return false;
            
        } catch (\Exception $e) {
            $this->messageManager->send($player, "error.internal");
            $this->logError("Ошибка при регистрации игрока {$username}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Авторизация игрока
     */
    public function login(Player $player, string $password): bool {
        $username = $player->getName();
        $uuid = $player->getUniqueId()->toString();
        $currentTime = time();
        
        // Проверка состояния
        if ($this->isLoggedIn($player)) {
            $this->messageManager->send($player, "login.already_logged_in");
            return false;
        }
        
        if (!$this->isRegistered($player)) {
            $this->messageManager->send($player, "login.not_registered");
            return false;
        }
        
        // Загрузка данных
        $authData = $this->storageManager->load($username);
        
        if ($authData === null) {
            $this->messageManager->send($player, "login.not_registered");
            return false;
        }
        
        // Проверка блокировки
        if ($authData->isLocked()) {
            $lockTimeRemaining = ceil(($authData->getLockedUntil() - $currentTime) / 60);
            $this->messageManager->send($player, "login.locked", ["MINUTES" => $lockTimeRemaining]);
            $this->logSecurity("Попытка входа заблокированного игрока {$username}");
            return false;
        }
        
        // Проверка cooldown
        if (isset($this->lastAttemptTime[$uuid])) {
            $cooldown = $this->configManager->getLoginCooldown();
            $timeSinceLastAttempt = $currentTime - $this->lastAttemptTime[$uuid];
            
            if ($timeSinceLastAttempt < $cooldown) {
                $waitTime = $cooldown - $timeSinceLastAttempt;
                $this->messageManager->send($player, "login.cooldown", ["SECONDS" => $waitTime]);
                return false;
            }
        }
        
        $this->lastAttemptTime[$uuid] = $currentTime;
        
        // Проверка пароля
        if (!$this->passwordManager->verifyPassword($password, $authData->getPasswordHash(), $authData->getSalt())) {
            $authData->incrementFailedAttempts();
            
            $maxAttempts = $this->configManager->getMaxLoginAttempts();
            
            if ($authData->getFailedAttempts() >= $maxAttempts) {
                // Блокировка
                $lockTime = $this->configManager->getLockTime();
                $authData->setLockedUntil($currentTime + $lockTime);
                $this->storageManager->save($authData);
                
                $lockMinutes = ceil($lockTime / 60);
                $this->messageManager->send($player, "login.locked", ["MINUTES" => $lockMinutes]);
                $this->logSecurity("Игрок {$username} заблокирован после {$maxAttempts} неудачных попыток");
            } else {
                $this->storageManager->save($authData);
                $this->messageManager->send($player, "login.wrong_password");
                $this->logSecurity("Неверный пароль для игрока {$username} (попытка {$authData->getFailedAttempts()}/{$maxAttempts})");
            }
            
            return false;
        }
        
        // Успешная авторизация
        $authData->resetFailedAttempts();
        $authData->setLastLogin($currentTime);
        $this->storageManager->save($authData);
        
        $this->setState($player, AuthState::LOGGED_IN);
        $this->messageManager->send($player, "login.success", ["PLAYER" => $username]);
        $this->messageManager->send($player, "authorized", ["PLAYER" => $username]);
        
        $this->logInfo("Игрок {$username} успешно авторизован");
        
        return true;
    }
    
    /**
     * Смена пароля
     */
    public function changePassword(Player $player, string $oldPassword, string $newPassword): bool {
        $username = $player->getName();
        
        if (!$this->isLoggedIn($player)) {
            $this->messageManager->send($player, "login.not_registered");
            return false;
        }
        
        $authData = $this->storageManager->load($username);
        
        if ($authData === null) {
            $this->messageManager->send($player, "error.internal");
            return false;
        }
        
        // Проверка старого пароля
        if (!$this->passwordManager->verifyPassword($oldPassword, $authData->getPasswordHash(), $authData->getSalt())) {
            $this->messageManager->send($player, "changepassword.wrong_old");
            return false;
        }
        
        // Проверка нового пароля
        $minLength = $this->configManager->getMinPasswordLength();
        $maxLength = $this->configManager->getMaxPasswordLength();
        
        if (empty($newPassword)) {
            $this->messageManager->send($player, "register.empty_password");
            return false;
        }
        
        if (!$this->passwordManager->isPasswordValid($newPassword, $minLength, $maxLength)) {
            if (strlen($newPassword) < $minLength) {
                $this->messageManager->send($player, "register.password_too_short", ["MIN_LENGTH" => $minLength]);
            } else {
                $this->messageManager->send($player, "register.password_too_long", ["MAX_LENGTH" => $maxLength]);
            }
            return false;
        }
        
        // Проверка совпадения со старым
        if ($oldPassword === $newPassword) {
            $this->messageManager->send($player, "changepassword.same_as_old");
            return false;
        }
        
        // Смена пароля
        try {
            $salt = $this->passwordManager->generateSalt();
            $passwordHash = $this->passwordManager->hashPassword($newPassword, $salt);
            
            $authData->setPassword($passwordHash, $salt);
            
            if ($this->storageManager->save($authData)) {
                $this->messageManager->send($player, "changepassword.success");
                $this->logInfo("Игрок {$username} сменил пароль");
                return true;
            }
            
            $this->messageManager->send($player, "error.internal");
            return false;
            
        } catch (\Exception $e) {
            $this->messageManager->send($player, "error.internal");
            $this->logError("Ошибка при смене пароля игрока {$username}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Выход игрока (logout)
     */
    public function onPlayerQuit(Player $player): void {
        $uuid = $player->getUniqueId()->toString();
        
        unset($this->playerStates[$uuid]);
        unset($this->lastAttemptTime[$uuid]);
        
        $this->logInfo("Игрок " . $player->getName() . " вышел (автоматический logout)");
    }
    
    /**
     * Сохранить всех игроков
     */
    public function saveAllPlayers(): void {
        foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
            $username = $player->getName();
            $authData = $this->storageManager->load($username);
            
            if ($authData !== null && $this->isLoggedIn($player)) {
                $authData->setLastLogin(time());
                $this->storageManager->save($authData);
            }
        }
    }
    
    /**
     * Административное удаление аккаунта
     */
    public function unregisterAccount(string $username): bool {
        if ($this->storageManager->delete($username)) {
            $this->storageManager->clearCache($username);
            $this->logInfo("Аккаунт игрока {$username} удалён администратором");
            return true;
        }
        return false;
    }
    
    /**
     * Получить информацию об аккаунте
     */
    public function getAccountInfo(string $username): ?array {
        $authData = $this->storageManager->load($username);
        
        if ($authData === null) {
            return null;
        }
        
        return [
            "username" => $authData->getUsername(),
            "uuid" => $authData->getUuid(),
            "registered_at" => date("Y-m-d H:i:s", $authData->getCreatedAt()),
            "last_login" => $authData->getLastLogin() > 0 
                ? date("Y-m-d H:i:s", $authData->getLastLogin()) 
                : "Никогда",
            "failed_attempts" => $authData->getFailedAttempts(),
            "is_locked" => $authData->isLocked()
        ];
    }
    
    /**
     * Логирование информационных событий
     */
    private function logInfo(string $message): void {
        if ($this->configManager->isLoggingEnabled()) {
            $this->plugin->getLogger()->info($message);
        }
    }
    
    /**
     * Логирование событий безопасности
     */
    private function logSecurity(string $message): void {
        if ($this->configManager->isLoggingEnabled()) {
            $this->plugin->getLogger()->warning("[SECURITY] " . $message);
        }
    }
    
    /**
     * Логирование ошибок
     */
    private function logError(string $message): void {
        $this->plugin->getLogger()->error($message);
    }
}
