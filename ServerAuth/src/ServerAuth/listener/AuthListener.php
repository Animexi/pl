<?php

declare(strict_types=1);

namespace ServerAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use ServerAuth\manager\AuthManager;
use ServerAuth\util\MessageManager;

/**
 * Слушатель событий подключения игроков
 */
class AuthListener implements Listener {
    
    private AuthManager $authManager;
    private MessageManager $messageManager;
    
    public function __construct(AuthManager $authManager, MessageManager $messageManager) {
        $this->authManager = $authManager;
        $this->messageManager = $messageManager;
    }
    
    /**
     * Обработка подключения игрока
     */
    public function onPlayerJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        
        // Отмена стандартного сообщения о подключении (опционально)
        // $event->setJoinMessage("");
        
        // Инициализация состояния игрока
        $this->authManager->onPlayerJoin($player);
    }
}
