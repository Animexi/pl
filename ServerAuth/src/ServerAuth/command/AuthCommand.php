<?php

declare(strict_types=1);

namespace ServerAuth\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\plugin\Plugin;

use ServerAuth\manager\AuthManager;
use ServerAuth\util\MessageManager;
use ServerAuth\util\ConfigManager;

/**
 * Административная команда /auth
 */
class AuthCommand extends Command implements PluginIdentifiableCommand {
    
    private AuthManager $authManager;
    private MessageManager $messageManager;
    private ConfigManager $configManager;
    
    public function __construct(AuthManager $authManager, MessageManager $messageManager, ConfigManager $configManager) {
        parent::__construct("auth", "Административные команды ServerAuth", "/auth <reload|unregister|info>", ["serverauth"]);
        $this->setPermission("serverauth.admin");
        
        $this->authManager = $authManager;
        $this->messageManager = $messageManager;
        $this->configManager = $configManager;
    }
    
    public function execute(CommandSender $sender, $commandLabel, array $args): bool {
        // Проверка прав
        if (!$sender->hasPermission("serverauth.admin")) {
            $this->messageManager->send($sender, "admin.no_permission");
            return true;
        }
        
        if (count($args) < 1) {
            $sender->sendMessage("§8[§6Server§8] §fИспользование:");
            $sender->sendMessage("§6/auth reload §7- Перезагрузить конфигурацию");
            $sender->sendMessage("§6/auth unregister <игрок> §7- Удалить аккаунт");
            $sender->sendMessage("§6/auth info <игрок> §7- Информация об аккаунте");
            return true;
        }
        
        $subCommand = strtolower($args[0]);
        
        switch ($subCommand) {
            case "reload":
                return $this->executeReload($sender);
                
            case "unregister":
                if (!isset($args[1])) {
                    $sender->sendMessage("§8[§6Server§8] §fИспользование: §6/auth unregister <игрок>");
                    return true;
                }
                return $this->executeUnregister($sender, $args[1]);
                
            case "info":
                if (!isset($args[1])) {
                    $sender->sendMessage("§8[§6Server§8] §fИспользование: §6/auth info <игрок>");
                    return true;
                }
                return $this->executeInfo($sender, $args[1]);
                
            default:
                $sender->sendMessage("§8[§6Server§8] §cНеизвестная подкоманда. Используйте /auth для помощи.");
                return true;
        }
    }
    
    private function executeReload(CommandSender $sender): bool {
        try {
            $this->configManager->reload();
            $this->messageManager->reload();
            
            $this->messageManager->send($sender, "admin.reload_success");
            return true;
            
        } catch (\Exception $e) {
            $sender->sendMessage("§8[§6Server§8] §cОшибка при перезагрузке: " . $e->getMessage());
            return false;
        }
    }
    
    private function executeUnregister(CommandSender $sender, string $username): bool {
        // Проверка специальных прав
        if (!$sender->hasPermission("serverauth.unregister")) {
            $this->messageManager->send($sender, "admin.no_permission");
            return true;
        }
        
        if ($this->authManager->unregisterAccount($username)) {
            $this->messageManager->send($sender, "admin.unregister_success", ["PLAYER" => $username]);
            return true;
        }
        
        $this->messageManager->send($sender, "admin.unregister_not_found");
        return true;
    }
    
    private function executeInfo(CommandSender $sender, string $username): bool {
        // Проверка специальных прав
        if (!$sender->hasPermission("serverauth.info")) {
            $this->messageManager->send($sender, "admin.no_permission");
            return true;
        }
        
        $info = $this->authManager->getAccountInfo($username);
        
        if ($info === null) {
            $this->messageManager->send($sender, "admin.unregister_not_found");
            return true;
        }
        
        $sender->sendMessage("§8§m--------------------------------");
        $this->messageManager->send($sender, "admin.info_header", ["PLAYER" => $info["username"]]);
        $sender->sendMessage("§8§m--------------------------------");
        
        $status = $info["is_locked"] ? "§cЗаблокирован" : "§aАктивен";
        $this->messageManager->send($sender, "admin.info_registered", ["STATUS" => $status]);
        
        $this->messageManager->send($sender, "admin.info_last_login", ["LAST_LOGIN" => $info["last_login"]]);
        
        $sender->sendMessage("§7UUID: §6" . $info["uuid"]);
        $sender->sendMessage("§7Дата регистрации: §6" . $info["registered_at"]);
        $sender->sendMessage("§7Неудачных попыток: §6" . $info["failed_attempts"]);
        
        return true;
    }
    
    public function getPlugin(): Plugin {
        return $this->authManager->getPlugin();
    }
}
