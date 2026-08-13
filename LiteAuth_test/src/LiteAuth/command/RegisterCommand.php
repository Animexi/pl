<?php

namespace LiteAuth\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\plugin\Plugin;

use LiteAuth\manager\AuthManager;
use LiteAuth\util\MessageManager;

/**
 * Команда /register
 */
class RegisterCommand extends Command implements PluginIdentifiableCommand {
    
    /** @var AuthManager */
    private $authManager;
    
    /** @var MessageManager */
    private $messageManager;
    
    public function __construct(AuthManager $authManager, MessageManager $messageManager) {
        parent::__construct("register", "Регистрация нового аккаунта", "/register <пароль> <пароль>", array("reg"));
        $this->setPermission("liteauth.register");
        
        $this->authManager = $authManager;
        $this->messageManager = $messageManager;
    }
    
    public function execute(CommandSender $sender, $commandLabel, array $args) {
        if (!$sender instanceof Player) {
            $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §cЭта команда доступна только игрокам.");
            return true;
        }
        
        // Проверка количества аргументов
        if (count($args) < 2) {
            $this->messageManager->send($sender, "register.usage");
            return true;
        }
        
        $password = $args[0];
        $confirmPassword = $args[1];
        
        // Регистрация
        $this->authManager->register($sender, $password, $confirmPassword);
        
        return true;
    }
    
    public function getPlugin() {
        return $this->authManager->getPlugin();
    }
}
