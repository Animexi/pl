<?php

declare(strict_types=1);

namespace LiteAuth\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use LiteAuth\LiteAuthPlugin;

class CaptchaCommand extends Command {

    private $plugin;

    public function __construct(LiteAuthPlugin $plugin) {
        parent::__construct("captcha", "Solve captcha challenge", "/captcha <answer>", []);
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("§cЭта команда доступна только игрокам.");
            return true;
        }

        $authManager = $this->plugin->getAuthManager();

        // If no args and needs captcha, show new captcha
        if (count($args) === 0) {
            if ($authManager->needsCaptcha($sender)) {
                $captcha = $authManager->generateCaptcha($sender);
                $this->plugin->getMessageManager()->send($sender, "captcha-message", ["captcha" => $captcha]);
                return true;
            }
            $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §cУ вас нет активной капчи.");
            return true;
        }

        if (!$authManager->needsCaptcha($sender)) {
            $sender->sendMessage("§e§lLITE§f§lAUTH §8┃ §cВам не нужно проходить капчу.");
            return true;
        }

        $maxAttempts = $this->plugin->getConfigValue("captcha-attempts", 3);
        
        if ($authManager->getCaptchaAttemptCount($sender) >= $maxAttempts) {
            $this->plugin->getMessageManager()->send($sender, "error-max-attempts");
            $sender->kick("Слишком много неудачных попыток ввода капчи.", false);
            return true;
        }

        $answer = (int)$args[0];

        if ($authManager->checkCaptcha($sender, $answer)) {
            $authManager->setState($sender, \LiteAuth\manager\AuthManager::STATE_AUTHENTICATED);
            $authManager->resetCaptchaAttempts($sender);
            $authManager->saveSession($sender);
            $this->plugin->getMessageManager()->send($sender, "captcha-success");
        } else {
            $authManager->incrementCaptchaAttempts($sender);
            $this->plugin->getMessageManager()->send($sender, "error-captcha-wrong");
            
            if ($authManager->getCaptchaAttemptCount($sender) >= $maxAttempts) {
                $sender->kick("Слишком много неудачных попыток ввода капчи.", false);
            }
        }

        return true;
    }
}
