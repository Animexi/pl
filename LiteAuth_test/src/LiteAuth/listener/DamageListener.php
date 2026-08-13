<?php

namespace LiteAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use LiteAuth\manager\AuthManager;
use LiteAuth\util\ConfigManager;

class DamageListener implements Listener {
    
    /** @var AuthManager */
    private $authManager;
    
    /** @var ConfigManager */
    private $configManager;
    
    public function __construct(AuthManager $authManager, ConfigManager $configManager) {
        $this->authManager = $authManager;
        $this->configManager = $configManager;
    }
    
    /**
     * @param EntityDamageEvent $event
     * @priority HIGHEST
     * @ignoreCancelled true
     */
    public function onEntityDamage(EntityDamageEvent $event) {
        // Если это игрок получает урон
        if ($event->getEntity() instanceof \pocketmine\Player) {
            $player = $event->getEntity();
            $playerName = $player->getName();
            
            if ($player->hasPermission("liteauth.bypass")) {
                return;
            }
            
            if ($this->authManager->isAuthenticated($playerName)) {
                return;
            }
            
            // Блокируем получение урона неавторизованным игроком
            $event->setCancelled();
        }
        
        // Если игрок наносит урон
        if ($event instanceof EntityDamageByEntityEvent) {
            $damager = $event->getDamager();
            
            if ($damager instanceof \pocketmine\Player) {
                $damagerName = $damager->getName();
                
                if ($damager->hasPermission("liteauth.bypass")) {
                    return;
                }
                
                if ($this->authManager->isAuthenticated($damagerName)) {
                    return;
                }
                
                // Блокируем нанесение урона неавторизованным игроком
                $event->setCancelled();
            }
        }
    }
}
