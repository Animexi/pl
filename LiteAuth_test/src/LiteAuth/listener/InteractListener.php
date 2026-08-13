<?php

namespace LiteAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use LiteAuth\manager\AuthManager;
use LiteAuth\util\ConfigManager;

class InteractListener implements Listener {
    
    /** @var AuthManager */
    private $authManager;
    
    /** @var ConfigManager */
    private $configManager;
    
    public function __construct(AuthManager $authManager, ConfigManager $configManager) {
        $this->authManager = $authManager;
        $this->configManager = $configManager;
    }
    
    /**
     * @param PlayerInteractEvent $event
     * @priority HIGHEST
     * @ignoreCancelled true
     */
    public function onPlayerInteract(PlayerInteractEvent $event) {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        
        if ($player->hasPermission("liteauth.bypass")) {
            return;
        }
        
        if ($this->authManager->isAuthenticated($playerName)) {
            return;
        }
        
        $event->setCancelled();
    }
    
    /**
     * @param BlockBreakEvent $event
     * @priority HIGHEST
     * @ignoreCancelled true
     */
    public function onBlockBreak(BlockBreakEvent $event) {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        
        if ($player->hasPermission("liteauth.bypass")) {
            return;
        }
        
        if ($this->authManager->isAuthenticated($playerName)) {
            return;
        }
        
        $event->setCancelled();
    }
    
    /**
     * @param BlockPlaceEvent $event
     * @priority HIGHEST
     * @ignoreCancelled true
     */
    public function onBlockPlace(BlockPlaceEvent $event) {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        
        if ($player->hasPermission("liteauth.bypass")) {
            return;
        }
        
        if ($this->authManager->isAuthenticated($playerName)) {
            return;
        }
        
        $event->setCancelled();
    }
}
