<?php

declare(strict_types=1);

namespace ServerAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use ServerAuth\manager\AuthManager;

/**
 * Слушатель событий выхода игроков
 */
class QuitListener implements Listener {
    
    private AuthManager $authManager;
    
    public function __construct(AuthManager $authManager) {
        $this->authManager = $authManager;
    }
    
    /**
     * Обработка выхода игрока
     */
    public function onPlayerQuit(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();
        
        // Автоматический logout и очистка состояния
        $this->authManager->onPlayerQuit($player);
    }
}
