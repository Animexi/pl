<?php

declare(strict_types=1);

namespace LiteAuth\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use LiteAuth\LiteAuthPlugin;

class RegisterCommand extends Command {

    private $plugin;

    public function __construct(LiteAuthPlugin $plugin) {
        parent::__construct("register", "Register a new account", "/register <password> <password>", ["reg"]);
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("§cЭта команда доступна только игрокам.");
            return true;
        }

        $authManager = $this->plugin->getAuthManager();

        if ($authManager->isRegistered($sender)) {
            $this->plugin->getMessageManager()->send($sender, "error-already-registered");
            return true;
        }

        if (count($args) < 2) {
            $this->plugin->getMessageManager()->send($sender, "usage-register");
            return true;
        }

        $password = $args[0];
        $confirm = $args[1];

        $minLen = $this->plugin->getConfigValue("min-password-length", 6);
        $maxLen = $this->plugin->getConfigValue("max-password-length", 32);

        if (strlen($password) < $minLen) {
            $this->plugin->getMessageManager()->send($sender, "error-password-short", ["min" => $minLen]);
            return true;
        }

        if (strlen($password) > $maxLen) {
            $this->plugin->getMessageManager()->send($sender, "error-password-long", ["max" => $maxLen]);
            return true;
        }

        if ($password !== $confirm) {
            $this->plugin->getMessageManager()->send($sender, "error-password-mismatch");
            return true;
        }

        $blacklist = $this->plugin->getConfigValue("password-blacklist", []);
        if (in_array(strtolower($password), array_map("strtolower", $blacklist))) {
            $this->plugin->getMessageManager()->send($sender, "error-password-simple");
            return true;
        }

        $ip = $sender->getAddress();
        $maxReg = $this->plugin->getConfigValue("max-registrations-per-ip", 3);
        if ($this->plugin->getStorageManager()->getRegistrationsByIp($ip) >= $maxReg) {
            $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §cПревышен лимит регистраций с вашего IP.");
            return true;
        }

        if ($authManager->register($sender, $password)) {
            $this->plugin->getMessageManager()->send($sender, "register-success");
            
            // Auto-login after registration if enabled
            if ($this->plugin->getConfigValue("auto-login", true)) {
                $authManager->setState($sender, \LiteAuth\manager\AuthManager::STATE_AUTHENTICATED);
                $authManager->saveSession($sender);
                $this->plugin->getMessageManager()->sendRaw($sender, "§e§lLITE§f§lAUTH §8┃ §aАвтоматическая авторизация выполнена после регистрации.");
            } else {
                // Show captcha
                $captcha = $authManager->generateCaptcha($sender);
                $this->plugin->getMessageManager()->send($sender, "captcha-message", ["captcha" => $captcha]);
            }
        } else {
            $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §cНе удалось создать аккаунт. Попробуйте позже.");
        }

        return true;
    }
}
