<?php

namespace LiteAuth\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\command\PluginIdentifiableCommand;

use LiteAuth\manager\AuthManager;
use LiteAuth\util\MessageManager;
use LiteAuth\util\ConfigManager;

/**
 * Команда /auth (административная и информационная)
 */
class AuthCommand extends Command implements PluginIdentifiableCommand {
    
    /** @var AuthManager */
    private $authManager;
    
    /** @var MessageManager */
    private $messageManager;
    
    /** @var ConfigManager */
    private $configManager;
    
    public function __construct(AuthManager $authManager, MessageManager $messageManager, ConfigManager $configManager) {
        parent::__construct("auth", "Управление авторизацией", "/auth <info|unregister|changepassword|logout|reload|captcha|session>", array());
        $this->setPermission("liteauth.admin");
        
        $this->authManager = $authManager;
        $this->messageManager = $messageManager;
        $this->configManager = $configManager;
    }
    
    public function execute(CommandSender $sender, string $commandLabel, array $args) : bool {
        // Если нет аргументов - показать статус текущего игрока
        if (count($args) < 1) {
            if (!$sender instanceof Player) {
                $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §cИспользуйте /auth <info|unregister|changepassword|logout|reload|captcha|session>");
                return true;
            }
            
            $this->authManager->showAuthStatus($sender);
            return true;
        }
        
        $subCommand = strtolower($args[0]);
        
        // Обработка подкоманд
        switch ($subCommand) {
            case "info":
                if (!$sender->hasPermission("liteauth.info")) {
                    $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §cНедостаточно прав.");
                    return true;
                }
                
                if (!isset($args[1])) {
                    $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §cИспользуйте /auth info <игрок>");
                    return true;
                }
                
                $targetName = $args[1];
                $this->authManager->showPlayerInfo($sender, $targetName);
                break;
                
            case "unregister":
                if (!$sender->hasPermission("liteauth.unregister")) {
                    $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §cНедостаточно прав.");
                    return true;
                }
                
                if (!isset($args[1])) {
                    $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §cИспользуйте /auth unregister <игрок>");
                    return true;
                }
                
                $targetName = $args[1];
                $this->authManager->unregisterPlayer($sender, $targetName);
                break;
                
            case "changepassword":
                if (!$sender->hasPermission("liteauth.changepassword")) {
                    $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §cНедостаточно прав.");
                    return true;
                }
                
                if (!isset($args[1]) || !isset($args[2])) {
                    $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §cИспользуйте /auth changepassword <игрок> <новый-пароль>");
                    return true;
                }
                
                $targetName = $args[1];
                $newPassword = $args[2];
                $this->authManager->changePassword($sender, $targetName, $newPassword);
                break;
                
            case "logout":
                if (!$sender->hasPermission("liteauth.logout")) {
                    $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §cНедостаточно прав.");
                    return true;
                }
                
                if (!isset($args[1])) {
                    $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §cИспользуйте /auth logout <игрок>");
                    return true;
                }
                
                $targetName = $args[1];
                $this->authManager->logoutPlayer($sender, $targetName);
                break;
                
            case "reload":
                if (!$sender->hasPermission("liteauth.reload")) {
                    $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §cНедостаточно прав.");
                    return true;
                }
                
                $this->authManager->reloadConfig($sender);
                break;
                
            case "captcha":
                if (!$sender->hasPermission("liteauth.admin")) {
                    $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §cНедостаточно прав.");
                    return true;
                }
                
                if (!isset($args[1])) {
                    $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §cИспользуйте /auth captcha <игрок>");
                    return true;
                }
                
                $targetName = $args[1];
                $this->authManager->forceCaptcha($sender, $targetName);
                break;
                
            case "session":
                if (!$sender->hasPermission("liteauth.admin")) {
                    $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §cНедостаточно прав.");
                    return true;
                }
                
                if (!isset($args[1])) {
                    $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §cИспользуйте /auth session <игрок>");
                    return true;
                }
                
                $targetName = $args[1];
                $this->authManager->showSessionInfo($sender, $targetName);
                break;
                
            default:
                $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §cНеизвестная подкоманда. Используйте /auth для просмотра статуса.");
                break;
        }
        
        return true;
    }
    
    public function getPlugin() {
        return $this->authManager->getPlugin();
    }
}
