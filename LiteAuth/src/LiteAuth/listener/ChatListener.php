<?php

namespace LiteAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use LiteAuth\LiteAuthPlugin;
use LiteAuth\manager\AuthManager;

class ChatListener implements Listener {
    
    private $plugin;
    private $authManager;
    
    public function __construct(LiteAuthPlugin $plugin, AuthManager $authManager) {
        $this->plugin = $plugin;
        $this->authManager = $authManager;
    }
    
    public function onPlayerChat(PlayerChatEvent $event) {
        $player = $event->getPlayer();
        
        if ($this->authManager->isAuthenticated($player)) {
            return;
        }
        
        if ($this->authManager->hasPermission($player, "liteauth.bypass")) {
            return;
        }
        
        $event->setCancelled();
        $player->sendMessage($this->authManager->formatMessage("chat-blocked"));
    }
}
