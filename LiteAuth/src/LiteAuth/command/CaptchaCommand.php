<?php

declare(strict_types=1);

namespace LiteAuth\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use LiteAuth\LiteAuthPlugin;

class CaptchaCommand extends Command {

    /** @var LiteAuthPlugin */
    private $plugin;

    public function __construct(LiteAuthPlugin $plugin) {
        parent::__construct("captcha", "Solve captcha verification", "/captcha <answer>", []);
        $this->setPermission("liteauth.captcha");
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("§cЭта команда доступна только игрокам.");
            return true;
        }

        $player = $sender;
        $msg = $this->plugin->getMessageManager();
        $authManager = $this->plugin->getAuthManager();

        // Если нет аргументов - показываем новую капчу
        if (count($args) < 1) {
            $state = $authManager->getState($player);
            if ($state === LiteAuth\manager\AuthManager::STATE_CAPTCHA_REQUIRED) {
                $authManager->generateCaptcha($player);
            } else {
                $msg->send($player, "captcha-not-required");
            }
            return true;
        }

        // Проверка ответа
        $answer = $args[0];
        $authManager->checkCaptcha($player, $answer);
        
        return true;
    }
}
