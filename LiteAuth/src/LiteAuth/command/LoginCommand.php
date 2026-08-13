<?php

namespace LiteAuth\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use LiteAuth\LiteAuthPlugin;
use LiteAuth\manager\AuthManager;

class LoginCommand extends Command {
    
    private $plugin;
    private $authManager;
    
    public function __construct(LiteAuthPlugin $plugin, AuthManager $authManager) {
        parent::__construct("login", "Login to your account", "/login <password>", ["l"]);
        $this->setPermission("liteauth.login");
        $this->plugin = $plugin;
        $this->authManager = $authManager;
    }
    
    public function execute(CommandSender $sender, $label, array $args) {
        if (!$sender instanceof Player) {
            $sender->sendMessage(TextFormat::RED . "Эту команду можно использовать только в игре.");
            return true;
        }
        
        if (!$this->authManager->isRegistered($sender)) {
            $sender->sendMessage($this->authManager->formatMessage("not-registered"));
            return true;
        }
        
        if (count($args) < 1) {
            $sender->sendMessage($this->authManager->formatBoxedMessage([
                "§e§lLITEAUTH",
                "",
                "§cНеверный формат команды.",
                "",
                "§7Используйте:",
                "§e/login §f<пароль>",
                ""
            ]));
            return true;
        }
        
        $password = $args[0];
        $this->authManager->login($sender, $password);
        return true;
    }
}
