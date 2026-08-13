<?php

namespace LiteAuth\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use LiteAuth\LiteAuthPlugin;
use LiteAuth\manager\AuthManager;

class CaptchaCommand extends Command {
    
    private $plugin;
    private $authManager;
    
    public function __construct(LiteAuthPlugin $plugin, AuthManager $authManager) {
        parent::__construct("captcha", "Solve captcha challenge", "/captcha <answer>");
        $this->setPermission("liteauth.captcha");
        $this->plugin = $plugin;
        $this->authManager = $authManager;
    }
    
    public function execute(CommandSender $sender, $label, array $args) {
        if (!$sender instanceof Player) {
            $sender->sendMessage(TextFormat::RED . "Эту команду можно использовать только в игре.");
            return true;
        }
        
        if (!$this->authManager->needsCaptcha($sender)) {
            $sender->sendMessage($this->authManager->formatMessage("no-captcha-active"));
            return true;
        }
        
        if (count($args) < 1) {
            $sender->sendMessage($this->authManager->formatBoxedMessage([
                "§e§lLITEAUTH",
                "",
                "§cНеверный формат команды.",
                "",
                "§7Используйте:",
                "§e/captcha §f<число>",
                ""
            ]));
            return true;
        }
        
        $answer = $args[0];
        $this->authManager->checkCaptcha($sender, $answer);
        return true;
    }
}
