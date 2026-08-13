<?php

namespace LiteAuth;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;

use LiteAuth\manager\AuthManager;
use LiteAuth\storage\StorageManager;
use LiteAuth\util\ConfigManager;
use LiteAuth\util\MessageManager;
use LiteAuth\util\PasswordManager;
use LiteAuth\listener\AuthListener;
use LiteAuth\listener\QuitListener;
use LiteAuth\listener\MoveListener;
use LiteAuth\listener\InteractListener;
use LiteAuth\listener\DamageListener;
use LiteAuth\listener\CommandListener;
use LiteAuth\listener\DropListener;
use LiteAuth\listener\ChatListener;
use LiteAuth\command\RegisterCommand;
use LiteAuth\command\LoginCommand;
use LiteAuth\command\CaptchaCommand;
use LiteAuth\command\AuthCommand;

class LiteAuthPlugin extends PluginBase implements Listener {
    
    /** @var LiteAuthPlugin|null */
    private static $instance = null;
    
    /** @var AuthManager */
    private $authManager;
    
    /** @var StorageManager */
    private $storageManager;
    
    /** @var ConfigManager */
    private $configManager;
    
    /** @var MessageManager */
    private $messageManager;
    
    /** @var PasswordManager */
    private $passwordManager;
    
    public function onEnable() {
        self::$instance = $this;
        
        // Создание директорий
        @mkdir($this->getDataFolder() . "players");
        
        // Сохранение ресурсов если не существуют
        $this->saveResource("config.yml", false);
        $this->saveResource("messages.yml", false);
        
        // Инициализация менеджеров
        $this->configManager = new ConfigManager($this);
        $this->messageManager = new MessageManager($this);
        $this->passwordManager = new PasswordManager();
        $this->storageManager = new StorageManager($this);
        
        $this->authManager = new AuthManager(
            $this,
            $this->storageManager,
            $this->configManager,
            $this->messageManager,
            $this->passwordManager
        );
        
        // Регистрация слушателей событий
        $this->registerListeners();
        
        // Регистрация команд
        $this->registerCommands();
        
        $this->getLogger()->info("[LiteAuth] Плагин успешно загружен!");
        $this->getLogger()->info("[LiteAuth] Дизайн: жёлтый + белый + тёмно-серый");
        
        if ($this->configManager->isCaptchaEnabled()) {
            $this->getLogger()->info("[LiteAuth] Система капчи включена.");
        }
        
        if ($this->configManager->isAutoLoginEnabled()) {
            $this->getLogger()->info("[LiteAuth] Авто-логин включен.");
        }
    }
    
    private function registerListeners() {
        $pluginManager = $this->getServer()->getPluginManager();
        
        $pluginManager->registerEvents(new AuthListener($this->authManager, $this->messageManager), $this);
        $pluginManager->registerEvents(new QuitListener($this->authManager), $this);
        $pluginManager->registerEvents(new MoveListener($this->authManager, $this->configManager), $this);
        $pluginManager->registerEvents(new InteractListener($this->authManager, $this->configManager), $this);
        $pluginManager->registerEvents(new DamageListener($this->authManager, $this->configManager), $this);
        $pluginManager->registerEvents(new CommandListener($this->authManager, $this->configManager), $this);
        $pluginManager->registerEvents(new DropListener($this->authManager, $this->configManager), $this);
        $pluginManager->registerEvents(new ChatListener($this->authManager, $this->configManager), $this);
    }
    
    private function registerCommands() {
        $commandMap = $this->getServer()->getCommandMap();
        
        $commandMap->register("liteauth", new RegisterCommand($this->authManager, $this->messageManager));
        $commandMap->register("liteauth", new LoginCommand($this->authManager, $this->messageManager));
        $commandMap->register("liteauth", new CaptchaCommand($this->authManager, $this->messageManager, $this->configManager));
        $commandMap->register("liteauth", new AuthCommand($this->authManager, $this->messageManager, $this->configManager));
    }
    
    public function onDisable() {
        // Сохранение всех данных при выключении
        if ($this->authManager !== null) {
            $this->authManager->saveAllPlayers();
        }
        $this->getLogger()->info("[LiteAuth] Плагин выгружен.");
    }
    
    public static function getInstance() {
        return self::$instance;
    }
    
    public function getAuthManager() {
        return $this->authManager;
    }
    
    public function getStorageManager() {
        return $this->storageManager;
    }
    
    public function getConfigManager() {
        return $this->configManager;
    }
    
    public function getMessageManager() {
        return $this->messageManager;
    }
    
    public function getPasswordManager() {
        return $this->passwordManager;
    }
}
