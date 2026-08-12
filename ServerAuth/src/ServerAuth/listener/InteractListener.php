<?php

declare(strict_types=1);

namespace ServerAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use ServerAuth\ServerAuthPlugin;
use ServerAuth\manager\AuthManager;
use ServerAuth\manager\MessageManager;

class InteractListener implements Listener {
    
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
     * Обработка взаимодействия с блоками
     */
    public function onPlayerInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        
        // Если игрок авторизован - разрешаем взаимодействие
        if ($this->authManager->isLoggedIn($playerName)) {
            return;
        }
        
        // Проверка настройки блокировки
        if (!$this->plugin->getConfig()->getNested("protection.block-block-interaction", true)) {
            return;
        }
        
        // Отмена события
        $event->setCancelled();
        
        // Отправка сообщения (с кулдауном)
        static $lastMessageTime = [];
        $currentTime = microtime(true);
        
        if (!isset($lastMessageTime[$playerName]) || ($currentTime - $lastMessageTime[$playerName]) > 2) {
            $this->messageManager->send($player, "protection.block-interaction-blocked");
            $lastMessageTime[$playerName] = $currentTime;
        }
    }
    
    /**
     * Обработка разрушения блоков
     */
    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        
        // Если игрок авторизован - разрешаем
        if ($this->authManager->isLoggedIn($playerName)) {
            return;
        }
        
        // Отмена события
        $event->setCancelled();
    }
    
    /**
     * Обработка установки блоков
     */
    public function onBlockPlace(BlockPlaceEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        
        // Если игрок авторизован - разрешаем
        if ($this->authManager->isLoggedIn($playerName)) {
            return;
        }
        
        // Отмена события
        $event->setCancelled();
    }
}
