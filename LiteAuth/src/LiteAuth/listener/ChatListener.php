<?php

declare(strict_types=1);

namespace LiteAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\Player;
use LiteAuth\LiteAuthPlugin;

class ChatListener implements Listener {

    /** @var LiteAuthPlugin */
    private $plugin;

    public function __construct(LiteAuthPlugin $plugin) {
        $this->plugin = $plugin;
    }

    public function onChat(PlayerChatEvent $event): void {
        $player = $event->getPlayer();
        
        if ($player->hasPermission("liteauth.bypass")) {
            return;
        }
        
        if ($this->plugin->getAuthManager()->isAuthenticated($player)) {
            return;
        }
        
        $event->setCancelled();
        $this->plugin->getMessageManager()->sendPrefix($player, "§cСначала необходимо авторизоваться.");
    }
}
