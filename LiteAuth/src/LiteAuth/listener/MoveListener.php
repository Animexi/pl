<?php

declare(strict_types=1);

namespace LiteAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\Player;
use LiteAuth\LiteAuthPlugin;
use LiteAuth\manager\AuthManager;

class MoveListener implements Listener {

    private $plugin;

    public function __construct(LiteAuthPlugin $plugin) {
        $this->plugin = $plugin;
    }

    public function onPlayerMove(PlayerMoveEvent $event) {
        $player = $event->getPlayer();
        $authManager = $this->plugin->getAuthManager();

        // Allow movement if authenticated or has bypass
        if ($authManager->isAuthenticated($player) || $player->hasPermission("liteauth.bypass")) {
            return;
        }

        // Cancel movement for unauthenticated players
        $from = $event->getFrom();
        $to = $event->getTo();

        // Only cancel if position actually changed
        if ($from->x !== $to->x || $from->y !== $to->y || $from->z !== $to->z) {
            $event->setCancelled();
            
            // Teleport back to from position
            $player->teleport($from);
        }
    }
}
