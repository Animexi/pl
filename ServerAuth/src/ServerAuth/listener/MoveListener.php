<?php

declare(strict_types=1);

namespace ServerAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use ServerAuth\manager\AuthManager;
use ServerAuth\model\AuthState;

/**
 * Блокировка движения до авторизации
 */
class MoveListener implements Listener {
    
    private AuthManager $authManager;
    
    public function __construct(AuthManager $authManager) {
        $this->authManager = $authManager;
    }
    
    /**
     * Обработка движения игрока
     */
    public function onPlayerMove(PlayerMoveEvent $event): void {
        $player = $event->getPlayer();
        
        // Проверка, авторизован ли игрок
        if ($this->authManager->isLoggedIn($player)) {
            return;
        }
        
        // Получение состояния
        $state = $this->authManager->getState($player);
        
        // Если игрок не зарегистрирован или не авторизован - блокировка движения
        if ($state === AuthState::UNREGISTERED || $state === AuthState::REGISTERED_NOT_LOGGED) {
            // Телепортация обратно на спавн (или текущую позицию)
            $from = $event->getFrom();
            $event->setTo($from);
        }
    }
}
