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

/**
 * Команда /login
 */
class LoginCommand extends Command implements PluginIdentifiableCommand {
    
    private AuthManager $authManager;
    private MessageManager $messageManager;
    
    public function __construct(AuthManager $authManager, MessageManager $messageManager) {
        parent::__construct("login", "Вход в аккаунт", "/login <пароль>", ["l", "log"]);
        $this->setPermission("serverauth.login");
        
        $this->authManager = $authManager;
        $this->messageManager = $messageManager;
    }
    
    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("§cЭта команда доступна только игрокам.");
            return true;
        }
        
        // Проверка количества аргументов
        if (count($args) < 1) {
            $this->messageManager->send($sender, "login.usage");
            return true;
        }
        
        $password = $args[0];
        
        // Авторизация
        $this->authManager->login($sender, $password);
        
        return true;
    }
    
    public function getPlugin(): Plugin {
        return $this->authManager->getPlugin();
    }
}
