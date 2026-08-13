<?php

declare(strict_types=1);

namespace LiteAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\Player;
use LiteAuth\LiteAuthPlugin;
use LiteAuth\manager\AuthManager;

class CommandListener implements Listener {

    private $plugin;
    
    // Commands allowed before authentication
    private static $allowedCommands = [
        "login",
        "l",
        "register", 
        "reg",
        "captcha",
        "help",
        "auth"
    ];

    public function __construct(LiteAuthPlugin $plugin) {
        $this->plugin = $plugin;
    }

    public function onPlayerCommand(PlayerCommandPreprocessEvent $event) {
        $player = $event->getPlayer();
        $authManager = $this->plugin->getAuthManager();

        // Allow commands if authenticated or has bypass
        if ($authManager->isAuthenticated($player) || $player->hasPermission("liteauth.bypass")) {
            return;
        }

        $message = $event->getMessage();
        $args = explode(" ", trim($message));
        $cmd = strtolower(ltrim($args[0], "/"));

        // Check if command is allowed
        if (in_array($cmd, self::$allowedCommands)) {
            return;
        }

        // Block other commands
        $event->setCancelled();
        
        static $notifiedPlayers = [];
        $name = strtolower($player->getName());
        
        if (!isset($notifiedPlayers[$name])) {
            $this->plugin->getMessageManager()->send($player, "error-command-blocked");
            $notifiedPlayers[$name] = true;
        }
    }
}
