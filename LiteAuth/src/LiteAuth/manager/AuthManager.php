<?php

namespace LiteAuth\manager;

use pocketmine\Player;
use pocketmine\level\Location;
use LiteAuth\LiteAuthPlugin;
use LiteAuth\storage\YamlStorage;
use pocketmine\utils\Config;

class AuthManager {
    
    const STATE_UNREGISTERED = 0;
    const STATE_REGISTERED = 1;
    const STATE_CAPTCHA = 2;
    const STATE_AUTH_REQUIRED = 3;
    const STATE_AUTHENTICATED = 4;
    
    private $plugin;
    private $storage;
    private $config;
    private $messages;
    private $playerStates = [];
    private $playerData = [];
    private $captchaData = [];
    private $loginAttempts = [];
    private $lastActionTime = [];
    private $registrationByIp = [];
    
    public function __construct(LiteAuthPlugin $plugin, YamlStorage $storage, Config $config, Config $messages) {
        $this->plugin = $plugin;
        $this->storage = $storage;
        $this->config = $config;
        $this->messages = $messages;
    }
    
    public function getPlugin() {
        return $this->plugin;
    }
    
    public function getStorage() {
        return $this->storage;
    }
    
    public function getState($player) {
        $name = strtolower($player instanceof Player ? $player->getName() : $player);
        return isset($this->playerStates[$name]) ? $this->playerStates[$name] : self::STATE_UNREGISTERED;
    }
    
    public function setState($player, $state) {
        $name = strtolower($player instanceof Player ? $player->getName() : $player);
        $this->playerStates[$name] = $state;
    }
    
    public function isRegistered($player) {
        $name = strtolower($player instanceof Player ? $player->getName() : $player);
        return $this->storage->exists($name);
    }
    
    public function isAuthenticated($player) {
        return $this->getState($player) === self::STATE_AUTHENTICATED;
    }
    
    public function needsCaptcha($player) {
        return $this->getState($player) === self::STATE_CAPTCHA;
    }
    
    public function needsAuth($player) {
        $state = $this->getState($player);
        return $state === self::STATE_AUTH_REQUIRED || $state === self::STATE_REGISTERED;
    }
    
    public function handleJoin($player) {
        $name = strtolower($player->getName());
        $ip = $player->getAddress();
        
        if ($this->hasPermission($player, "liteauth.bypass")) {
            $this->setState($player, self::STATE_AUTHENTICATED);
            return;
        }
        
        $currentTime = time();
        
        if ($this->isRegistered($player)) {
            $account = $this->storage->get($name);
            
            if ($this->config->get("auto-login", true) && isset($account["session"])) {
                $session = $account["session"];
                $sessionTime = $this->config->get("session-time", 86400);
                
                if (isset($session["expires"]) && $session["expires"] > $currentTime) {
                    $sessionByIp = $this->config->get("session-by-ip", false);
                    if (!$sessionByIp || (isset($session["ip"]) && $session["ip"] === $ip)) {
                        $this->setState($player, self::STATE_AUTHENTICATED);
                        $this->saveSession($player);
                        $player->sendMessage($this->formatMessage("auto-login-success"));
                        $this->log("Auto-login: " . $player->getName());
                        return;
                    }
                }
            }
            
            $this->setState($player, self::STATE_AUTH_REQUIRED);
            $this->showLoginMessage($player);
            $this->startAuthTimeout($player);
        } else {
            if (!$this->config->get("registration-enabled", true)) {
                $player->kick("§cРегистрация временно отключена.");
                return;
            }
            
            $maxReg = $this->config->get("max-registrations-per-ip", 3);
            if (isset($this->registrationByIp[$ip]) && $this->registrationByIp[$ip] >= $maxReg) {
                $player->kick("§cСлишком много регистраций с вашего IP.");
                return;
            }
            
            $this->setState($player, self::STATE_UNREGISTERED);
            $this->showRegisterMessage($player);
        }
    }
    
