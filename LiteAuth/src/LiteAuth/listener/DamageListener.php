<?php

namespace LiteAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use LiteAuth\LiteAuthPlugin;
use LiteAuth\manager\AuthManager;

class DamageListener implements Listener {
    
    private $plugin;
    private $authManager;
    
    public function __construct(LiteAuthPlugin $plugin, AuthManager $authManager) {
        $this->plugin = $plugin;
        $this->authManager = $authManager;
    }
    
    public function onEntityDamage(EntityDamageEvent $event) {
        if ($event instanceof EntityDamageByEntityEvent) {
            $damager = $event->getDamager();
            if ($damager instanceof \pocketmine\Player) {
                if (!$this->authManager->isAuthenticated($damager)) {
                    if (!$this->authManager->hasPermission($damager, "liteauth.bypass")) {
                        $event->setCancelled();
                    }
                }
            }
        }
        
        $victim = $event->getEntity();
        if ($victim instanceof \pocketmine\Player) {
            if (!$this->authManager->isAuthenticated($victim)) {
                if (!$this->authManager->hasPermission($victim, "liteauth.bypass")) {
                    $event->setCancelled();
                }
            }
        }
    }
}
