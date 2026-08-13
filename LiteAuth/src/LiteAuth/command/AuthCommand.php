<?php

declare(strict_types=1);

namespace LiteAuth\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use LiteAuth\LiteAuthPlugin;

class AuthCommand extends Command {

    private $plugin;

    public function __construct(LiteAuthPlugin $plugin) {
        parent::__construct("auth", "Authentication management", "/auth <info|unregister|changepassword|logout|reload>", []);
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (count($args) < 1) {
            $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §7Используйте: §e/auth <info|unregister|changepassword|logout|reload>");
            return true;
        }

        $subCmd = strtolower($args[0]);

        if ($subCmd === "reload") {
            if (!$sender->hasPermission("liteauth.reload") && !$sender->isOp()) {
                $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §cУ вас нет прав для этой команды.");
                return true;
            }

            if ($this->plugin->reloadConfig()) {
                $this->plugin->getMessageManager()->send($sender, "admin-reload-success");
            } else {
                $this->plugin->getMessageManager()->send($sender, "admin-reload-error");
            }
            return true;
        }

        if (!$sender instanceof Player) {
            if ($subCmd === "info" && isset($args[1])) {
                $targetName = $args[1];
                $this->showInfo($sender, $targetName);
                return true;
            }
            $sender->sendMessage("§cЭта команда доступна только игрокам.");
            return true;
        }

        if ($subCmd === "info") {
            if (!$sender->hasPermission("liteauth.info") && !$sender->isOp()) {
                $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §cУ вас нет прав для этой команды.");
                return true;
            }
            
            $targetName = isset($args[1]) ? $args[1] : $sender->getName();
            $this->showInfo($sender, $targetName);
            return true;
        }

        if ($subCmd === "unregister") {
            if (!$sender->hasPermission("liteauth.unregister") && !$sender->isOp()) {
                $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §cУ вас нет прав для этой команды.");
                return true;
            }

            if (!isset($args[1])) {
                $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §7Используйте: §e/auth unregister <player>");
                return true;
            }

            $targetName = $args[1];
            if ($this->plugin->getStorageManager()->deletePlayer($targetName)) {
                $this->plugin->getMessageManager()->send($sender, "admin-unregister-success", ["player" => $targetName]);
                $this->plugin->getLogger()->info("Account $targetName unregistered by " . $sender->getName());
            } else {
                $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §cАккаунт не найден.");
            }
            return true;
        }

        if ($subCmd === "logout") {
            if (!$sender->hasPermission("liteauth.admin") && !$sender->isOp()) {
                $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §cУ вас нет прав для этой команды.");
                return true;
            }

            if (!isset($args[1])) {
                $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §7Используйте: §e/auth logout <player>");
                return true;
            }

            $target = $this->plugin->getServer()->getPlayer($args[1]);
            if ($target instanceof Player) {
                $authManager = $this->plugin->getAuthManager();
                $authManager->clearSession($target);
                $authManager->setState($target, \LiteAuth\manager\AuthManager::STATE_AUTH_REQUIRED);
                $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §aИгрок §f" . $target->getName() . " §aвышел из аккаунта.");
            } else {
                $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §cИгрок не найден.");
            }
            return true;
        }

        $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §cНеизвестная подкоманда. Используйте §e/auth reload§c, §e/auth info§c, §e/auth unregister§c, или §e/auth logout§c.");
        return true;
    }

    private function showInfo(CommandSender $sender, string $playerName) {
        $storage = $this->plugin->getStorageManager();
        $authManager = $this->plugin->getAuthManager();
        
        $isRegistered = $storage->playerExists($playerName);
        
        $target = $this->plugin->getServer()->getPlayer($playerName);
        $isAuthenticated = false;
        $hasSession = false;
        $autoLogin = false;

        if ($target instanceof Player) {
            $isAuthenticated = $authManager->isAuthenticated($target);
            $hasSession = $authManager->hasValidSession($target);
            $autoLogin = $this->plugin->getConfigValue("auto-login", true);
        }

        $status = $isRegistered ? "§aЗарегистрирован" : "§cНе зарегистрирован";
        $sessionStr = $hasSession ? "§aАктивна" : "§7Нет";
        $autoLoginStr = $autoLogin ? "§aВключен" : "§7Отключен";

        $msg = $this->plugin->getMessageManager()->get("admin-info-title", [
            "player" => $playerName,
            "status" => $status,
            "session" => $sessionStr,
            "autologin" => $autoLoginStr
        ]);

        $sender->sendMessage($msg);
    }
}
