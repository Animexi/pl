<?php

declare(strict_types=1);

namespace LiteAuth\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use LiteAuth\LiteAuthPlugin;

class LoginCommand extends Command {

    /** @var LiteAuthPlugin */
    private $plugin;

    public function __construct(LiteAuthPlugin $plugin) {
        parent::__construct("login", "Login to your account", "/login <password>", ["l"]);
        $this->setPermission("liteauth.login");
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("§cЭта команда доступна только игрокам.");
            return true;
        }

        $player = $sender;
        $msg = $this->plugin->getMessageManager();

        // Проверка на уже авторизованных
        if ($this->plugin->getAuthManager()->isAuthenticated($player)) {
            $msg->send($player, "already-authenticated");
            return true;
        }

        // Проверка аргументов
        if (count($args) < 1) {
            $msg->send($player, "command-usage", ["usage" => "§e/login <пароль>"]);
            return true;
        }

        $password = implode(' ', $args);
        
        // Проверка регистрации
        if (!$this->plugin->getStorageManager()->isRegistered($player->getName())) {
            $msg->send($player, "login-not-registered");
            return true;
        }

        // Выполняем вход
        $this->plugin->getAuthManager()->login($player, $password);
        
        return true;
    }
}
