<?php

declare(strict_types=1);

namespace LiteAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\Player;
use LiteAuth\LiteAuthPlugin;
use LiteAuth\manager\AuthManager;

class DamageListener implements Listener {

    private $plugin;

    public function __construct(LiteAuthPlugin $plugin) {
        $this->plugin = $plugin;
    }

    public function onEntityDamage(EntityDamageEvent $event) {
        // If damage is from a player
        if ($event instanceof EntityDamageByEntityEvent) {
            $damager = $event->getDamager();
            
            if ($damager instanceof Player) {
                $authManager = $this->plugin->getAuthManager();
                
                // Allow damage if authenticated or has bypass
                if (!$authManager->isAuthenticated($damager) && !$damager->hasPermission("liteauth.bypass")) {
                    $event->setCancelled();
                    return;
                }
            }
        }

        // If the target is a player who is not authenticated
        $target = $event->getEntity();
        if ($target instanceof Player) {
            $authManager = $this->plugin->getAuthManager();
            
            // Don't cancel damage to unauthenticated players (they can still be hurt by mobs/environment)
            // But we could optionally protect them - for now, let them take damage
        }
    }
}
