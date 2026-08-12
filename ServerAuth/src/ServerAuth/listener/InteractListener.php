<?php

declare(strict_types=1);

namespace ServerAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use ServerAuth\manager\AuthManager;
use ServerAuth\model\AuthState;

/**
 * Блокировка взаимодействия с блоками до авторизации
 */
class InteractListener implements Listener {
    
    private AuthManager $authManager;
    
    public function __construct(AuthManager $authManager) {
        $this->authManager = $authManager;
    }
    
    /**
     * Обработка взаимодействия с блоками
     */
    public function onPlayerInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();
        
        if ($this->authManager->isLoggedIn($player)) {
            return;
        }
        
        $state = $this->authManager->getState($player);
        
        if ($state === AuthState::UNREGISTERED || $state === AuthState::REGISTERED_NOT_LOGGED) {
            $event->setCancelled();
        }
    }
    
    /**
     * Обработка разрушения блоков
     */
    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();
        
        if ($this->authManager->isLoggedIn($player)) {
            return;
        }
        
        $event->setCancelled();
    }
    
    /**
     * Обработка установки блоков
     */
    public function onBlockPlace(BlockPlaceEvent $event): void {
        $player = $event->getPlayer();
        
        if ($this->authManager->isLoggedIn($player)) {
            return;
        }
        
        $event->setCancelled();
    }
}
