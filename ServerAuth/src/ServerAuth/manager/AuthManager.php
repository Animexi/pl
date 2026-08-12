<?php

declare(strict_types=1);

namespace ServerAuth\manager;

use pocketmine\Player;
use pocketmine\command\CommandSender;
use ServerAuth\ServerAuthPlugin;
use ServerAuth\storage\StorageManager;
use ServerAuth\manager\MessageManager;

class AuthManager {
    
    public const STATE_UNREGISTERED = 0;
    public const STATE_REGISTERED_NOT_LOGGED = 1;
    public const STATE_LOGGED_IN = 2;
    
    private ServerAuthPlugin $plugin;
    private StorageManager $storageManager;
    private MessageManager $messageManager;
    
    /** @var array<string, int> */
    private array $playerStates = [];
    
    /** @var array<string, int> */
    private array $loginAttempts = [];
    
    /** @var array<string, int> */
    private array $lockoutTimes = [];
    
    /** @var array<string, int> */
    private array $lastLoginAttempt = [];
    
    /** @var array<string, string> */
    private array $pendingRegistrations = [];
    
    public function __construct(
        ServerAuthPlugin $plugin,
        StorageManager $storageManager,
        MessageManager $messageManager
    ) {
        $this->plugin = $plugin;
        $this->storageManager = $storageManager;
        $this->messageManager = $messageManager;
    }
    
    public function getPlayerState(string $playerName): int {
        return $this->playerStates[strtolower($playerName)] ?? self::STATE_UNREGISTERED;
    }
    
    public function setPlayerState(string $playerName, int $state): void {
        $this->playerStates[strtolower($playerName)] = $state;
    }
    
    public function isRegistered(string $playerName): bool {
        return $this->storageManager->playerExists($playerName);
    }
    
    public function isLoggedIn(string $playerName): bool {
        return $this->getPlayerState($playerName) === self::STATE_LOGGED_IN;
    }
    
    public function handleRegister(CommandSender $sender, array $args): bool {
        if (!$sender instanceof Player) {
            $this->messageManager->send($sender, "general.player-only");
            return true;
        }
        
        // Если уже зарегистрирован и вошел - ошибка
        if ($this->isLoggedIn($sender->getName())) {
            $this->messageManager->send($sender, "register.already-registered");
            return true;
        }
        
        // Если уже зарегистрирован но не вошел - тоже ошибка
        if ($this->isRegistered($sender->getName()) && !$this->isLoggedIn($sender->getName())) {
            $this->messageManager->send($sender, "register.already-registered");
            return true;
        }
        
        if (count($args) < 2) {
            $this->messageManager->send($sender, "general.not-enough-args");
            return true;
        }
        
        $password = $args[0];
        $confirmPassword = $args[1];
        
        // Проверка длины пароля
        $minLength = $this->plugin->getConfig()->getNested("security.min-password-length", 4);
        $maxLength = $this->plugin->getConfig()->getNested("security.max-password-length", 20);
        
        if (strlen($password) < $minLength) {
            $this->messageManager->send($sender, "register.password-too-short", ["{MIN}" => (string)$minLength]);
            return true;
        }
        
        if (strlen($password) > $maxLength) {
            $this->messageManager->send($sender, "register.password-too-long", ["{MAX}" => (string)$maxLength]);
            return true;
        }
        
        if ($password !== $confirmPassword) {
            $this->messageManager->send($sender, "register.passwords-mismatch");
            return true;
        }
        
        // Хеширование пароля
        $hashedPassword = $this->hashPassword($password, $sender->getName());
        
        // Сохранение данных игрока
        $playerData = [
            "name" => $sender->getName(),
            "uuid" => $sender->getUniqueId()->toString(),
            "password" => $hashedPassword,
            "ip" => $sender->getAddress(),
            "first_login" => time(),
            "last_login" => time()
        ];
        
        if ($this->storageManager->savePlayer($sender->getName(), $playerData)) {
            $this->setPlayerState($sender->getName(), self::STATE_LOGGED_IN);
            
            // Логирование
            if ($this->plugin->getConfig()->getNested("logging.log-registration", true)) {
                $this->plugin->getLogger()->info("§6Регистрация: §f" . $sender->getName() . " успешно зарегистрирован.");
            }
            
            $this->messageManager->send($sender, "register.success");
            
            // Авто-вход после регистрации
            if ($this->plugin->getConfig()->getNested("security.auto-login-after-register", true)) {
                $this->onSuccessfulLogin($sender);
            }
            
            return true;
        } else {
            $this->messageManager->send($sender, "register.error");
            return true;
        }
    }
    
