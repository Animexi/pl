<?php

declare(strict_types=1);

namespace LiteAuth\manager;

use LiteAuth\LiteAuthPlugin;
use pocketmine\Player;

class AuthManager {

    const STATE_UNREGISTERED = 0;
    const STATE_REGISTERED = 1;
    const STATE_CAPTCHA = 2;
    const STATE_AUTH_REQUIRED = 3;
    const STATE_AUTHENTICATED = 4;

    private $plugin;
    private $playerStates = [];
    private $captchaData = [];
    private $loginAttempts = [];
    private $lastCaptchaTime = [];
    private $sessionData = [];

    public function __construct(LiteAuthPlugin $plugin) {
        $this->plugin = $plugin;
    }

    public function getState(Player $player): int {
        $name = strtolower($player->getName());
        return isset($this->playerStates[$name]) ? $this->playerStates[$name] : self::STATE_UNREGISTERED;
    }

    public function setState(Player $player, int $state) {
        $name = strtolower($player->getName());
        $this->playerStates[$name] = $state;
    }

    public function isRegistered(Player $player): bool {
        return $this->plugin->getStorageManager()->playerExists($player->getName());
    }

    public function isAuthenticated(Player $player): bool {
        return $this->getState($player) === self::STATE_AUTHENTICATED;
    }

    public function needsCaptcha(Player $player): bool {
        return $this->getState($player) === self::STATE_CAPTCHA;
    }

    public function needsLogin(Player $player): bool {
        $state = $this->getState($player);
        return $state === self::STATE_REGISTERED || $state === self::STATE_AUTH_REQUIRED;
    }

    public function generateCaptcha(Player $player): string {
        $num1 = rand(1, 10);
        $num2 = rand(1, 10);
        $ops = ["+", "-", "*"];
        
        if (rand(0, 2) === 0 && $num1 * $num2 <= 50) {
            $op = "*";
            $answer = $num1 * $num2;
        } elseif ($num1 >= $num2) {
            $op = "-";
            $answer = $num1 - $num2;
        } else {
            $op = "+";
            $answer = $num1 + $num2;
        }

        $name = strtolower($player->getName());
        $this->captchaData[$name] = $answer;
        $this->lastCaptchaTime[$name] = time();

        return "$num1 $op $num2 = ?";
    }

    public function checkCaptcha(Player $player, int $answer): bool {
        $name = strtolower($player->getName());
        
        if (!isset($this->captchaData[$name])) {
            return false;
        }

        $correct = $this->captchaData[$name];
        unset($this->captchaData[$name]);
        unset($this->lastCaptchaTime[$name]);

        return $answer === $correct;
    }

    public function getCaptchaAttemptCount(Player $player): int {
        $name = strtolower($player->getName());
        return isset($this->captchaData[$name . "_attempts"]) ? $this->captchaData[$name . "_attempts"] : 0;
    }

    public function incrementCaptchaAttempts(Player $player) {
        $name = strtolower($player->getName());
        if (!isset($this->captchaData[$name . "_attempts"])) {
            $this->captchaData[$name . "_attempts"] = 0;
        }
        $this->captchaData[$name . "_attempts"]++;
    }

    public function resetCaptchaAttempts(Player $player) {
        $name = strtolower($player->getName());
        unset($this->captchaData[$name . "_attempts"]);
    }

    public function getLoginAttempts(Player $player): int {
        $name = strtolower($player->getName());
        return isset($this->loginAttempts[$name]) ? $this->loginAttempts[$name] : 0;
    }

    public function incrementLoginAttempts(Player $player) {
        $name = strtolower($player->getName());
        if (!isset($this->loginAttempts[$name])) {
            $this->loginAttempts[$name] = 0;
        }
        $this->loginAttempts[$name]++;
    }

    public function resetLoginAttempts(Player $player) {
        $name = strtolower($player->getName());
        unset($this->loginAttempts[$name]);
    }

    public function hashPassword(string $password): string {
        if (function_exists("password_hash")) {
            return password_hash($password, PASSWORD_DEFAULT);
        }
        return base64_encode(hash("sha256", $password, true));
    }

    public function verifyPassword(string $password, string $hash): bool {
        if (function_exists("password_verify")) {
            return password_verify($password, $hash);
        }
        return $this->hashPassword($password) === $hash;
    }

    public function register(Player $player, string $password): bool {
        $name = $player->getName();
        $ip = $player->getAddress();

        $data = [
            "name" => $name,
            "password" => $this->hashPassword($password),
            "registered" => time(),
            "last-login" => time(),
            "last-ip" => $ip
        ];

        if (!$this->plugin->getStorageManager()->savePlayer($name, $data)) {
            return false;
        }

        $this->setState($player, self::STATE_CAPTCHA);
        $this->plugin->getLogger()->info("Player $name registered successfully");
        return true;
    }

    public function login(Player $player, string $password): bool {
        $name = $player->getName();
        $data = $this->plugin->getStorageManager()->getPlayerData($name);

        if ($data === null) {
            return false;
        }

        if (!$this->verifyPassword($password, $data["password"])) {
            return false;
        }

        $this->setState($player, self::STATE_AUTHENTICATED);
        
        $data["last-login"] = time();
        $data["last-ip"] = $player->getAddress();
        $this->plugin->getStorageManager()->savePlayer($name, $data);

        $this->resetLoginAttempts($player);
        $this->saveSession($player);

        $this->plugin->getLogger()->info("Player $name logged in successfully");
        return true;
    }

    public function saveSession(Player $player) {
        $name = strtolower($player->getName());
        $this->sessionData[$name] = [
            "time" => time(),
            "ip" => $player->getAddress()
        ];
    }

    public function hasValidSession(Player $player): bool {
        $name = strtolower($player->getName());
        
        if (!isset($this->sessionData[$name])) {
            return false;
        }

        $sessionTime = $this->plugin->getConfigValue("session-time", 86400);
        $sessionByIp = $this->plugin->getConfigValue("session-by-ip", false);

        $session = $this->sessionData[$name];
        
        if (time() - $session["time"] > $sessionTime) {
            unset($this->sessionData[$name]);
            return false;
        }

        if ($sessionByIp && $session["ip"] !== $player->getAddress()) {
            return false;
        }

        return true;
    }

    public function clearSession(Player $player) {
        $name = strtolower($player->getName());
        unset($this->sessionData[$name]);
    }

    public function clearAllSessions() {
        $this->sessionData = [];
        $this->playerStates = [];
        $this->captchaData = [];
        $this->loginAttempts = [];
        $this->lastCaptchaTime = [];
    }

    public function cleanupPlayer(Player $player) {
        $name = strtolower($player->getName());
        unset($this->playerStates[$name]);
        unset($this->captchaData[$name]);
        unset($this->captchaData[$name . "_attempts"]);
        unset($this->loginAttempts[$name]);
        unset($this->lastCaptchaTime[$name]);
        unset($this->sessionData[$name]);
    }
}
