<?php

declare(strict_types=1);

namespace LiteAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\Player;
use LiteAuth\LiteAuthPlugin;
use LiteAuth\manager\AuthManager;

class DropListener implements Listener {

    private $plugin;

    public function __construct(LiteAuthPlugin $plugin) {
        $this->plugin = $plugin;
    }

    public function onPlayerDropItem(PlayerDropItemEvent $event) {
        $player = $event->getPlayer();
        $authManager = $this->plugin->getAuthManager();

        // Allow dropping if authenticated or has bypass
        if ($authManager->isAuthenticated($player) || $player->hasPermission("liteauth.bypass")) {
            return;
        }

        // Cancel drop for unauthenticated players
        $event->setCancelled();
    }
}
