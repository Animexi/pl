<?php

namespace LiteAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use LiteAuth\LiteAuthPlugin;
use LiteAuth\manager\AuthManager;

class JoinListener implements Listener {
    
    private $plugin;
    private $authManager;
    
    public function __construct(LiteAuthPlugin $plugin, AuthManager $authManager) {
        $this->plugin = $plugin;
        $this->authManager = $authManager;
    }
    
    public function onPlayerJoin(PlayerJoinEvent $event) {
        $player = $event->getPlayer();
        $this->authManager->handleJoin($player);
    }
}
