<?php

namespace LiteAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use LiteAuth\manager\AuthManager;
use LiteAuth\util\ConfigManager;

class CommandListener implements Listener {
    
    /** @var AuthManager */
    private $authManager;
    
    /** @var ConfigManager */
    private $configManager;
    
    // Разрешённые команды до авторизации
    private static $allowedCommands = [
        '/login',
        '/l',
        '/register',
        '/reg',
        '/captcha',
        '/help',
        '/auth',
        '/me'
    ];
    
    public function __construct(AuthManager $authManager, ConfigManager $configManager) {
        $this->authManager = $authManager;
        $this->configManager = $configManager;
    }
    
    /**
     * @param PlayerCommandPreprocessEvent $event
     * @priority HIGHEST
     * @ignoreCancelled true
     */
    public function onPlayerCommandPreprocess(PlayerCommandPreprocessEvent $event) {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        
        // Пропуск для администраторов с bypass
        if ($player->hasPermission("liteauth.bypass")) {
            return;
        }
        
        // Пропуск для авторизованных игроков
        if ($this->authManager->isAuthenticated($playerName)) {
            return;
        }
        
        $message = $event->getMessage();
        $command = strtolower(trim($message));
        
        // Убираем слэш в начале для проверки
        if (strpos($command, '/') === 0) {
            $command = substr($command, 1);
        }
        
        // Получаем имя команды (без аргументов)
        $commandParts = explode(' ', $command);
        $commandName = '/' . strtolower($commandParts[0]);
        
        // Проверка на разрешённую команду
        foreach (self::$allowedCommands as $allowed) {
            if (strpos($commandName, strtolower($allowed)) === 0) {
                return;
            }
        }
        
        // Блокируем все остальные команды
        $event->setCancelled();
        $player->sendMessage("§e§lLITE§f§lAUTH §8┃ §cСначала необходимо авторизоваться.");
    }
}
