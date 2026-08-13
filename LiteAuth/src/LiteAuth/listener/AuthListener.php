<?php

namespace LiteAuth\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use LiteAuth\manager\AuthManager;
use LiteAuth\util\MessageManager;

class AuthListener implements Listener {
    
    /** @var AuthManager */
    private $authManager;
    
    /** @var MessageManager */
    private $messageManager;
    
    public function __construct(AuthManager $authManager, MessageManager $messageManager) {
        $this->authManager = $authManager;
        $this->messageManager = $messageManager;
    }
    
    /**
     * @param PlayerJoinEvent $event
     * @priority HIGHEST
     */
    public function onPlayerJoin(PlayerJoinEvent $event) {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        
        // Проверка на обход авторизации для администраторов
        if ($player->hasPermission("liteauth.bypass")) {
            return;
        }
        
        // Инициализация состояния игрока
        $this->authManager->initializePlayer($player);
        
        // Проверка существования аккаунта
        if ($this->authManager->isRegistered($playerName)) {
            // Игрок зарегистрирован - проверяем сессию для авто-логина
            if ($this->authManager->checkAutoLogin($player)) {
                // Авто-логин успешен
                $this->authManager->setAuthenticated($playerName, true);
                $this->messageManager->sendBoxedMessage(
                    $player,
                    "§e§lLITEAUTH",
                    [
                        "§aАвторизация выполнена.",
                        "§7Добро пожаловать, §f{$playerName}§7."
                    ]
                );
                $this->messageManager->sendMessage($player, "§e§lLITE§f§lAUTH §8┃ §aАвтоматическая авторизация выполнена.");
            } else {
                // Требуется ввод пароля
                $this->authManager->setState($playerName, \LiteAuth\model\AuthState::AUTH_REQUIRED);
                
                $this->messageManager->sendBoxedMessage(
                    $player,
                    "§e§lLITEAUTH",
                    [
                        "§fАккаунт найден.",
                        "§7Введите пароль для входа.",
                        "",
                        "§e/login <пароль>"
                    ]
                );
                
                // Запуск таймера авторизации
                $this->authManager->startAuthTimer($player);
            }
        } else {
            // Игрок не зарегистрирован - показываем сообщение о регистрации
            $this->authManager->setState($playerName, \LiteAuth\model\AuthState::UNREGISTERED);
            
            $this->messageManager->sendBoxedMessage(
                $player,
                "§e§lLITEAUTH",
                [
                    "§fДобро пожаловать на сервер.",
                    "§7Для продолжения необходимо",
                    "§7создать аккаунт.",
                    "",
                    "§e/register <пароль> <пароль>",
                    "",
                    "§7Пример: §f/register password password"
                ]
            );
        }
    }
}