    public function handleLogin(CommandSender $sender, array $args): bool {
        if (!$sender instanceof Player) {
            $this->messageManager->send($sender, "general.player-only");
            return true;
        }
        
        // Если уже вошел
        if ($this->isLoggedIn($sender->getName())) {
            $this->messageManager->send($sender, "login.already-logged-in");
            return true;
        }
        
        // Если не зарегистрирован
        if (!$this->isRegistered($sender->getName())) {
            $this->messageManager->send($sender, "login.not-registered");
            return true;
        }
        
        // Проверка блокировки
        if ($this->isLockedOut($sender->getName())) {
            $remainingTime = $this->getLockoutRemainingTime($sender->getName());
            $this->messageManager->send($sender, "login.too-many-attempts", ["{TIME}" => (string)$remainingTime]);
            return true;
        }
        
        // Проверка cooldown
        if ($this->isOnCooldown($sender->getName())) {
            $cooldownTime = $this->getCooldownRemainingTime($sender->getName());
            $this->messageManager->send($sender, "login.cooldown", ["{TIME}" => (string)$cooldownTime]);
            return true;
        }
        
        if (count($args) < 1) {
            $this->messageManager->send($sender, "general.not-enough-args");
            return true;
        }
        
        $password = $args[0];
        $playerName = $sender->getName();
        
        // Загрузка данных игрока
        $playerData = $this->storageManager->loadPlayer($playerName);
        
        if ($playerData === null) {
            $this->messageManager->send($sender, "login.not-registered");
            return true;
        }
        
        // Проверка пароля
        if ($this->verifyPassword($password, $playerData["password"], $playerName)) {
            // Успешный вход
            $this->loginAttempts[$playerName] = 0;
            $this->setPlayerState($playerName, self::STATE_LOGGED_IN);
            
            // Обновление данных
            $playerData["last_login"] = time();
            $playerData["ip"] = $sender->getAddress();
            $this->storageManager->savePlayer($playerName, $playerData);
            
            $this->onSuccessfulLogin($sender);
            
            return true;
        } else {
            // Неверный пароль
            $this->loginAttempts[$playerName] = ($this->loginAttempts[$playerName] ?? 0) + 1;
            $this->lastLoginAttempt[$playerName] = time();
            
            // Логирование
            if ($this->plugin->getConfig()->getNested("logging.log-failed-login", true)) {
                $this->plugin->getLogger()->info("§cНеудачный вход: §f" . $playerName . " (§cневерный пароль§f). Попытка #" . $this->loginAttempts[$playerName]);
            }
            
            // Проверка на превышение попыток
            $maxAttempts = $this->plugin->getConfig()->getNested("security.max-login-attempts", 5);
            if ($this->loginAttempts[$playerName] >= $maxAttempts) {
                $this->lockoutTimes[$playerName] = time();
                
                if ($this->plugin->getConfig()->getNested("logging.log-lockouts", true)) {
                    $this->plugin->getLogger()->info("§eБлокировка: §f" . $playerName . " заблокирован на " . $this->plugin->getConfig()->getNested("security.lockout-time", 300) . " сек.");
                }
            }
            
            $this->messageManager->send($sender, "login.wrong-password");
            return true;
        }
    }
    
