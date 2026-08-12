<?php

declare(strict_types=1);

namespace ServerAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDropItemEvent;
use ServerAuth\manager\AuthManager;
use ServerAuth\model\AuthState;

/**
 * Блокировка выброса предметов до авторизации
 */
class DropListener implements Listener {
    
    private AuthManager $authManager;
    
    public function __construct(AuthManager $authManager) {
        $this->authManager = $authManager;
    }
    
    /**
     * Обработка выброса предмета
     */
    public function onPlayerDropItem(PlayerDropItemEvent $event): void {
        $player = $event->getPlayer();
        
        // Если игрок авторизован - пропускаем
        if ($this->authManager->isLoggedIn($player)) {
            return;
        }
        
        $state = $this->authManager->getState($player);
        
        // Блокировка выброса предметов для неавторизованных игроков
        if ($state === AuthState::UNREGISTERED || $state === AuthState::REGISTERED_NOT_LOGGED) {
            $event->setCancelled();
        }
    }
}
