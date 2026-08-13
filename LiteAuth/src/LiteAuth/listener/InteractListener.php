<?php

declare(strict_types=1);

namespace LiteAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\Player;
use LiteAuth\LiteAuthPlugin;
use LiteAuth\manager\AuthManager;

class InteractListener implements Listener {

    private $plugin;

    public function __construct(LiteAuthPlugin $plugin) {
        $this->plugin = $plugin;
    }

    public function onPlayerInteract(PlayerInteractEvent $event) {
        $player = $event->getPlayer();
        $authManager = $this->plugin->getAuthManager();

        // Allow interaction if authenticated or has bypass
        if ($authManager->isAuthenticated($player) || $player->hasPermission("liteauth.bypass")) {
            return;
        }

        // Cancel interaction for unauthenticated players
        $event->setCancelled();
    }
}
