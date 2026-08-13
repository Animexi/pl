<?php

namespace LiteAuth;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\Player;
use LiteAuth\manager\AuthManager;
use LiteAuth\storage\YamlStorage;
use LiteAuth\listener\JoinListener;
use LiteAuth\listener\QuitListener;
use LiteAuth\listener\MoveListener;
use LiteAuth\listener\InteractListener;
use LiteAuth\listener\DamageListener;
use LiteAuth\listener\DropListener;
use LiteAuth\listener\ChatListener;
use LiteAuth\listener\CommandListener;
use LiteAuth\command\RegisterCommand;
use LiteAuth\command\LoginCommand;
use LiteAuth\command\CaptchaCommand;
use LiteAuth\command\AuthCommand;

class LiteAuthPlugin extends PluginBase {
    
    private static $instance = null;
    private $authManager;
    private $config;
    private $messages;
    
    public function onEnable() {
        self::$instance = $this;
        
        @mkdir($this->getDataFolder());
        
        $this->saveResource("config.yml");
        $this->saveResource("messages.yml");
        
        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $this->messages = new Config($this->getDataFolder() . "messages.yml", Config::YAML);
        
        $this->validateConfig();
        
        $storage = new YamlStorage($this->getDataFolder() . "players/");
        $this->authManager = new AuthManager($this, $storage, $this->config, $this->messages);
        
        $this->registerCommands();
        $this->registerListeners();
        
        $this->getLogger()->info("Plugin enabled.");
        $this->getLogger()->info("Storage loaded.");
        if ($this->getConfig()->get("captcha-enabled", true)) {
            $this->getLogger()->info("Captcha system enabled.");
        }
        if ($this->getConfig()->get("auto-login", true)) {
            $this->getLogger()->info("Auto-login enabled.");
        }
    }
    
    private function validateConfig() {
        $defaults = [
            "min-password-length" => 6,
            "max-password-length" => 32,
            "auth-timeout" => 60,
            "max-login-attempts" => 5,
            "login-delay" => 1000,
            "captcha-enabled" => true,
            "captcha-attempts" => 3,
            "captcha-timeout" => 60,
            "auto-login" => true,
            "session-time" => 86400,
            "session-by-ip" => false,
            "registration-enabled" => true,
            "max-registrations-per-ip" => 3,
            "kick-on-timeout" => true,
            "debug" => false,
            "password-blacklist" => ["123456", "password", "qwerty", "123123", "admin", "server"]
        ];
        
        foreach ($defaults as $key => $value) {
            if (!isset($this->config->getAll()[$key])) {
                $this->config->set($key, $value);
            }
        }
        
        $minPass = $this->config->get("min-password-length", 6);
        $maxPass = $this->config->get("max-password-length", 32);
        if ($minPass > $maxPass || $minPass < 1) {
            $this->config->set("min-password-length", 6);
            $this->config->set("max-password-length", 32);
        }
        
        $authTimeout = $this->config->get("auth-timeout", 60);
        if ($authTimeout <= 0) {
            $this->config->set("auth-timeout", 60);
        }
        
        $maxAttempts = $this->config->get("max-login-attempts", 5);
        if ($maxAttempts <= 0) {
            $this->config->set("max-login-attempts", 5);
        }
        
        $this->config->save();
    }
    
    private function registerCommands() {
        $commandMap = $this->getServer()->getCommandMap();
        $commandMap->register("liteauth", new RegisterCommand($this, $this->authManager));
        $commandMap->register("liteauth", new LoginCommand($this, $this->authManager));
        $commandMap->register("liteauth", new CaptchaCommand($this, $this->authManager));
        $commandMap->register("liteauth", new AuthCommand($this, $this->authManager));
    }
    
    private function registerListeners() {
        $pm = $this->getServer()->getPluginManager();
        $pm->registerEvents(new JoinListener($this, $this->authManager), $this);
        $pm->registerEvents(new QuitListener($this, $this->authManager));
        $pm->registerEvents(new MoveListener($this, $this->authManager));
        $pm->registerEvents(new InteractListener($this, $this->authManager));
        $pm->registerEvents(new DamageListener($this, $this->authManager));
        $pm->registerEvents(new DropListener($this, $this->authManager));
        $pm->registerEvents(new ChatListener($this, $this->authManager));
        $pm->registerEvents(new CommandListener($this, $this->authManager));
    }
    
    public function onDisable() {
        if ($this->authManager !== null) {
            $this->authManager->cleanup();
        }
        $this->getLogger()->info("Plugin disabled.");
    }
    
    public static function getInstance() {
        return self::$instance;
    }
    
    public function getAuthManager() {
        return $this->authManager;
    }
    
    public function getConfig() {
        return $this->config;
    }
    
    public function getMessages() {
        return $this->messages;
    }
    
    public function getMessage($key, $params = []) {
        $all = $this->messages->getAll();
        $message = isset($all[$key]) ? $all[$key] : $key;
        
        foreach ($params as $param => $value) {
            $message = str_replace("{" . $param . "}", $value, $message);
        }
        
        $message = str_replace("{prefix}", "§e§lLITE§f§lAUTH §8┃", $message);
        $message = str_replace("{password_min}", $this->config->get("min-password-length", 6), $message);
        $message = str_replace("{password_max}", $this->config->get("max-password-length", 32), $message);
        
        return $message;
    }
    
    public function reloadConfig() {
        $this->config->reload();
        $this->messages->reload();
        $this->validateConfig();
    }
}
