<?php

declare(strict_types=1);

namespace ServerAuth;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\Server;

use ServerAuth\manager\AuthManager;
use ServerAuth\storage\StorageManager;
use ServerAuth\util\ConfigManager;
use ServerAuth\util\MessageManager;
use ServerAuth\util\PasswordManager;
use ServerAuth\listener\AuthListener;
use ServerAuth\listener\QuitListener;
use ServerAuth\listener\MoveListener;
use ServerAuth\listener\InteractListener;
use ServerAuth\listener\DamageListener;
use ServerAuth\listener\CommandListener;
use ServerAuth\listener\DropListener;
use ServerAuth\command\RegisterCommand;
use ServerAuth\command\LoginCommand;
use ServerAuth\command\ChangePasswordCommand;
use ServerAuth\command\AuthCommand;

class ServerAuthPlugin extends PluginBase implements Listener {
    
    private static ?ServerAuthPlugin $instance = null;
    
    private AuthManager $authManager;
    private StorageManager $storageManager;
    private ConfigManager $configManager;
    private MessageManager $messageManager;
    private PasswordManager $passwordManager;
    
    public function onEnable(): void {
        self::$instance = $this;
        
        // Создание директорий
        @mkdir($this->getDataFolder() . "players");
        
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
        
        $this->getLogger()->info("§6ServerAuth §fуспешно загружен!");
    }
    
    private function registerListeners(): void {
        $pluginManager = $this->getServer()->getPluginManager();
        
        $pluginManager->registerEvents(new AuthListener($this->authManager, $this->messageManager), $this);
        $pluginManager->registerEvents(new QuitListener($this->authManager), $this);
        $pluginManager->registerEvents(new MoveListener($this->authManager), $this);
        $pluginManager->registerEvents(new InteractListener($this->authManager), $this);
        // DamageListener регистрируется только если событие доступно в данной версии API
        if (class_exists('pocketmine\event\entity\EntityDamageEvent')) {
            $pluginManager->registerEvents(new DamageListener($this->authManager), $this);
        }
        $pluginManager->registerEvents(new CommandListener($this->authManager, $this->configManager), $this);
        $pluginManager->registerEvents(new DropListener($this->authManager), $this);
    }
    
    private function registerCommands(): void {
        $commandMap = $this->getServer()->getCommandMap();
        
        $commandMap->register("serverauth", new RegisterCommand($this->authManager, $this->messageManager));
        $commandMap->register("serverauth", new LoginCommand($this->authManager, $this->messageManager));
        $commandMap->register("serverauth", new ChangePasswordCommand($this->authManager, $this->messageManager));
        $commandMap->register("serverauth", new AuthCommand($this->authManager, $this->messageManager, $this->configManager));
    }
    
    public function onDisable(): void {
        // Сохранение всех данных при выключении
        $this->authManager->saveAllPlayers();
        $this->getLogger()->info("§6ServerAuth §fуспешно выгружен!");
    }
    
    public function onCommand(CommandSender $sender, Command $command, $label, array $args): bool {
        // Обработка команд делегируется Command классам
        return true;
    }
    
    public static function getInstance(): ?ServerAuthPlugin {
        return self::$instance;
    }
    
    public function getAuthManager(): AuthManager {
        return $this->authManager;
    }
    
    public function getStorageManager(): StorageManager {
        return $this->storageManager;
    }
    
    public function getConfigManager(): ConfigManager {
        return $this->configManager;
    }
    
    public function getMessageManager(): MessageManager {
        return $this->messageManager;
    }
    
    public function getPasswordManager(): PasswordManager {
        return $this->passwordManager;
    }
}
