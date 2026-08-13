<?php

namespace LiteAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\level\Location;
use LiteAuth\LiteAuthPlugin;
use LiteAuth\manager\AuthManager;

class MoveListener implements Listener {
    
    private $plugin;
    private $authManager;
    
    public function __construct(LiteAuthPlugin $plugin, AuthManager $authManager) {
        $this->plugin = $plugin;
        $this->authManager = $authManager;
    }
    
    public function onPlayerMove(PlayerMoveEvent $event) {
        $player = $event->getPlayer();
        
        if ($this->authManager->isAuthenticated($player)) {
            return;
        }
        
        if ($this->authManager->hasPermission($player, "liteauth.bypass")) {
            return;
        }
        
        $from = $event->getFrom();
        $to = $event->getTo();
        
        if (abs($from->x - $to->x) < 0.1 && abs($to->y - $from->y) < 0.1 && abs($from->z - $to->z) < 0.1) {
            return;
        }
        
        $event->setCancelled();
        
        $player->teleport(new Location($from->x, $from->y, $from->z, $from->yaw, $from->pitch, $from->getLevel()));
    }
}
