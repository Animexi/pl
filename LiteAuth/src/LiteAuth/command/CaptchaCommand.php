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
 * Команда /captcha
 */
class CaptchaCommand extends Command implements PluginIdentifiableCommand {
    
    /** @var AuthManager */
    private $authManager;
    
    /** @var MessageManager */
    private $messageManager;
    
    /** @var ConfigManager */
    private $configManager;
    
    public function __construct(AuthManager $authManager, MessageManager $messageManager, ConfigManager $configManager) {
        parent::__construct("captcha", "Подтверждение капчи", "/captcha <ответ>", array());
        $this->setPermission("liteauth.captcha");
        
        $this->authManager = $authManager;
        $this->messageManager = $messageManager;
        $this->configManager = $configManager;
    }
    
    public function execute(CommandSender $sender, string $commandLabel, array $args) : bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §cЭта команда доступна только игрокам.");
            return true;
        }
        
        // Если нет аргументов - показать новую капчу
        if (count($args) < 1) {
            $this->authManager->showCaptcha($sender);
            return true;
        }
        
        $answer = intval($args[0]);
        
        // Проверка капчи
        $this->authManager->checkCaptcha($sender, $answer);
        
        return true;
    }
    
    public function getPlugin() {
        return $this->authManager->getPlugin();
    }
}
