<?php

namespace LiteAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use LiteAuth\manager\AuthManager;

class QuitListener implements Listener {
    
    /** @var AuthManager */
    private $authManager;
    
    public function __construct(AuthManager $authManager) {
        $this->authManager = $authManager;
    }
    
    /**
     * @param PlayerQuitEvent $event
     */
    public function onPlayerQuit(PlayerQuitEvent $event) {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        
        // Очистка всех временных данных игрока
        $this->authManager->cleanupPlayer($playerName);
        
        // Сохранение данных авторизованного игрока
        if ($this->authManager->isAuthenticated($player)) {
            $this->authManager->savePlayerSession($player);
        }
    }
}
