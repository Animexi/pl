<?php

declare(strict_types=1);

namespace ServerAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use ServerAuth\manager\AuthManager;
use ServerAuth\util\ConfigManager;
use ServerAuth\model\AuthState;

/**
 * Блокировка команд до авторизации
 */
class CommandListener implements Listener {
    
    private AuthManager $authManager;
    private ConfigManager $configManager;
    
    public function __construct(AuthManager $authManager, ConfigManager $configManager) {
        $this->authManager = $authManager;
        $this->configManager = $configManager;
    }
    
    /**
     * Обработка ввода команды
     */
    public function onPlayerCommandPreprocess(PlayerCommandPreprocessEvent $event): void {
        $player = $event->getPlayer();
        $message = $event->getMessage();
        
        // Если игрок авторизован - пропускаем
        if ($this->authManager->isLoggedIn($player)) {
            return;
        }
        
        // Извлечение команды из сообщения
        $command = strtok($message, " ");
        if ($command === false) {
            return;
        }
        
        // Удаление слэша и приведение к нижнему регистру
        $command = strtolower(ltrim($command, '/'));
        
        // Проверка, разрешена ли команда
        if ($this->configManager->isCommandAllowed($command)) {
            return;
        }
        
        // Блокировка команды
        $event->setCancelled();
        
        // Отправка сообщения о необходимости авторизации
        $state = $this->authManager->getState($player);
        
        if ($state === AuthState::UNREGISTERED) {
            $player->sendMessage("§8[§6Server§8] §cСначала зарегистрируйтесь: /register <пароль> <повтор>");
        } else {
            $player->sendMessage("§8[§6Server§8] §cАвторизуйтесь для использования команд: /login <пароль>");
        }
    }
}
