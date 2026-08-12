<?php

declare(strict_types=1);

namespace ServerAuth;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\Player;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use ServerAuth\manager\AuthManager;
use ServerAuth\manager\MessageManager;
use ServerAuth\storage\StorageManager;
use ServerAuth\listener\JoinListener;
use ServerAuth\listener\QuitListener;
use ServerAuth\listener\MoveListener;
use ServerAuth\listener\InteractListener;
use ServerAuth\listener\DamageListener;
use ServerAuth\listener\DropListener;
use ServerAuth\listener\CommandListener;
use ServerAuth\task\AutoSaveTask;

class ServerAuthPlugin extends PluginBase implements Listener {
    
    private static ?ServerAuthPlugin $instance = null;
    private AuthManager $authManager;
    private MessageManager $messageManager;
    private StorageManager $storageManager;
    private Config $config;
    private Config $messagesConfig;
    
    public function onLoad(): void {
        self::$instance = $this;
        $this->saveResource("config.yml");
        $this->saveResource("messages.yml");
    }
    
    public function onEnable(): void {
        $this->getLogger()->info("§6Загрузка ServerAuth v" . $this->getDescription()->getVersion());
        
        // Загрузка конфигурации
        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $this->messagesConfig = new Config($this->getDataFolder() . "messages.yml", Config::YAML);
        
        // Инициализация менеджеров
        $this->storageManager = new StorageManager($this);
        $this->messageManager = new MessageManager($this, $this->messagesConfig);
        $this->authManager = new AuthManager($this, $this->storageManager, $this->messageManager);
        
        // Регистрация слушателей
        $this->registerListeners();
        
        // Регистрация команд
        $this->registerCommands();
        
        // Запуск задачи автосохранения
        $autoSaveInterval = $this->getConfig()->getNested("storage.auto-save-interval", 60);
        if ($autoSaveInterval > 0) {
            $this->getScheduler()->scheduleRepeatingTask(new AutoSaveTask($this), $autoSaveInterval * 20);
        }
        
        $this->getLogger()->info("§aServerAuth успешно запущен!");
    }
    
    private function registerListeners(): void {
        $pm = $this->getServer()->getPluginManager();
        $pm->registerEvents(new JoinListener($this, $this->authManager, $this->messageManager), $this);
        $pm->registerEvents(new QuitListener($this, $this->authManager), $this);
        $pm->registerEvents(new MoveListener($this, $this->authManager, $this->messageManager), $this);
        $pm->registerEvents(new InteractListener($this, $this->authManager, $this->messageManager), $this);
        $pm->registerEvents(new DamageListener($this, $this->authManager, $this->messageManager), $this);
        $pm->registerEvents(new DropListener($this, $this->authManager, $this->messageManager), $this);
        $pm->registerEvents(new CommandListener($this, $this->authManager, $this->messageManager), $this);
    }
    
    private function registerCommands(): void {
        // Команды регистрируются через plugin.yml
    }
    
    public function onCommand(CommandSender $sender, Command $command, $label, array $args): bool {
        $cmdName = strtolower($command->getName());
        
        switch ($cmdName) {
            case "register":
                return $this->authManager->handleRegister($sender, $args);
            case "login":
                return $this->authManager->handleLogin($sender, $args);
            case "changepassword":
                return $this->authManager->handleChangePassword($sender, $args);
            case "auth":
                return $this->authManager->handleAdminCommand($sender, $args);
            default:
                return false;
        }
    }
    
    public function onDisable(): void {
        // Сохранение всех данных при выключении
        $this->authManager->saveAllPlayers();
        $this->getLogger()->info("§eServerAuth выключен. Все данные сохранены.");
    }
    
    public static function getInstance(): ?ServerAuthPlugin {
        return self::$instance;
    }
    
    public function getConfig(): Config {
        return $this->config;
    }
    
    public function getMessagesConfig(): Config {
        return $this->messagesConfig;
    }
    
    public function getAuthManager(): AuthManager {
        return $this->authManager;
    }
    
    public function getMessageManager(): MessageManager {
        return $this->messageManager;
    }
    
    public function getStorageManager(): StorageManager {
        return $this->storageManager;
    }
}
