<?php

declare(strict_types=1);

namespace LiteAuth\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use LiteAuth\LiteAuthPlugin;

class RegisterCommand extends Command {

    /** @var LiteAuthPlugin */
    private $plugin;

    public function __construct(LiteAuthPlugin $plugin) {
        parent::__construct("register", "Register a new account", "/register <password> <password>", ["reg"]);
        $this->setPermission("liteauth.register");
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("§cЭта команда доступна только игрокам.");
            return true;
        }

        $player = $sender;
        $msg = $this->plugin->getMessageManager();

        // Проверка на уже зарегистрированных
        if ($this->plugin->getStorageManager()->isRegistered($player->getName())) {
            $msg->send($player, "register-already-exists");
            return true;
        }

        // Проверка аргументов
        if (count($args) < 2) {
            $msg->send($player, "command-usage", ["usage" => "§e/register <пароль> <пароль>"]);
            return true;
        }

        $password = $args[0];
        $confirmPassword = $args[1];

        // Выполняем регистрацию
        $this->plugin->getAuthManager()->register($player, $password, $confirmPassword);
        
        return true;
    }
}
