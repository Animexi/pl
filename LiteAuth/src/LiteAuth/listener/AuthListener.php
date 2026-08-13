<?php

declare(strict_types=1);

namespace LiteAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\Player;
use LiteAuth\LiteAuthPlugin;

class AuthListener implements Listener {

    /** @var LiteAuthPlugin */
    private $plugin;

    public function __construct(LiteAuthPlugin $plugin) {
        $this->plugin = $plugin;
    }

    public function onJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        
        // Обход для администраторов с bypass
        if ($player->hasPermission("liteauth.bypass")) {
            return;
        }
        
        $this->plugin->getAuthManager()->handleJoin($player);
    }
}
