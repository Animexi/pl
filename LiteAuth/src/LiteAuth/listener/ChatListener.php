<?php

declare(strict_types=1);

namespace LiteAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\Player;
use LiteAuth\LiteAuthPlugin;
use LiteAuth\manager\AuthManager;

class ChatListener implements Listener {

    private $plugin;

    public function __construct(LiteAuthPlugin $plugin) {
        $this->plugin = $plugin;
    }

    public function onPlayerChat(PlayerChatEvent $event) {
        $player = $event->getPlayer();
        $authManager = $this->plugin->getAuthManager();

        // Allow chat if authenticated or has bypass
        if ($authManager->isAuthenticated($player) || $player->hasPermission("liteauth.bypass")) {
            return;
        }

        // Cancel chat for unauthenticated players
        $event->setCancelled();
        
        // Send error message (but not too spammy - only once per join session)
        static $notifiedPlayers = [];
        $name = strtolower($player->getName());
        
        if (!isset($notifiedPlayers[$name])) {
            $this->plugin->getMessageManager()->send($player, "error-chat-blocked");
            $notifiedPlayers[$name] = true;
        }
    }
}
