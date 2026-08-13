<?php

declare(strict_types=1);

namespace LiteAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\Player;
use LiteAuth\LiteAuthPlugin;

class DamageListener implements Listener {

    /** @var LiteAuthPlugin */
    private $plugin;

    public function __construct(LiteAuthPlugin $plugin) {
        $this->plugin = $plugin;
    }

    public function onDamage(EntityDamageEvent $event): void {
        $entity = $event->getEntity();
        
        if (!$entity instanceof Player) {
            return;
        }
        
        // Если игрок получает урон - пропускаем (чтобы не был бессмертным)
        // Но если он сам наносит урон до авторизации - отменяем
        
        if ($event instanceof EntityDamageByEntityEvent) {
            $damager = $event->getDamager();
            
            if ($damager instanceof Player && !$damager->hasPermission("liteauth.bypass")) {
                if (!$this->plugin->getAuthManager()->isAuthenticated($damager)) {
                    $event->setCancelled();
                }
            }
        }
    }
}
