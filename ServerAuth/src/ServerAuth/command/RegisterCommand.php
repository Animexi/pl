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
 * Команда /register
 */
class RegisterCommand extends Command implements PluginIdentifiableCommand {
    
    private AuthManager $authManager;
    private MessageManager $messageManager;
    
    public function __construct(AuthManager $authManager, MessageManager $messageManager) {
        parent::__construct("register", "Регистрация нового аккаунта", "/register <пароль> <повтор>", ["reg"]);
        $this->setPermission("serverauth.register");
        
        $this->authManager = $authManager;
        $this->messageManager = $messageManager;
    }
    
    public function execute(CommandSender $sender, $commandLabel, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("§cЭта команда доступна только игрокам.");
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
    
    public function getPlugin(): Plugin {
        return $this->authManager->getPlugin();
    }
}
