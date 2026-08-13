<?php

declare(strict_types=1);

namespace LiteAuth;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use LiteAuth\manager\AuthManager;
use LiteAuth\manager\StorageManager;
use LiteAuth\manager\MessageManager;
use LiteAuth\listener\AuthListener;
use LiteAuth\listener\MoveListener;
use LiteAuth\listener\InteractListener;
use LiteAuth\listener\DamageListener;
use LiteAuth\listener\DropListener;
use LiteAuth\listener\ChatListener;
use LiteAuth\listener\CommandListener;
use LiteAuth\listener\QuitListener;
use LiteAuth\command\LoginCommand;
use LiteAuth\command\RegisterCommand;
use LiteAuth\command\CaptchaCommand;
use LiteAuth\command\AuthCommand;

class LiteAuthPlugin extends PluginBase {

    private static $instance;
    private $authManager;
    private $storageManager;
    private $messageManager;
    private $config;
    private $messagesConfig;

    public function onEnable() {
        self::$instance = $this;
        
        $this->getLogger()->info("LiteAuth v" . $this->getDescription()->getVersion() . " enabled.");
        
        @mkdir($this->getDataFolder() . "players");
        @mkdir($this->getDataFolder() . "logs");

        $this->saveResource("config.yml");
        $this->saveResource("messages.yml");

        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $this->messagesConfig = new Config($this->getDataFolder() . "messages.yml", Config::YAML);

        $this->messageManager = new MessageManager($this);
        $this->storageManager = new StorageManager($this);
        $this->authManager = new AuthManager($this);

        $this->registerCommands();
        $this->registerListeners();

        $this->getLogger()->info("Storage loaded.");
        if ($this->getConfigValue("captcha-enabled", true)) {
            $this->getLogger()->info("Captcha system enabled.");
        }
        if ($this->getConfigValue("auto-login", true)) {
            $this->getLogger()->info("Auto-login enabled.");
        }
        
        $this->getLogger()->info("Plugin ready.");
    }

    public function onDisable() {
        if ($this->authManager !== null) {
            $this->authManager->clearAllSessions();
        }
        $this->getLogger()->info("LiteAuth disabled.");
    }

    private function registerCommands() {
        $commandMap = $this->getServer()->getCommandMap();
        
        $commandMap->register("liteauth", new LoginCommand($this));
        $commandMap->register("liteauth", new RegisterCommand($this));
        $commandMap->register("liteauth", new CaptchaCommand($this));
        $commandMap->register("liteauth", new AuthCommand($this));
    }

    private function registerListeners() {
        $pm = $this->getServer()->getPluginManager();
        
        $pm->registerEvent(new AuthListener($this), $this);
        $pm->registerEvent(new MoveListener($this), $this);
        $pm->registerEvent(new InteractListener($this), $this);
        $pm->registerEvent(new DamageListener($this), $this);
        $pm->registerEvent(new DropListener($this), $this);
        $pm->registerEvent(new ChatListener($this), $this);
        $pm->registerEvent(new CommandListener($this), $this);
        $pm->registerEvent(new QuitListener($this), $this);
    }

    public static function getInstance(): LiteAuthPlugin {
        return self::$instance;
    }

    public function getAuthManager(): AuthManager {
        return $this->authManager;
    }

    public function getStorageManager(): StorageManager {
        return $this->storageManager;
    }

    public function getMessageManager(): MessageManager {
        return $this->messageManager;
    }

    public function getMessagesConfig(): Config {
        return $this->messagesConfig;
    }

    public function getConfigValue(string $key, $default = null) {
        $value = $this->config->get($key);
        return $value !== null ? $value : $default;
    }

    public function reloadConfigs(): bool {
        try {
            $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
            $this->messagesConfig = new Config($this->getDataFolder() . "messages.yml", Config::YAML);
            return true;
        } catch (\Exception $e) {
            $this->getLogger()->error("Failed to reload config: " . $e->getMessage());
            return false;
        }
    }
}