    public function handleChangePassword(CommandSender $sender, array $args): bool {
        if (!$sender instanceof Player) {
            $this->messageManager->send($sender, "general.player-only");
            return true;
        }
        
        if (!$this->isLoggedIn($sender->getName())) {
            $this->messageManager->send($sender, "changepassword.not-registered");
            return true;
        }
        
        if (count($args) < 2) {
            $this->messageManager->send($sender, "general.not-enough-args");
            return true;
        }
        
        $oldPassword = $args[0];
        $newPassword = $args[1];
        
        $playerData = $this->storageManager->loadPlayer($sender->getName());
        
        if ($playerData === null) {
            $this->messageManager->send($sender, "changepassword.not-registered");
            return true;
        }
        
        // Проверка старого пароля
        if (!$this->verifyPassword($oldPassword, $playerData["password"], $sender->getName())) {
            $this->messageManager->send($sender, "changepassword.wrong-old-password");
            return true;
        }
        
        // Проверка длины нового пароля
        $minLength = $this->plugin->getConfig()->getNested("security.min-password-length", 4);
        $maxLength = $this->plugin->getConfig()->getNested("security.max-password-length", 20);
        
        if (strlen($newPassword) < $minLength) {
            $this->messageManager->send($sender, "changepassword.new-password-too-short");
            return true;
        }
        
        if (strlen($newPassword) > $maxLength) {
            $this->messageManager->send($sender, "changepassword.new-password-too-long");
            return true;
        }
        
        // Хеширование нового пароля
        $hashedPassword = $this->hashPassword($newPassword, $sender->getName());
        
        // Сохранение нового пароля
        $playerData["password"] = $hashedPassword;
        if ($this->storageManager->savePlayer($sender->getName(), $playerData)) {
            $this->messageManager->send($sender, "changepassword.success");
            
            if ($this->plugin->getConfig()->getNested("logging.log-registration", true)) {
                $this->plugin->getLogger()->info("§6Смена пароля: §f" . $sender->getName() . " сменил пароль.");
            }
        } else {
            $this->messageManager->send($sender, "general.error");
        }
        
        return true;
    }
    
    public function handleAdminCommand(CommandSender $sender, array $args): bool {
        if (empty($args)) {
            $this->messageManager->send($sender, "admin.usage", ["{USAGE}" => "/auth <reload|unregister|info> [игрок]"]);
            return true;
        }
        
        $subCommand = strtolower($args[0]);
        
        switch ($subCommand) {
            case "reload":
                if (!$sender->hasPermission("serverauth.reload")) {
                    $this->messageManager->send($sender, "admin.no-permission");
                    return true;
                }
                
                try {
                    $this->plugin->reloadConfig();
                    $this->messageManager->reload();
                    $this->messageManager->send($sender, "admin.reload-success");
                } catch (\Exception $e) {
                    $this->messageManager->send($sender, "admin.reload-error");
                    $this->plugin->getLogger()->logException($e);
                }
                return true;
                
            case "unregister":
                if (!$sender->hasPermission("serverauth.unregister")) {
                    $this->messageManager->send($sender, "admin.no-permission");
                    return true;
                }
                
                if (count($args) < 2) {
                    $this->messageManager->send($sender, "general.not-enough-args");
                    return true;
                }
                
                $targetPlayer = $args[1];
                
                if ($this->storageManager->deletePlayer($targetPlayer)) {
                    unset($this->playerStates[strtolower($targetPlayer)]);
                    unset($this->loginAttempts[strtolower($targetPlayer)]);
                    
                    $this->messageManager->send($sender, "admin.unregister-success", ["{PLAYER}" => $targetPlayer]);
                    
                    if ($this->plugin->getConfig()->getNested("logging.log-admin-actions", true)) {
                        $this->plugin->getLogger()->info("§6Админ: §f" . $sender->getName() . " удалил аккаунт игрока " . $targetPlayer);
                    }
                } else {
                    $this->messageManager->send($sender, "admin.player-not-found", ["{PLAYER}" => $targetPlayer]);
                }
                return true;
                
            case "info":
                if (!$sender->hasPermission("serverauth.info")) {
                    $this->messageManager->send($sender, "admin.no-permission");
                    return true;
                }
                
                if (count($args) < 2) {
                    $this->messageManager->send($sender, "general.not-enough-args");
                    return true;
                }
                
                $targetPlayer = $args[1];
                $playerData = $this->storageManager->loadPlayer($targetPlayer);
                
                if ($playerData !== null) {
                    $status = $this->isLoggedIn($targetPlayer) ? "В сети" : "Зарегистрирован";
                    
                    $this->messageManager->sendRaw($sender, $this->plugin->getMessagesConfig()->getNested("admin.info-header"));
                    $this->messageManager->send($sender, "admin.info-line", [
                        "{PLAYER}" => $targetPlayer,
                        "{STATUS}" => $status,
                        "{IP}" => $playerData["ip"] ?? "N/A"
                    ]);
                    $this->messageManager->sendRaw($sender, $this->plugin->getMessagesConfig()->getNested("admin.info-footer"));
                } else {
                    $this->messageManager->send($sender, "admin.player-not-found", ["{PLAYER}" => $targetPlayer]);
                }
                return true;
                
            default:
                $this->messageManager->send($sender, "admin.usage", ["{USAGE}" => "/auth <reload|unregister|info> [игрок]"]);
                return true;
        }
    }
    
