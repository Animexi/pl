<?php

declare(strict_types=1);

namespace LiteAuth\manager;

use pocketmine\utils\Config;
use pocketmine\Player;
use LiteAuth\LiteAuthPlugin;

class MessageManager {

    /** @var LiteAuthPlugin */
    private $plugin;
    
    /** @var Config */
    private $messagesConfig;
    
    /** @var string */
    private $prefix;

    public function __construct(LiteAuthPlugin $plugin) {
        $this->plugin = $plugin;
        $this->messagesConfig = new Config($plugin->getDataFolder() . "messages.yml", Config::YAML);
        $this->prefix = $this->messagesConfig->get("prefix", "§e§lLITE§f§lAUTH §8┃ ");
    }

    public function reload(): void {
        $this->plugin->saveResource("messages.yml", true);
        $this->messagesConfig = new Config($this->plugin->getDataFolder() . "messages.yml", Config::YAML);
        $this->prefix = $this->messagesConfig->get("prefix", "§e§lLITE§f§lAUTH §8┃ ");
    }

    /**
     * Получает сообщение по ключу с заменой переменных
     */
    public function get(string $key, array $vars = []): string {
        $message = $this->messagesConfig->get($key);
        
        if ($message === null) {
            // Возвращаем безопасное сообщение по умолчанию вместо "not found"
            return $this->prefix . "§cMessage: " . $key;
        }

        // Заменяем переменные
        foreach ($vars as $var => $value) {
            $message = str_replace("{" . $var . "}", (string)$value, $message);
        }

        // Добавляем префикс к однострочным сообщениям
        if (strpos($message, "╔") === false && strpos($message, "\n") === false) {
            if (strpos($message, $this->prefix) !== 0) {
                $message = $this->prefix . $message;
            }
        }

        return $message;
    }

    /**
     * Отправляет сообщение игроку
     */
    public function send(Player $player, string $key, array $vars = []): void {
        $message = $this->get($key, $vars);
        $player->sendMessage($message);
    }

    /**
     * Отправляет префиксное сообщение
     */
    public function sendPrefix(Player $player, string $message): void {
        $player->sendMessage($this->prefix . $message);
    }

    /**
     * Получает префикс
     */
    public function getPrefix(): string {
        return $this->prefix;
    }
}
