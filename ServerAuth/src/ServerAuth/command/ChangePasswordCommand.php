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
 * Команда /changepassword
 */
class ChangePasswordCommand extends Command implements PluginIdentifiableCommand {
    
    private AuthManager $authManager;
    private MessageManager $messageManager;
    
    public function __construct(AuthManager $authManager, MessageManager $messageManager) {
        parent::__construct("changepassword", "Смена пароля", "/changepassword <старый> <новый>", ["cp"]);
        $this->setPermission("serverauth.changepassword");
        
        $this->authManager = $authManager;
        $this->messageManager = $messageManager;
    }
    
    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("§cЭта команда доступна только игрокам.");
            return true;
        }
        
        // Проверка количества аргументов
        if (count($args) < 2) {
            $this->messageManager->send($sender, "changepassword.usage");
            return true;
        }
        
        $oldPassword = $args[0];
        $newPassword = $args[1];
        
        // Смена пароля
        $this->authManager->changePassword($sender, $oldPassword, $newPassword);
        
        return true;
    }
    
    public function getPlugin(): Plugin {
        return $this->authManager->getPlugin();
    }
}
