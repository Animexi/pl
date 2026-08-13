<?php

declare(strict_types=1);

namespace LiteAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\Player;
use LiteAuth\LiteAuthPlugin;

class CommandListener implements Listener {

    /** @var LiteAuthPlugin */
    private $plugin;
    
    /** @var array<string> */
    private $allowedCommands = [
        'login',
        'l',
        'register',
        'reg',
        'captcha',
        'help',
        '?',
        'auth'
    ];

    public function __construct(LiteAuthPlugin $plugin) {
        $this->plugin = $plugin;
    }

    public function onCommand(PlayerCommandPreprocessEvent $event): void {
        $player = $event->getPlayer();
        
        if ($player->hasPermission("liteauth.bypass")) {
            return;
        }
        
        if ($this->plugin->getAuthManager()->isAuthenticated($player)) {
            return;
        }
        
        $message = $event->getMessage();
        if (strpos($message, '/') === 0) {
            $message = substr($message, 1);
        }
        
        $parts = explode(' ', $message);
        $cmd = strtolower(array_shift($parts));
        
        // Разрешаем только команды авторизации
        if (!in_array($cmd, $this->allowedCommands)) {
            $event->setCancelled();
            $this->plugin->getMessageManager()->sendPrefix($player, "§cСначала необходимо авторизоваться.");
        }
    }
}
