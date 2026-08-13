<?php

declare(strict_types=1);

namespace LiteAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\Player;
use LiteAuth\LiteAuthPlugin;
use LiteAuth\manager\AuthManager;

class AuthListener implements Listener {

    private $plugin;

    public function __construct(LiteAuthPlugin $plugin) {
        $this->plugin = $plugin;
    }

    public function onPlayerJoin(PlayerJoinEvent $event) {
        $player = $event->getPlayer();
        $authManager = $this->plugin->getAuthManager();
        $msgManager = $this->plugin->getMessageManager();

        // Check if player has bypass permission
        if ($player->hasPermission("liteauth.bypass")) {
            $authManager->setState($player, AuthManager::STATE_AUTHENTICATED);
            return;
        }

        // Check if player is registered
        if (!$authManager->isRegistered($player)) {
            $authManager->setState($player, AuthManager::STATE_UNREGISTERED);
            $msgManager->sendWelcome($player);
            return;
        }

        // Player is registered, check for auto-login
        if ($this->plugin->getConfigValue("auto-login", true) && $authManager->hasValidSession($player)) {
            $authManager->setState($player, AuthManager::STATE_AUTHENTICATED);
            $authManager->saveSession($player);
            $msgManager->sendAutoLogin($player);
            return;
        }

        // Require login
        $authManager->setState($player, AuthManager::STATE_AUTH_REQUIRED);
        $msgManager->sendLoginRequest($player);
    }
}
