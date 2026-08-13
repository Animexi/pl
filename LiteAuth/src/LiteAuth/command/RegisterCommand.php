<?php

namespace LiteAuth\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use LiteAuth\LiteAuthPlugin;
use LiteAuth\manager\AuthManager;

class RegisterCommand extends Command {
    
    private $plugin;
    private $authManager;
    
    public function __construct(LiteAuthPlugin $plugin, AuthManager $authManager) {
        parent::__construct("register", "Register a new account", "/register <password> <password>", ["reg"]);
        $this->setPermission("liteauth.register");
        $this->plugin = $plugin;
        $this->authManager = $authManager;
    }
    
    public function execute(CommandSender $sender, $label, array $args) {
        if (!$sender instanceof Player) {
            $sender->sendMessage(TextFormat::RED . "Эту команду можно использовать только в игре.");
            return true;
        }
        
        if ($this->authManager->isRegistered($sender)) {
            $sender->sendMessage($this->authManager->formatMessage("already-registered"));
            return true;
        }
        
        if (count($args) < 2) {
            $sender->sendMessage($this->authManager->formatBoxedMessage([
                "§e§lLITEAUTH",
                "",
                "§cНеверный формат команды.",
                "",
                "§7Используйте:",
                "§e/register §f<пароль> <пароль>",
                ""
            ]));
            return true;
        }
        
        $password = $args[0];
        $confirmPassword = $args[1];
        
        $this->authManager->register($sender, $password, $confirmPassword);
        return true;
    }
}
