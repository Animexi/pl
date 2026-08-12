<?php

declare(strict_types=1);

namespace ServerAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use ServerAuth\ServerAuthPlugin;
use ServerAuth\manager\AuthManager;
use ServerAuth\manager\MessageManager;

class MoveListener implements Listener {
    
    private ServerAuthPlugin $plugin;
    private AuthManager $authManager;
    private MessageManager $messageManager;
    
    /** @var array<string, \pocketmine\level\Position> Последние разрешенные позиции */
    private array $lastAllowedPositions = [];
    
    public function __construct(
        ServerAuthPlugin $plugin,
        AuthManager $authManager,
        MessageManager $messageManager
    ) {
        $this->plugin = $plugin;
        $this->authManager = $authManager;
        $this->messageManager = $messageManager;
    }
    
    public function onPlayerMove(PlayerMoveEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        
        // Если игрок авторизован - разрешаем движение
        if ($this->authManager->isLoggedIn($playerName)) {
            return;
        }
        
        // Проверка настройки блокировки движения
        if (!$this->plugin->getConfig()->getNested("protection.block-movement", true)) {
            return;
        }
        
        // Получение текущей и целевой позиций
        $from = $event->getFrom();
        $to = $event->getTo();
        
        // Если позиция не изменилась значительно - пропускаем
        if ($from->distance($to) < 0.1) {
            return;
        }
        
        // Сохранение последней разрешенной позиции
        if (!isset($this->lastAllowedPositions[$playerName])) {
            $this->lastAllowedPositions[$playerName] = $from;
        }
        
        // Телепортация обратно на последнюю разрешенную позицию
        $player->teleport($this->lastAllowedPositions[$playerName]);
        
        // Отправка сообщения (с кулдауном чтобы не спамить)
        static $lastMessageTime = [];
        $currentTime = microtime(true);
        
        if (!isset($lastMessageTime[$playerName]) || ($currentTime - $lastMessageTime[$playerName]) > 2) {
            $this->messageManager->send($player, "protection.movement-blocked");
            $lastMessageTime[$playerName] = $currentTime;
        }
        
        // Отмена события
        $event->setCancelled();
    }
    
    /**
     * Обновить разрешенную позицию игрока (вызывается при успешной авторизации)
     */
    public function updateAllowedPosition(string $playerName, $position): void {
        $this->lastAllowedPositions[$playerName] = $position;
    }
    
    /**
     * Очистить сохраненную позицию игрока
     */
    public function clearAllowedPosition(string $playerName): void {
        unset($this->lastAllowedPositions[$playerName]);
    }
}
