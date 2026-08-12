<?php

declare(strict_types=1);

namespace ServerAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use ServerAuth\ServerAuthPlugin;
use ServerAuth\manager\AuthManager;
use ServerAuth\manager\MessageManager;

class CommandListener implements Listener {
    
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
     * Обработка команд перед выполнением
     */
    public function onPlayerCommandPreprocess(PlayerCommandPreprocessEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        
        // Если игрок авторизован - пропускаем команду
        if ($this->authManager->isLoggedIn($playerName)) {
            return;
        }
        
        // Проверка настройки блокировки команд
        if (!$this->plugin->getConfig()->getNested("protection.block-commands", true)) {
            return;
        }
        
        // Получение команды из сообщения
        $message = $event->getMessage();
        
        // Удаление слэша в начале
        if (strpos($message, "/") === 0) {
            $message = substr($message, 1);
        }
        
        // Извлечение имени команды (без аргументов)
        $parts = explode(" ", $message);
        $commandName = strtolower(array_shift($parts));
        
        // Получение списка разрешенных команд
        $allowedCommands = array_map('strtolower', $this->plugin->getConfig()->getNested("protection.allowed-commands", [
            "login",
            "register",
            "changepassword",
            "auth",
            "help"
        ]));
        
        // Проверка является ли команда разрешенной
        if (in_array($commandName, $allowedCommands)) {
            return;
        }
        
        // Отмена выполнения команды
        $event->setCancelled();
        
        // Отправка сообщения о блокировке
        $this->messageManager->send($player, "protection.command-blocked");
    }
}
