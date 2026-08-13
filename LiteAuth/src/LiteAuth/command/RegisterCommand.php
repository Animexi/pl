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
        $msgManager = $this->plugin->getMessageManager();

        if ($authManager->isRegistered($sender)) {
            $msgManager->sendAlreadyRegistered($sender);
            return true;
        }

        if (count($args) < 2) {
            $msgManager->sendInvalidCommand($sender, "/register <пароль> <пароль>");
            return true;
        }

        $password = $args[0];
        $confirm = $args[1];

        $minLen = $this->plugin->getConfigValue("min-password-length", 6);
        $maxLen = $this->plugin->getConfigValue("max-password-length", 32);

        if (strlen($password) < $minLen) {
            $msgManager->sendPasswordTooShort($sender, $minLen);
            return true;
        }

        if (strlen($password) > $maxLen) {
            $msgManager->sendPasswordTooLong($sender, $maxLen);
            return true;
        }

        if ($password !== $confirm) {
            $msgManager->sendPasswordMismatch($sender);
            return true;
        }

        $blacklist = $this->plugin->getConfigValue("password-blacklist", []);
        if (in_array(strtolower($password), array_map("strtolower", $blacklist))) {
            $msgManager->sendSimplePassword($sender);
            return true;
        }

        $ip = $sender->getAddress();
        $maxReg = $this->plugin->getConfigValue("max-registrations-per-ip", 3);
        if ($this->plugin->getStorageManager()->getRegistrationsByIp($ip) >= $maxReg) {
            $msgManager->sendMaxRegistrations($sender);
            return true;
        }

        if (!$this->plugin->getConfigValue("registration-enabled", true)) {
            $msgManager->sendRegistrationDisabled($sender);
            return true;
        }

        if ($authManager->register($sender, $password)) {
            $msgManager->sendRegisterSuccess($sender);
            
            // Generate captcha
            $captcha = $authManager->generateCaptcha($sender);
            $msgManager->sendCaptcha($sender, $captcha);
        } else {
            $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §cНе удалось создать аккаунт. Попробуйте позже.");
        }

        return true;
    }
}
