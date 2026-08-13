<?php

declare(strict_types=1);

namespace LiteAuth\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use LiteAuth\LiteAuthPlugin;

class AuthCommand extends Command {

    /** @var LiteAuthPlugin */
    private $plugin;

    public function __construct(LiteAuthPlugin $plugin) {
        parent::__construct("auth", "Authentication management commands", "/auth <info|unregister|changepassword|logout|reload>", []);
        $this->setPermission("liteauth.admin");
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        $msg = $this->plugin->getMessageManager();

        // Проверка прав
        if (!$sender->hasPermission("liteauth.admin")) {
            $msg->sendPrefix($sender, "§cУ вас нет прав для этой команды.");
            return true;
        }

        if (count($args) < 1) {
            $sender->sendMessage("§e§lLITEAUTH §8┃ §7Используйте: §e/auth <info|unregister|changepassword|logout|reload>");
            return true;
        }

        $subCmd = strtolower($args[0]);

        switch ($subCmd) {
            case "reload":
                try {
                    $this->plugin->reloadConfig();
                    $msg->sendPrefix($sender, "§aКонфигурация успешно перезагружена.");
                } catch (\Exception $e) {
                    $msg->sendPrefix($sender, "§cНе удалось загрузить конфигурацию.");
                    $this->plugin->getLogger()->error("Reload failed: " . $e->getMessage());
                }
                break;

            case "info":
                if (!isset($args[1])) {
                    $msg->sendPrefix($sender, "§cИспользуйте: /auth info <player>");
                    return true;
                }
                
                $targetName = $args[1];
                $isRegistered = $this->plugin->getStorageManager()->isRegistered($targetName);
                
                $status = $isRegistered ? "§aЗарегистрирован" : "§cНе зарегистрирован";
                $regDate = $isRegistered ? $this->plugin->getStorageManager()->getRegistrationDate($targetName) : null;
                $lastLogin = $isRegistered ? date("d.m.Y H:i", $this->plugin->getStorageManager()->loadPlayerData($targetName)["last_login"] ?? 0) : "N/A";
                
                $regDateStr = $regDate ? date("d.m.Y H:i", $regDate) : "N/A";
                
                $message = str_replace(
                    ["{player}", "{status}", "{registered}", "{regdate}", "{lastlogin}"],
                    [$targetName, $status, $status, $regDateStr, $lastLogin],
                    $msg->get("admin-info-header")
                );
                $sender->sendMessage($message);
                break;

            case "unregister":
                if (!isset($args[1])) {
                    $msg->sendPrefix($sender, "§cИспользуйте: /auth unregister <player>");
                    return true;
                }
                
                $targetName = $args[1];
                if ($this->plugin->getStorageManager()->deleteAccount($targetName)) {
                    $this->plugin->getAuthManager()->clearPlayerData($targetName);
                    $msg->send($sender, "admin-unregister-success", ["player" => $targetName]);
                } else {
                    $msg->sendPrefix($sender, "§cНе удалось удалить аккаунт.");
                }
                break;

            case "changepassword":
                if (!isset($args[1]) || !isset($args[2])) {
                    $msg->sendPrefix($sender, "§cИспользуйте: /auth changepassword <player> <newpassword>");
                    return true;
                }
                
                $targetName = $args[1];
                $newPassword = $args[2];
                
                if (!$this->plugin->getStorageManager()->isRegistered($targetName)) {
                    $msg->sendPrefix($sender, "§cИгрок не зарегистрирован.");
                    return true;
                }
                
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $data = $this->plugin->getStorageManager()->loadPlayerData($targetName);
                if ($data !== null) {
                    $data["password"] = $hash;
                    $this->plugin->getStorageManager()->savePlayerData($targetName, $data);
                    $msg->send($sender, "admin-changepassword-success", ["player" => $targetName]);
                }
                break;

            case "logout":
                if (!isset($args[1])) {
                    $msg->sendPrefix($sender, "§cИспользуйте: /auth logout <player>");
                    return true;
                }
                
                $targetName = $args[1];
                $this->plugin->getAuthManager()->clearPlayerData($targetName);
                $msg->send($sender, "admin-logout-success", ["player" => $targetName]);
                break;

            default:
                $sender->sendMessage("§e§lLITEAUTH §8┃ §7Неизвестная подкоманда. Доступные: §einfo, unregister, changepassword, logout, reload");
                break;
        }

        return true;
    }
}
