<?php

namespace LiteAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\level\Location;
use LiteAuth\manager\AuthManager;
use LiteAuth\util\ConfigManager;

class MoveListener implements Listener {
    
    /** @var AuthManager */
    private $authManager;
    
    /** @var ConfigManager */
    private $configManager;
    
    public function __construct(AuthManager $authManager, ConfigManager $configManager) {
        $this->authManager = $authManager;
        $this->configManager = $configManager;
    }
    
    /**
     * @param PlayerMoveEvent $event
     * @priority HIGHEST
     * @ignoreCancelled true
     */
    public function onPlayerMove(PlayerMoveEvent $event) {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        
        // Пропуск для администраторов с bypass
        if ($player->hasPermission("liteauth.bypass")) {
            return;
        }
        
        // Пропуск для авторизованных игроков
        if ($this->authManager->isAuthenticated($playerName)) {
            return;
        }
        
        // Блокировка движения - возвращаем на исходную позицию
        $from = $event->getFrom();
        $event->setTo(new Location($from->x, $from->y, $from->z, $from->level, $from->yaw, $from->pitch));
    }
}
