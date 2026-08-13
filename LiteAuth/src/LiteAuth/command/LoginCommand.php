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
 * Команда /login
 */
class LoginCommand extends Command implements PluginIdentifiableCommand {
    
    /** @var AuthManager */
    private $authManager;
    
    /** @var MessageManager */
    private $messageManager;
    
    public function __construct(AuthManager $authManager, MessageManager $messageManager) {
        parent::__construct("login", "Авторизация на сервере", "/login <пароль>", array("l"));
        $this->setPermission("liteauth.login");
        
        $this->authManager = $authManager;
        $this->messageManager = $messageManager;
    }
    
    public function execute(CommandSender $sender, string $commandLabel, array $args) : bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §cЭта команда доступна только игрокам.");
            return true;
        }
        
        // Проверка количества аргументов
        if (count($args) < 1) {
            $this->messageManager->send($sender, "login.usage");
            return true;
        }
        
        $password = implode(" ", $args);
        
        // Авторизация
        $this->authManager->login($sender, $password);
        
        return true;
    }
    
    public function getPlugin() {
        return $this->authManager->getPlugin();
    }
}
