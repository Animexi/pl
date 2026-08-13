<?php

namespace LiteAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDropItemEvent;
use LiteAuth\manager\AuthManager;
use LiteAuth\util\ConfigManager;

class DropListener implements Listener {
    
    /** @var AuthManager */
    private $authManager;
    
    /** @var ConfigManager */
    private $configManager;
    
    public function __construct(AuthManager $authManager, ConfigManager $configManager) {
        $this->authManager = $authManager;
        $this->configManager = $configManager;
    }
    
    /**
     * @param PlayerDropItemEvent $event
     * @priority HIGHEST
     * @ignoreCancelled true
     */
    public function onPlayerDropItem(PlayerDropItemEvent $event) {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        
        if ($player->hasPermission("liteauth.bypass")) {
            return;
        }
        
        if ($this->authManager->isAuthenticated($player)) {
            return;
        }
        
        $event->setCancelled();
    }
}
