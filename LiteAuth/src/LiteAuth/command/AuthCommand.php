<?php

namespace LiteAuth\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use LiteAuth\LiteAuthPlugin;
use LiteAuth\manager\AuthManager;

class AuthCommand extends Command {
    
    private $plugin;
    private $authManager;
    
    public function __construct(LiteAuthPlugin $plugin, AuthManager $authManager) {
        parent::__construct("auth", "Administration commands", "/auth <subcommand>", []);
        $this->setPermission("liteauth.admin");
        $this->plugin = $plugin;
        $this->authManager = $authManager;
    }
    
    public function execute(CommandSender $sender, $label, array $args) {
        if (count($args) < 1) {
            $sender->sendMessage($this->authManager->formatBoxedMessage([
                "§e§lLITEAUTH",
                "",
                "§cНеверный формат команды.",
                "",
                "§7Доступные подкоманды:",
                "§e/auth info §f<player>",
                "§e/auth unregister §f<player>",
                "§e/auth changepassword §f<player> <password>",
                "§e/auth logout §f<player>",
                "§e/auth reload",
                ""
            ]));
            return true;
        }
        
        $subCommand = strtolower($args[0]);
        
        switch ($subCommand) {
            case "info":
                return $this->handleInfo($sender, $args);
            case "unregister":
                return $this->handleUnregister($sender, $args);
            case "changepassword":
                return $this->handleChangePassword($sender, $args);
            case "logout":
                return $this->handleLogout($sender, $args);
            case "reload":
                return $this->handleReload($sender);
            default:
                $sender->sendMessage($this->authManager->formatMessage("unknown-subcommand"));
                return true;
        }
    }
    
    private function handleInfo(CommandSender $sender, array $args) {
        if (count($args) < 2) {
            $sender->sendMessage("§e§lLITEAUTH §8┃ §cИспользование: /auth info <player>");
            return true;
        }
        
        $targetName = strtolower($args[1]);
        $isRegistered = $this->authManager->getPlugin()->getAuthManager()->isRegistered($targetName);
        
        if (!$isRegistered) {
            $sender->sendMessage("§e§lLITEAUTH §8┃ §7Игрок §f" . $args[1] . " §7не зарегистрирован.");
            return true;
        }
        
        $storage = $this->authManager->getPlugin()->getAuthManager()->getStorage();
        $account = $storage->get($targetName);
        
        $registeredDate = isset($account["registered"]) ? date("d.m.Y H:i", $account["registered"]) : "N/A";
        $lastLogin = isset($account["lastlogin"]) ? date("d.m.Y H:i", $account["lastlogin"]) : "N/A";
        
        $sender->sendMessage($this->authManager->formatBoxedMessage([
            "§e§lLITEAUTH",
            "",
            "§7Информация об аккаунте:",
            "§f" . $args[1],
            "",
            "§7Зарегистрирован: §a" . $registeredDate,
            "§7Последний вход: §e" . $lastLogin,
            ""
        ]));
        
        return true;
    }
    
    private function handleUnregister(CommandSender $sender, array $args) {
        if (count($args) < 2) {
            $sender->sendMessage("§e§lLITEAUTH §8┃ §cИспользование: /auth unregister <player>");
            return true;
        }
        
        $targetName = strtolower($args[1]);
        $storage = $this->authManager->getPlugin()->getAuthManager()->getStorage();
        
        if (!$storage->exists($targetName)) {
            $sender->sendMessage("§e§lLITEAUTH §8┃ §7Игрок §f" . $args[1] . " §7не зарегистрирован.");
            return true;
        }
        
        $storage->delete($targetName);
        $this->authManager->log("Account unregistered by admin: " . $args[1] . " by " . ($sender instanceof Player ? $sender->getName() : "Console"));
        $sender->sendMessage("§e§lLITEAUTH §8┃ §aАккаунт игрока §f" . $args[1] . " §aудалён.");
        
        return true;
    }
    
    private function handleChangePassword(CommandSender $sender, array $args) {
        if (count($args) < 3) {
            $sender->sendMessage("§e§lLITEAUTH §8┃ §cИспользование: /auth changepassword <player> <password>");
            return true;
        }
        
        $targetName = strtolower($args[1]);
        $newPassword = $args[2];
        $storage = $this->authManager->getPlugin()->getAuthManager()->getStorage();
        
        if (!$storage->exists($targetName)) {
            $sender->sendMessage("§e§lLITEAUTH §8┃ §7Игрок §f" . $args[1] . " §7не зарегистрирован.");
            return true;
        }
        
        $account = $storage->get($targetName);
        $account["password"] = password_hash($newPassword, PASSWORD_DEFAULT);
        $storage->save($targetName, $account);
        
        $this->authManager->log("Password changed by admin: " . $args[1] . " by " . ($sender instanceof Player ? $sender->getName() : "Console"));
        $sender->sendMessage("§e§lLITEAUTH §8┃ §aПароль изменён для §f" . $args[1] . "§a.");
        
        return true;
    }
    
    private function handleLogout(CommandSender $sender, array $args) {
        if (count($args) < 2) {
            $sender->sendMessage("§e§lLITEAUTH §8┃ §cИспользование: /auth logout <player>");
            return true;
        }
        
        $targetName = strtolower($args[1]);
        $server = $this->authManager->getPlugin()->getServer();
        $target = $server->getPlayer($args[1]);
        
        if ($target instanceof Player) {
            $this->authManager->setState($target, \LiteAuth\manager\AuthManager::STATE_AUTH_REQUIRED);
            $target->sendMessage("§e§lLITEAUTH §8┃ §7Вы были вынуждены выйти из аккаунта администратором.");
            $this->authManager->showLoginMessage($target);
            $sender->sendMessage("§e§lLITEAUTH §8┃ §aИгрок §f" . $args[1] . " §aвышел из аккаунта.");
        } else {
            $sender->sendMessage("§e§lLITEAUTH §8┃ §7Игрок §f" . $args[1] . " §7не в сети.");
        }
        
        return true;
    }
    
    private function handleReload(CommandSender $sender) {
        $this->authManager->getPlugin()->reloadConfig();
        $sender->sendMessage("§e§lLITEAUTH §8┃ §aКонфигурация успешно перезагружена.");
        $this->authManager->log("Configuration reloaded by " . ($sender instanceof Player ? $sender->getName() : "Console"));
        return true;
    }
}
