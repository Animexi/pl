<?php

declare(strict_types=1);

namespace LiteAuth;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\Player;
use pocketmine\Server;
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
    
    /** @var AuthManager */
    private $authManager;
    
    /** @var StorageManager */
    private $storageManager;
    
    /** @var MessageManager */
    private $messageManager;

    public function onEnable() {
        self::$instance = $this;
        
        $this->getLogger()->info("LiteAuth v" . $this->getDescription()->getVersion() . " enabled.");
        
        // Создаём папки
        @mkdir($this->getDataFolder() . "players");
        @mkdir($this->getDataFolder() . "logs");

        // Загружаем конфиги
        $this->saveResource("config.yml");
        $this->saveResource("messages.yml");

        $this->messageManager = new MessageManager($this);
        $this->storageManager = new StorageManager($this);
        $this->authManager = new AuthManager($this);

        // Регистрируем команды
        $this->registerCommands();

        // Регистрируем слушателей
        $this->registerListeners();

        $this->getLogger()->info("Storage loaded.");
        if ($this->getConfig()->get("captcha-enabled", true)) {
            $this->getLogger()->info("Captcha system enabled.");
        }
        if ($this->getConfig()->get("auto-login", true)) {
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
        
        $loginCmd = new LoginCommand($this);
        $commandMap->register("liteauth", $loginCmd);
        
        $regCmd = new RegisterCommand($this);
        $commandMap->register("liteauth", $regCmd);
        
        $captchaCmd = new CaptchaCommand($this);
        $commandMap->register("liteauth", $captchaCmd);
        
        $authCmd = new AuthCommand($this);
        $commandMap->register("liteauth", $authCmd);
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
}