    public function register($player, $password, $confirmPassword) {
        $name = strtolower($player->getName());
        $ip = $player->getAddress();
        
        if ($this->isRegistered($player)) {
            $player->sendMessage($this->formatMessage("already-registered"));
            return false;
        }
        
        if (!$this->config->get("registration-enabled", true)) {
            $player->sendMessage($this->formatMessage("registration-disabled"));
            return false;
        }
        
        if ($password !== $confirmPassword) {
            $player->sendMessage($this->formatMessage("password-mismatch"));
            return false;
        }
        
        $minLen = $this->config->get("min-password-length", 6);
        $maxLen = $this->config->get("max-password-length", 32);
        
        if (strlen($password) < $minLen) {
            $player->sendMessage($this->formatMessage("password-too-short", ["min" => $minLen]));
            return false;
        }
        
        if (strlen($password) > $maxLen) {
            $player->sendMessage($this->formatMessage("password-too-long", ["max" => $maxLen]));
            return false;
        }
        
        if (strpos($password, " ") !== false) {
            $player->sendMessage($this->formatMessage("password-invalid-chars"));
            return false;
        }
        
        $blacklist = $this->config->get("password-blacklist", []);
        if (in_array(strtolower($password), $blacklist)) {
            $player->sendMessage($this->formatMessage("password-blacklisted"));
            return false;
        }
        
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        $account = [
            "name" => $player->getName(),
            "password" => $hash,
            "registered" => time(),
            "lastlogin" => time(),
            "ip" => $ip
        ];
        
        $this->storage->save($name, $account);
        
        if (!isset($this->registrationByIp[$ip])) {
            $this->registrationByIp[$ip] = 0;
        }
        $this->registrationByIp[$ip]++;
        
        $this->setState($player, self::STATE_REGISTERED);
        
        $player->sendMessage($this->formatBoxedMessage([
            "§e§lLITEAUTH",
            "",
            "§aАккаунт успешно создан.",
            "",
            "§7Теперь необходимо пройти",
            "§7небольшую проверку.",
            ""
        ]));
        
        $this->log("Registration: " . $player->getName());
        
        if ($this->config->get("captcha-enabled", true)) {
            $this->generateCaptcha($player);
        } else {
            $this->setState($player, self::STATE_AUTHENTICATED);
            $this->saveSession($player);
            $player->sendMessage($this->formatMessage("register-success-no-captcha"));
        }
        
        return true;
    }
    
    public function generateCaptcha($player) {
        $name = strtolower($player->getName());
        
        $operators = ["+", "-", "*"];
        $operator = $operators[array_rand($operators)];
        
        switch ($operator) {
            case "+":
                $num1 = rand(1, 15);
                $num2 = rand(1, 15);
                $answer = $num1 + $num2;
                $question = "$num1 + $num2";
                break;
            case "-":
                $num1 = rand(5, 20);
                $num2 = rand(1, $num1);
                $answer = $num1 - $num2;
                $question = "$num1 - $num2";
                break;
            case "*":
                $num1 = rand(2, 9);
                $num2 = rand(2, 9);
                $answer = $num1 * $num2;
                $question = "$num1 × $num2";
                break;
        }
        
        $this->captchaData[$name] = [
            "answer" => $answer,
            "attempts" => 0,
            "time" => time()
        ];
        
        $this->setState($player, self::STATE_CAPTCHA);
        
        $player->sendMessage($this->formatBoxedMessage([
            "§e§lПРОВЕРКА",
            "",
            "§fРешите пример:",
            "",
            "§e§l$question = ?",
            "",
            "§7Ответ: §e/captcha <число>"
        ]));
        
        $this->startCaptchaTimeout($player);
    }
    
    public function checkCaptcha($player, $answer) {
        $name = strtolower($player->getName());
        
        if (!isset($this->captchaData[$name])) {
            $player->sendMessage($this->formatMessage("no-captcha-active"));
            return false;
        }
        
        $captcha = $this->captchaData[$name];
        $maxAttempts = $this->config->get("captcha-attempts", 3);
        
        if ($captcha["attempts"] >= $maxAttempts) {
            unset($this->captchaData[$name]);
            $player->sendMessage($this->formatMessage("captcha-max-attempts"));
            if ($this->config->get("kick-on-timeout", true)) {
                $player->kick($this->formatMessage("kick-captcha-fail"));
            } else {
                $this->generateCaptcha($player);
            }
            return false;
        }
        
        if ((int)$answer !== $captcha["answer"]) {
            $captcha["attempts"]++;
            $this->captchaData[$name] = $captcha;
            $remaining = $maxAttempts - $captcha["attempts"];
            $player->sendMessage($this->formatMessage("captcha-wrong", ["attempts" => $remaining]));
            return false;
        }
        
        unset($this->captchaData[$name]);
        $this->setState($player, self::STATE_AUTHENTICATED);
        $this->saveSession($player);
        
        $player->sendMessage($this->formatBoxedMessage([
            "§e§lLITEAUTH",
            "",
            "§aПроверка успешно пройдена.",
            "§7Аккаунт готов к использованию.",
            ""
        ]));
        
        $this->log("Captcha passed: " . $player->getName());
        return true;
    }
    
    public function login($player, $password) {
        $name = strtolower($player->getName());
        
        if (!$this->isRegistered($player)) {
            $player->sendMessage($this->formatMessage("not-registered"));
            return false;
        }
        
        if ($this->isAuthenticated($player)) {
            $player->sendMessage($this->formatMessage("already-authenticated"));
            return false;
        }
        
        $maxAttempts = $this->config->get("max-login-attempts", 5);
        if (isset($this->loginAttempts[$name]) && $this->loginAttempts[$name] >= $maxAttempts) {
            $player->sendMessage($this->formatMessage("max-login-attempts"));
            if ($this->config->get("kick-on-timeout", true)) {
                $player->kick($this->formatMessage("kick-login-fail"));
            }
            return false;
        }
        
        $account = $this->storage->get($name);
        if (!password_verify($password, $account["password"])) {
            if (!isset($this->loginAttempts[$name])) {
                $this->loginAttempts[$name] = 0;
            }
            $this->loginAttempts[$name]++;
            
            $remaining = $maxAttempts - $this->loginAttempts[$name];
            $player->sendMessage($this->formatMessage("login-failed", ["attempts" => $remaining]));
            $this->log("Login failed: " . $player->getName() . " (attempts: " . $this->loginAttempts[$name] . ")");
            return false;
        }
        
        unset($this->loginAttempts[$name]);
        $this->setState($player, self::STATE_AUTHENTICATED);
        $this->saveSession($player);
        
        $account["lastlogin"] = time();
        $this->storage->save($name, $account);
        
        $player->sendMessage($this->formatBoxedMessage([
            "§e§lLITEAUTH",
            "",
            "§aАвторизация выполнена.",
            "§7Добро пожаловать, §f" . $player->getName() . "§7.",
            ""
        ]));
        
        $this->log("Login success: " . $player->getName());
        return true;
    }
    
