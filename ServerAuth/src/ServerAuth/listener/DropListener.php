<?php

declare(strict_types=1);

namespace ServerAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDropItemEvent;
use ServerAuth\ServerAuthPlugin;
use ServerAuth\manager\AuthManager;
use ServerAuth\manager\MessageManager;

class DropListener implements Listener {
    
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
     * Обработка дропа предметов
     */
    public function onPlayerDropItem(PlayerDropItemEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        
        // Если игрок авторизован - разрешаем друп
        if ($this->authManager->isLoggedIn($playerName)) {
            return;
        }
        
        // Проверка настройки блокировки
        if (!$this->plugin->getConfig()->getNested("protection.block-drop", true)) {
            return;
        }
        
        // Отмена события
        $event->setCancelled();
        
        // Отправка сообщения (с кулдауном)
        static $lastMessageTime = [];
        $currentTime = microtime(true);
        
        if (!isset($lastMessageTime[$playerName]) || ($currentTime - $lastMessageTime[$playerName]) > 2) {
            $this->messageManager->send($player, "protection.drop-blocked");
            $lastMessageTime[$playerName] = $currentTime;
        }
    }
}
