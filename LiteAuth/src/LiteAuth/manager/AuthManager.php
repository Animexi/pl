<?php

declare(strict_types=1);

namespace LiteAuth\manager;

use pocketmine\Player;
use LiteAuth\LiteAuthPlugin;

class AuthManager {

    /** @var LiteAuthPlugin */
    private $plugin;
    
    /** @var array<string, int> States: 0=UNREGISTERED, 1=REGISTERED_NOT_AUTH, 2=CAPTCHA_REQUIRED, 3=AUTHENTICATED */
    private $playerStates = [];
    
    /** @var array<string, array> */
    private $captchaData = [];
    
    /** @var array<string, int> */
    private $loginAttempts = [];
    
    /** @var array<string, int> */
    private $captchaAttempts = [];
    
    /** @var array<string, int> */
    private $lastActionTime = [];
    
    /** @var array<string, int> */
    private $banUntil = [];

    const STATE_UNREGISTERED = 0;
    const STATE_REGISTERED_NOT_AUTH = 1;
    const STATE_CAPTCHA_REQUIRED = 2;
    const STATE_AUTHENTICATED = 3;

    public function __construct(LiteAuthPlugin $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * Обрабатывает вход игрока
     */
    public function handleJoin(Player $player): void {
        $name = $player->getName();
        $ip = $player->getAddress();
        
        // Сбрасываем временные данные
        unset($this->loginAttempts[$name]);
        unset($this->captchaAttempts[$name]);
        unset($this->captchaData[$name]);
        unset($this->banUntil[$name]);

        // Проверяем регистрацию
        if (!$this->plugin->getStorageManager()->isRegistered($name)) {
            $this->playerStates[$name] = self::STATE_UNREGISTERED;
            $this->plugin->getMessageManager()->send($player, "join-unregistered");
            return;
        }

        // Проверяем сессию для авто-логина
        if ($this->checkSession($player)) {
            $this->setAuthenticated($player);
            $this->plugin->getMessageManager()->sendPrefix($player, "§aАвтоматическая авторизация выполнена.");
            return;
        }

        // Требуется авторизация
        $this->playerStates[$name] = self::STATE_REGISTERED_NOT_AUTH;
        $this->plugin->getMessageManager()->send($player, "join-registered");
        
        // Запускаем таймер
        $this->startAuthTimer($player);
    }

    /**
     * Проверяет сессию игрока
     */
    private function checkSession(Player $player): bool {
        if (!$this->plugin->getConfig()->get("auto-login", true)) {
            return false;
        }

        $name = $player->getName();
        $sessionData = $this->plugin->getStorageManager()->getSessionData($name);
        
        if ($sessionData === null || !isset($sessionData["token"])) {
            return false;
        }

        // Проверяем срок действия
        if ($sessionData["expires"] < time()) {
            return false;
        }

        // Проверяем IP если включено
        if ($this->plugin->getConfig()->get("session-by-ip", false)) {
            $lastIP = $this->plugin->getStorageManager()->getLastIP($name);
            if ($lastIP !== $player->getAddress()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Регистрирует нового игрока
     */
    public function register(Player $player, string $password, string $confirmPassword): bool {
        $name = $player->getName();
        $msg = $this->plugin->getMessageManager();

        // Проверка состояния
        if (isset($this->playerStates[$name]) && $this->playerStates[$name] >= self::STATE_AUTHENTICATED) {
            $msg->sendPrefix($player, "§cВы уже зарегистрированы и авторизованы.");
            return false;
        }

        if ($this->plugin->getStorageManager()->isRegistered($name)) {
            $msg->send($player, "register-already-exists");
            return false;
        }

        // Проверка включена ли регистрация
        if (!$this->plugin->getConfig()->get("registration-enabled", true)) {
            $msg->send($player, "register-disabled");
            return false;
        }

        // Проверка лимита по IP
        $maxReg = (int)$this->plugin->getConfig()->get("max-registrations-per-ip", 3);
        if ($maxReg > 0) {
            // Упрощённая проверка (можно улучшить)
        }

        // Проверка паролей
        if ($password !== $confirmPassword) {
            $msg->send($player, "register-password-mismatch");
            return false;
        }

        $minLen = (int)$this->plugin->getConfig()->get("min-password-length", 6);
        $maxLen = (int)$this->plugin->getConfig()->get("max-password-length", 32);

        if (strlen($password) < $minLen) {
            $msg->send($player, "register-password-too-short", ["min" => $minLen]);
            return false;
        }

        if (strlen($password) > $maxLen) {
            $msg->send($player, "register-password-too-long", ["max" => $maxLen]);
            return false;
        }

        // Проверка на запрещённые символы
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $password)) {
            $msg->send($player, "register-invalid-chars");
            return false;
        }

        // Проверка на простые пароли
        $forbidden = $this->plugin->getConfig()->get("forbidden-passwords", []);
        if (in_array(strtolower($password), array_map('strtolower', $forbidden))) {
            $msg->send($player, "register-forbidden-password");
            return false;
        }

        // Хешируем пароль
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // Создаём аккаунт
        if (!$this->plugin->getStorageManager()->createAccount($name, $hash, $player->getAddress())) {
            $msg->sendPrefix($player, "§cНе удалось создать аккаунт.");
            return false;
        }

        // Успешная регистрация
        $this->playerStates[$name] = self::STATE_CAPTCHA_REQUIRED;
        $msg->send($player, "register-success");

        // Генерируем капчу
        $this->generateCaptcha($player);

        return true;
    }

    /**
     * Вход в аккаунт
     */
    public function login(Player $player, string $password): bool {
        $name = $player->getName();
        $msg = $this->plugin->getMessageManager();

        // Проверка бана попыток
        if (isset($this->banUntil[$name]) && $this->banUntil[$name] > time()) {
            $remaining = $this->banUntil[$name] - time();
            $msg->send($player, "login-max-attempts", ["time" => $remaining]);
            return false;
        }

        // Проверка регистрации
        if (!$this->plugin->getStorageManager()->isRegistered($name)) {
            $msg->send($player, "login-not-registered");
            return false;
        }

        // Проверка состояния
        $state = $this->getState($player);
        if ($state === self::STATE_AUTHENTICATED) {
            $msg->send($player, "already-authenticated");
            return false;
        }

        // Rate limit
        if (!$this->checkRateLimit($player, "login")) {
            $msg->sendPrefix($player, "§7Подождите перед следующей попыткой.");
            return false;
        }

        // Проверка пароля
        $hash = $this->plugin->getStorageManager()->getPasswordHash($name);
        if ($hash === null || !password_verify($password, $hash)) {
            $this->loginAttempts[$name] = ($this->loginAttempts[$name] ?? 0) + 1;
            
            $maxAttempts = (int)$this->plugin->getConfig()->get("max-login-attempts", 5);
            
            if ($this->loginAttempts[$name] >= $maxAttempts) {
                $banTime = (int)$this->plugin->getConfig()->get("login-ban-time", 300);
                $this->banUntil[$name] = time() + $banTime;
                $msg->send($player, "login-max-attempts", ["time" => $banTime]);
                
                // Кик через несколько секунд
                $this->plugin->getScheduler()->scheduleDelayedTask(function() use ($player) {
                    $player->kick("§e§lLITE§f§lAUTH §8┃ §cСлишком много неудачных попыток входа.");
                }, 60);
                
                return false;
            }
            
            $msg->send($player, "login-failed");
            return false;
        }

        // Успешный вход
        $this->setAuthenticated($player);
        $this->saveSession($player);
        $msg->send($player, "login-success", ["player" => $name]);

        return true;
    }

    /**
     * Генерация капчи
     */
    public function generateCaptcha(Player $player): void {
        $name = $player->getName();
        
        // Генерируем простой математический пример
        $num1 = rand(1, 15);
        $num2 = rand(1, 15);
        $ops = ['+', '-', '*'];
        $op = $ops[array_rand($ops)];
        
        switch ($op) {
            case '+':
                $answer = $num1 + $num2;
                $question = "$num1 + $num2 = ?";
                break;
            case '-':
                if ($num1 < $num2) {
                    [$num1, $num2] = [$num2, $num1];
                }
                $answer = $num1 - $num2;
                $question = "$num1 - $num2 = ?";
                break;
            case '*':
                $num1 = rand(1, 9);
                $num2 = rand(1, 9);
                $answer = $num1 * $num2;
                $question = "$num1 × $num2 = ?";
                break;
        }

        $this->captchaData[$name] = [
            "answer" => $answer,
            "question" => $question,
            "time" => time()
        ];

        $this->plugin->getMessageManager()->send($player, "captcha-request", ["question" => $question]);
    }

    /**
     * Проверка ответа капчи
     */
    public function checkCaptcha(Player $player, string $answer): bool {
        $name = $player->getName();
        $msg = $this->plugin->getMessageManager();

        if (!isset($this->captchaData[$name])) {
            $msg->send($player, "captcha-not-required");
            return false;
        }

        // Rate limit
        if (!$this->checkRateLimit($player, "captcha")) {
            $msg->send($player, "captcha-cooldown");
            return false;
        }

        $expected = $this->captchaData[$name]["answer"];
        
        if ((int)$answer !== $expected) {
            $this->captchaAttempts[$name] = ($this->captchaAttempts[$name] ?? 0) + 1;
            
            $maxAttempts = (int)$this->plugin->getConfig()->get("captcha-attempts", 3);
            
            if ($this->captchaAttempts[$name] >= $maxAttempts) {
                unset($this->captchaData[$name]);
                $msg->send($player, "captcha-max-attempts");
                $player->kick("§e§lLITE§f§lAUTH §8┃ §cСлишком много неверных попыток капчи.");
                return false;
            }
            
            $msg->send($player, "captcha-failed");
            $this->generateCaptcha($player); // Новая капча
            return false;
        }

        // Успешная капча
        unset($this->captchaData[$name]);
        unset($this->captchaAttempts[$name]);

        // Если была регистрация - автоматически авторизуем
        if (isset($this->playerStates[$name]) && $this->playerStates[$name] === self::STATE_CAPTCHA_REQUIRED) {
            $this->setAuthenticated($player);
            $this->saveSession($player);
        }

        $msg->send($player, "captcha-success");
        return true;
    }

    /**
     * Устанавливает состояние авторизации
     */
    private function setAuthenticated(Player $player): void {
        $name = $player->getName();
        $this->playerStates[$name] = self::STATE_AUTHENTICATED;
        unset($this->loginAttempts[$name]);
        unset($this->banUntil[$name]);
    }

    /**
     * Сохраняет сессию
     */
    private function saveSession(Player $player): void {
        $name = $player->getName();
        $sessionTime = (int)$this->plugin->getConfig()->get("session-time", 86400);
        $token = bin2hex(random_bytes(16));
        
        $this->plugin->getStorageManager()->updateSession($name, $token, time() + $sessionTime);
    }

    /**
     * Запускает таймер авторизации
     */
    private function startAuthTimer(Player $player): void {
        $timeout = (int)$this->plugin->getConfig()->get("auth-timeout", 60);
        $kickOnTimeout = $this->plugin->getConfig()->get("kick-on-timeout", true);
        
        $this->plugin->getScheduler()->scheduleDelayedTask(function() use ($player, $kickOnTimeout) {
            if (!$player->isOnline()) {
                return;
            }
            
            $state = $this->getState($player);
            if ($state !== self::STATE_REGISTERED_NOT_AUTH && $state !== self::STATE_CAPTCHA_REQUIRED) {
                return;
            }
            
            if ($kickOnTimeout) {
                $this->plugin->getMessageManager()->send($player, "login-timeout");
                $player->kick("§e§lLITE§f§lAUTH §8┃ §cВремя авторизации истекло.");
            }
        }, $timeout * 20);
    }

    /**
     * Получает состояние игрока
     */
    public function getState(Player $player): int {
        $name = $player->getName();
        return $this->playerStates[$name] ?? self::STATE_UNREGISTERED;
    }

    /**
     * Проверяет состояние по имени
     */
    public function getStateByName(string $name): int {
        return $this->playerStates[strtolower($name)] ?? self::STATE_UNREGISTERED;
    }

    /**
     * Проверяет авторизацию игрока
     */
    public function isAuthenticated(Player $player): bool {
        return $this->getState($player) === self::STATE_AUTHENTICATED;
    }

    /**
     * Проверяет авторизацию по имени
     */
    public function isAuthenticatedByName(string $name): bool {
        return $this->getStateByName($name) === self::STATE_AUTHENTICATED;
    }

    /**
     * Проверяет регистрацию игрока
     */
    public function isRegistered(Player $player): bool {
        $state = $this->getState($player);
        return $state !== self::STATE_UNREGISTERED;
    }

    /**
     * Проверяет регистрацию по имени
     */
    public function isRegisteredByName(string $name): bool {
        $state = $this->getStateByName($name);
        return $state !== self::STATE_UNREGISTERED;
    }

    /**
     * Rate limit проверка
     */
    private function checkRateLimit(Player $player, string $action): bool {
        $name = $player->getName();
        $window = (int)$this->plugin->getConfig()->get("rate-limit-window", 10);
        $max = (int)$this->plugin->getConfig()->get("rate-limit-max", 5);
        
        $key = $name . "_" . $action;
        $now = time();
        
        if (!isset($this->lastActionTime[$key])) {
            $this->lastActionTime[$key] = $now;
            return true;
        }
        
        if ($now - $this->lastActionTime[$key] < $window) {
            return false;
        }
        
        $this->lastActionTime[$key] = $now;
        return true;
    }

    /**
     * Очищает все сессии при выключении
     */
    public function clearAllSessions(): void {
        $this->playerStates = [];
        $this->captchaData = [];
        $this->loginAttempts = [];
        $this->captchaAttempts = [];
        $this->lastActionTime = [];
        $this->banUntil = [];
    }

    /**
     * Очищает данные игрока при выходе
     */
    public function clearPlayerData(string $name): void {
        $normalizedName = strtolower($name);
        unset($this->playerStates[$normalizedName]);
        unset($this->captchaData[$normalizedName]);
        unset($this->loginAttempts[$normalizedName]);
        unset($this->captchaAttempts[$normalizedName]);
        unset($this->lastActionTime[$normalizedName]);
        unset($this->banUntil[$normalizedName]);
    }
}