    public function saveSession($player) {
        $name = strtolower($player->getName());
        if (!$this->isRegistered($player)) {
            return;
        }
        
        $account = $this->storage->get($name);
        $sessionTime = $this->config->get("session-time", 86400);
        
        $account["session"] = [
            "created" => time(),
            "expires" => time() + $sessionTime,
            "ip" => $this->config->get("session-by-ip", false) ? $player->getAddress() : null
        ];
        
        $this->storage->save($name, $account);
    }
    
    public function startAuthTimeout($player) {
        $name = strtolower($player->getName());
        $this->lastActionTime[$name] = time();
    }
    
    public function startCaptchaTimeout($player) {
        $name = strtolower($player->getName());
        $this->lastActionTime[$name] = time();
    }
    
    public function checkTimeouts() {
        $currentTime = time();
        $authTimeout = $this->config->get("auth-timeout", 60);
        $captchaTimeout = $this->config->get("captcha-timeout", 60);
        
        foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
            $name = strtolower($player->getName());
            if (!isset($this->lastActionTime[$name])) {
                continue;
            }
            
            $state = $this->getState($player);
            $timeout = ($state === self::STATE_CAPTCHA) ? $captchaTimeout : $authTimeout;
            
            if ($currentTime - $this->lastActionTime[$name] > $timeout) {
                if ($state === self::STATE_CAPTCHA) {
                    unset($this->captchaData[$name]);
                    $player->sendMessage($this->formatMessage("captcha-timeout"));
                    if ($this->config->get("kick-on-timeout", true)) {
                        $player->kick($this->formatMessage("kick-timeout"));
                    } else {
                        $this->generateCaptcha($player);
                    }
                } elseif ($state === self::STATE_AUTH_REQUIRED) {
                    $player->sendMessage($this->formatMessage("auth-timeout"));
                    if ($this->config->get("kick-on-timeout", true)) {
                        $player->kick($this->formatMessage("kick-timeout"));
                    }
                }
            }
        }
    }
    
    public function showLoginMessage($player) {
        $player->sendMessage($this->formatBoxedMessage([
            "§e§lLITEAUTH",
            "",
            "§fАккаунт найден.",
            "§7Введите пароль для входа.",
            "",
            "§e/login <пароль>",
            ""
        ]));
    }
    
    public function showRegisterMessage($player) {
        $player->sendMessage($this->formatBoxedMessage([
            "§e§lLITEAUTH",
            "",
            "§fДобро пожаловать на сервер.",
            "§7Для продолжения необходимо",
            "§7создать аккаунт.",
            "",
            "§e/register <пароль> <пароль>",
            "",
            "§7Пример: §f/register password password",
            ""
        ]));
    }
    
    public function formatMessage($key, $params = []) {
        return $this->plugin->getMessage($key, $params);
    }
    
    public function formatBoxedMessage($lines) {
        $top = "╔══════════════════════════════╗";
        $bottom = "╚══════════════════════════════╝";
        $side = "║";
        
        $message = $top . "\n";
        foreach ($lines as $line) {
            $message .= $side . " " . $line . str_repeat(" ", max(0, 28 - strlen(preg_replace('/§[0-9a-fk-or]/', '', $line)))) . "\n";
        }
        $message .= $bottom;
        
        return $message;
    }
    
    public function hasPermission($player, $permission) {
        if (!$player instanceof Player) {
            return true;
        }
        return $player->hasPermission($permission);
    }
    
    public function cleanup() {
        $this->playerStates = [];
        $this->playerData = [];
        $this->captchaData = [];
        $this->loginAttempts = [];
        $this->lastActionTime = [];
    }
    
    public function handleQuit($player) {
        $name = strtolower($player->getName());
        unset($this->playerStates[$name]);
        unset($this->playerData[$name]);
        unset($this->captchaData[$name]);
        unset($this->loginAttempts[$name]);
        unset($this->lastActionTime[$name]);
    }
    
    public function log($message) {
        $this->plugin->getLogger()->info($message);
    }
    
    public function debug($message) {
        if ($this->config->get("debug", false)) {
            $this->plugin->getLogger()->info("[DEBUG] " . $message);
        }
    }
}
