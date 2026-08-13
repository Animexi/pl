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

        // Check if player has bypass permission
        if ($player->hasPermission("liteauth.bypass")) {
            $authManager->setState($player, AuthManager::STATE_AUTHENTICATED);
            return;
        }

        // Check if player is registered
        if (!$authManager->isRegistered($player)) {
            $authManager->setState($player, AuthManager::STATE_UNREGISTERED);
            $this->plugin->getMessageManager()->send($player, "join-unregistered");
            return;
        }

        // Player is registered, check for auto-login
        if ($this->plugin->getConfigValue("auto-login", true) && $authManager->hasValidSession($player)) {
            $authManager->setState($player, AuthManager::STATE_AUTHENTICATED);
            $authManager->saveSession($player);
            $this->plugin->getMessageManager()->sendRaw($player, $this->plugin->getMessageManager()->get("auto-login-success"));
            return;
        }

        // Require login
        $authManager->setState($player, AuthManager::STATE_AUTH_REQUIRED);
        $this->plugin->getMessageManager()->send($player, "join-registered");
    }
}
