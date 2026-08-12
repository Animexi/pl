<?php

declare(strict_types=1);

namespace ServerAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageEvent;
use ServerAuth\ServerAuthPlugin;
use ServerAuth\manager\AuthManager;
use ServerAuth\manager\MessageManager;

class DamageListener implements Listener {
    
    private ServerAuthPlugin $plugin;
    private AuthManager $authManager;
    private MessageManager $messageManager;
    
    public function __construct(
        ServerAuthPlugin $plugin,
        AuthManager $authManager,
        MessageManager $messageManager
    ) {
        $this->plugin = $plugin;
        $this->authManager = $authManager;
        $this->messageManager = $messageManager;
    }
    
    /**
     * Обработка получения урона
     */
    public function onEntityDamage(EntityDamageEvent $event): void {
        $entity = $event->getEntity();
        
        // Если это не игрок - пропускаем
        if (!$entity instanceof \pocketmine\Player) {
            return;
        }
        
        $playerName = $entity->getName();
        
        // Если игрок авторизован - пропускаем
        if ($this->authManager->isLoggedIn($playerName)) {
            return;
        }
        
        // Проверка настройки блокировки получения урона
        if (!$this->plugin->getConfig()->getNested("protection.block-damage", true)) {
            return;
        }
        
        // Отмена события (игрок не получает урон)
        $event->setCancelled();
        
        // Отправка сообщения (с кулдауном)
        static $lastMessageTime = [];
        $currentTime = microtime(true);
        
        if (!isset($lastMessageTime[$playerName]) || ($currentTime - $lastMessageTime[$playerName]) > 3) {
            $this->messageManager->send($entity, "protection.damage-blocked");
            $lastMessageTime[$playerName] = $currentTime;
        }
    }
}
