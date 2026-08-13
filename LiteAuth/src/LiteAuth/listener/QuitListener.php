<?php

declare(strict_types=1);

namespace LiteAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\Player;
use LiteAuth\LiteAuthPlugin;

class QuitListener implements Listener {

    /** @var LiteAuthPlugin */
    private $plugin;

    public function __construct(LiteAuthPlugin $plugin) {
        $this->plugin = $plugin;
    }

    public function onQuit(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();
        $name = $player->getName();
        
        // Очищаем временные данные
        $this->plugin->getAuthManager()->clearPlayerData($name);
        $this->plugin->getStorageManager()->clearCache($name);
    }
}
