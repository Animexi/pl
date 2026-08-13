<?php

declare(strict_types=1);

namespace LiteAuth\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use LiteAuth\LiteAuthPlugin;

class LoginCommand extends Command {

    private $plugin;

    public function __construct(LiteAuthPlugin $plugin) {
        parent::__construct("login", "Login to your account", "/login <password>", ["l"]);
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("§cЭта команда доступна только игрокам.");
            return true;
        }

        if (count($args) < 1) {
            $this->plugin->getMessageManager()->sendInvalidCommand($sender, "/login <пароль>");
            return true;
        }

        $authManager = $this->plugin->getAuthManager();
        
        if (!$authManager->isRegistered($sender)) {
            $this->plugin->getMessageManager()->sendNotRegistered($sender);
            return true;
        }

        if ($authManager->isAuthenticated($sender)) {
            $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §cВы уже авторизованы.");
            return true;
        }

        $password = implode(" ", $args);
        
        if ($authManager->login($sender, $password)) {
            $this->plugin->getMessageManager()->sendLoginSuccess($sender);
        } else {
            $authManager->incrementLoginAttempts($sender);
            $attempts = $authManager->getLoginAttempts($sender);
            $maxAttempts = $this->plugin->getConfigValue("max-login-attempts", 5);
            
            if ($attempts >= $maxAttempts) {
                $this->plugin->getMessageManager()->sendTooManyAttempts($sender);
                $sender->kick("Слишком много неудачных попыток входа.", false);
            } else {
                $this->plugin->getMessageManager()->sendWrongPassword($sender);
            }
        }

        return true;
    }
}
