<?php

declare(strict_types=1);

namespace ServerAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use ServerAuth\ServerAuthPlugin;
use ServerAuth\manager\AuthManager;

class QuitListener implements Listener {
    
    private ServerAuthPlugin $plugin;
    private AuthManager $authManager;
    
    public function __construct(
        ServerAuthPlugin $plugin,
        AuthManager $authManager
    ) {
        $this->plugin = $plugin;
        $this->authManager = $authManager;
    }
    
    public function onPlayerQuit(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        
        // Очистка данных игрока при выходе
        $this->authManager->clearPlayerData($playerName);
        
        // Сохранение данных игрока перед выходом
        $playerData = $this->plugin->getStorageManager()->loadPlayer($playerName);
        if ($playerData !== null) {
            $playerData["last_login"] = time();
            $this->plugin->getStorageManager()->savePlayer($playerName, $playerData);
        }
    }
}
