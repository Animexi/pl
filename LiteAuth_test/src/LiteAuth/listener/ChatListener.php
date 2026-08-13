<?php

namespace LiteAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use LiteAuth\manager\AuthManager;
use LiteAuth\util\ConfigManager;

class ChatListener implements Listener {
    
    /** @var AuthManager */
    private $authManager;
    
    /** @var ConfigManager */
    private $configManager;
    
    public function __construct(AuthManager $authManager, ConfigManager $configManager) {
        $this->authManager = $authManager;
        $this->configManager = $configManager;
    }
    
    /**
     * @param PlayerChatEvent $event
     * @priority HIGHEST
     * @ignoreCancelled true
     */
    public function onPlayerChat(PlayerChatEvent $event) {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        
        if ($player->hasPermission("liteauth.bypass")) {
            return;
        }
        
        if ($this->authManager->isAuthenticated($playerName)) {
            return;
        }
        
        // Блокируем чат для неавторизованных игроков
        $event->setCancelled();
        
        // Отправляем сообщение о необходимости авторизации (не спамим)
        $state = $this->authManager->getState($playerName);
        if ($state !== \LiteAuth\model\AuthState::CHAT_BLOCKED_INFO) {
            $this->authManager->setState($playerName, \LiteAuth\model\AuthState::CHAT_BLOCKED_INFO);
            $player->sendMessage("§e§lLITE§f§lAUTH §8┃ §cСначала необходимо авторизоваться.");
        }
    }
}
