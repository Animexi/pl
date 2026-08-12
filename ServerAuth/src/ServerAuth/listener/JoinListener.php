<?php

declare(strict_types=1);

namespace ServerAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use ServerAuth\ServerAuthPlugin;
use ServerAuth\manager\AuthManager;
use ServerAuth\manager\MessageManager;

class JoinListener implements Listener {
    
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
     * @priority HIGHEST
     */
    public function onPlayerJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        
        // Проверка на обход авторизации (если игрок был зарегистрирован но не вошел)
        if ($this->authManager->isRegistered($playerName)) {
            // Игрок зарегистрирован - ставим состояние REGISTERED_NOT_LOGGED
            $this->authManager->setPlayerState($playerName, AuthManager::STATE_REGISTERED_NOT_LOGGED);
            
            // Отправка приветственного сообщения для зарегистрированного игрока
            $this->messageManager->sendWelcomeMessage($player, true);
            
            // Применение защиты (телепортация на спавн если нужно)
            $this->applyProtection($player);
            
        } else {
            // Игрок не зарегистрирован - ставим состояние UNREGISTERED
            $this->authManager->setPlayerState($playerName, AuthManager::STATE_UNREGISTERED);
            
            // Отправка приветственного сообщения для нового игрока
            $this->messageManager->sendWelcomeMessage($player, false);
            
            // Применение защиты
            $this->applyProtection($player);
        }
        
        // Отмена стандартного сообщения о присоединении (опционально)
        // $event->setJoinMessage("");
    }
    
    /**
     * Применить защиту к игроку
     */
    private function applyProtection($player): void {
        // Телепортация на спавн (опционально, можно добавить в конфиг)
        // $player->teleport($this->plugin->getServer()->getDefaultLevel()->getSafeSpawn());
        
        // Сброс состояния полета
        if ($player->isFlying()) {
            $player->setFlying(false);
        }
        
        // Установка режима выживания для безопасности
        // $player->setGamemode(\pocketmine\Player::SURVIVAL);
    }
}