    private function onSuccessfulLogin(Player $player): void {
        // Показ сообщения об успешной авторизации
        $this->messageManager->sendTitle($player, "authorized");
        
        // Логирование
        if ($this->plugin->getConfig()->getNested("logging.log-successful-login", true)) {
            $this->plugin->getLogger()->info("§aВход: §f" . $player->getName() . " успешно вошел.");
        }
    }
    
    private function hashPassword(string $password, string $playerName): string {
        $salt = substr(hash("sha256", $playerName . "ServerAuthSalt"), 0, 16);
        return hash("sha256", $salt . $password . $salt) . ":" . $salt;
    }
    
    private function verifyPassword(string $password, string $hashedPassword, string $playerName): bool {
        $parts = explode(":", $hashedPassword);
        if (count($parts) !== 2) {
            return false;
        }
        
        $storedHash = $parts[0];
        $salt = $parts[1];
        
        $inputHash = hash("sha256", $salt . $password . $salt);
        
        return $inputHash === $storedHash;
    }
    
    private function isLockedOut(string $playerName): bool {
        if (!isset($this->lockoutTimes[$playerName])) {
            return false;
        }
        
        $lockoutTime = $this->plugin->getConfig()->getNested("security.lockout-time", 300);
        return (time() - $this->lockoutTimes[$playerName]) < $lockoutTime;
    }
    
    private function getLockoutRemainingTime(string $playerName): int {
        if (!isset($this->lockoutTimes[$playerName])) {
            return 0;
        }
        
        $lockoutTime = $this->plugin->getConfig()->getNested("security.lockout-time", 300);
        $elapsed = time() - $this->lockoutTimes[$playerName];
        
        return max(0, $lockoutTime - $elapsed);
    }
    
    private function isOnCooldown(string $playerName): bool {
        if (!isset($this->lastLoginAttempt[$playerName])) {
            return false;
        }
        
        $cooldown = $this->plugin->getConfig()->getNested("security.login-cooldown", 3);
        return (time() - $this->lastLoginAttempt[$playerName]) < $cooldown;
    }
    
    private function getCooldownRemainingTime(string $playerName): int {
        if (!isset($this->lastLoginAttempt[$playerName])) {
            return 0;
        }
        
        $cooldown = $this->plugin->getConfig()->getNested("security.login-cooldown", 3);
        $elapsed = time() - $this->lastLoginAttempt[$playerName];
        
        return max(0, $cooldown - $elapsed);
    }
    
    public function saveAllPlayers(): void {
        // Сброс состояний всех игроков при выключении
        foreach ($this->playerStates as $playerName => $state) {
            if ($state === self::STATE_LOGGED_IN) {
                $player = $this->plugin->getServer()->getPlayerExact($playerName);
                if ($player !== null) {
                    $playerData = $this->storageManager->loadPlayer($playerName);
                    if ($playerData !== null) {
                        $playerData["last_login"] = time();
                        $this->storageManager->savePlayer($playerName, $playerData);
                    }
                }
            }
        }
        
        $this->playerStates = [];
        $this->loginAttempts = [];
        $this->lockoutTimes = [];
        $this->lastLoginAttempt = [];
    }
    
    public function clearPlayerData(string $playerName): void {
        $key = strtolower($playerName);
        unset($this->playerStates[$key]);
        unset($this->loginAttempts[$key]);
        unset($this->lockoutTimes[$key]);
        unset($this->lastLoginAttempt[$key]);
    }
}
