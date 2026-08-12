<?php

declare(strict_types=1);

namespace ServerAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use ServerAuth\manager\AuthManager;
use ServerAuth\model\AuthState;

/**
 * Блокировка урона до авторизации
 */
class DamageListener implements Listener {
    
    private AuthManager $authManager;
    
    public function __construct(AuthManager $authManager) {
        $this->authManager = $authManager;
    }
    
    /**
     * Обработка получения и нанесения урона
     */
    public function onEntityDamage(EntityDamageEvent $event): void {
        $entity = $event->getEntity();
        
        // Проверка, является ли сущность игроком (получатель урона)
        if ($entity instanceof \pocketmine\Player) {
            // Если игрок авторизован - пропускаем
            if (!$this->authManager->isLoggedIn($entity)) {
                $state = $this->authManager->getState($entity);
                
                // Блокировка получения урона неавторизованными игроками
                if ($state === AuthState::UNREGISTERED || $state === AuthState::REGISTERED_NOT_LOGGED) {
                    $event->setCancelled();
                    return;
                }
            }
        }
        
        // Проверка атакующего (если есть)
        if ($event instanceof EntityDamageByEntityEvent) {
            $damager = $event->getDamager();
            
            // Проверка, является ли атакующий игроком
            if ($damager instanceof \pocketmine\Player) {
                // Если игрок авторизован - пропускаем
                if (!$this->authManager->isLoggedIn($damager)) {
                    $state = $this->authManager->getState($damager);
                    
                    // Блокировка нанесения урона неавторизованными игроками
                    if ($state === AuthState::UNREGISTERED || $state === AuthState::REGISTERED_NOT_LOGGED) {
                        $event->setCancelled();
                    }
                }
            }
        }
    }
}
