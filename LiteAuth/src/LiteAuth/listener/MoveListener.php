<?php

declare(strict_types=1);

namespace LiteAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\level\Location;
use pocketmine\Player;
use LiteAuth\LiteAuthPlugin;

class MoveListener implements Listener {

    /** @var LiteAuthPlugin */
    private $plugin;

    public function __construct(LiteAuthPlugin $plugin) {
        $this->plugin = $plugin;
    }

    public function onMove(PlayerMoveEvent $event): void {
        $player = $event->getPlayer();
        
        // Обход для администраторов
        if ($player->hasPermission("liteauth.bypass")) {
            return;
        }
        
        // Проверяем авторизацию
        if ($this->plugin->getAuthManager()->isAuthenticated($player)) {
            return;
        }
        
        // Отменяем движение - телепортируем обратно
        $from = $event->getFrom();
        $to = $event->getTo();
        
        if ($to !== null && 
            (abs($from->x - $to->x) > 0.1 || 
             abs($from->y - $to->y) > 0.1 || 
             abs($from->z - $to->z) > 0.1)) {
            
            $player->teleport(new Location($from->x, $from->y, $from->z, $from->yaw, $from->pitch, $from->getLevel()));
            $event->setCancelled();
        }
    }
}
