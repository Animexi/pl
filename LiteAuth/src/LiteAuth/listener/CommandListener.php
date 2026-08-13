<?php

namespace LiteAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use LiteAuth\LiteAuthPlugin;
use LiteAuth\manager\AuthManager;

class CommandListener implements Listener {
    
    private $plugin;
    private $authManager;
    
    private $allowedCommands = [
        "login", "l", "register", "reg", "captcha", "help", "auth"
    ];
    
    public function __construct(LiteAuthPlugin $plugin, AuthManager $authManager) {
        $this->plugin = $plugin;
        $this->authManager = $authManager;
    }
    
    public function onPlayerCommand(PlayerCommandPreprocessEvent $event) {
        $player = $event->getPlayer();
        
        if ($this->authManager->isAuthenticated($player)) {
            return;
        }
        
        if ($this->authManager->hasPermission($player, "liteauth.bypass")) {
            return;
        }
        
        $message = $event->getMessage();
        if (strpos($message, "/") === 0) {
            $message = substr($message, 1);
        }
        
        $parts = explode(" ", $message);
        $command = strtolower($parts[0]);
        
        if (in_array($command, $this->allowedCommands)) {
            return;
        }
        
        $event->setCancelled();
        $player->sendMessage($this->authManager->formatMessage("command-blocked"));
    }
}
