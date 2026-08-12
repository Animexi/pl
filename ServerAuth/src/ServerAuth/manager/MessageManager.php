<?php

declare(strict_types=1);

namespace ServerAuth\manager;

use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\Config;
use ServerAuth\ServerAuthPlugin;

class MessageManager {
    
    private ServerAuthPlugin $plugin;
    private Config $messagesConfig;
    private string $prefix;
    
    public function __construct(ServerAuthPlugin $plugin, Config $messagesConfig) {
        $this->plugin = $plugin;
        $this->messagesConfig = $messagesConfig;
        $this->prefix = $this->messagesConfig->getNested("prefix", "§8[§6Server§8]");
    }
    
    public function reload(): void {
        $this->messagesConfig->reload();
        $this->prefix = $this->messagesConfig->getNested("prefix", "§8[§6Server§8]");
    }
    
    /**
     * Отправить сообщение игроку или консольному отправителю
     * @param CommandSender $sender
     * @param string $messageKey Ключ сообщения в формате "section.key"
     * @param array<string, string> $replacements Замены переменных
     */
    public function send(CommandSender $sender, string $messageKey, array $replacements = []): void {
        $message = $this->getMessage($messageKey);
        
        if ($message === null) {
            // Резервное сообщение если ключ не найден
            $this->sendRaw($sender, $this->prefix . " §cСообщение не найдено: " . $messageKey);
            return;
        }
        
        // Применение замен
        $processedMessage = $this->processMessage($message, $replacements);
        
        $sender->sendMessage($processedMessage);
    }
    
    /**
     * Отправить сырое сообщение без префикса
     */
    public function sendRaw(CommandSender $sender, string $message): void {
        if (empty($message)) {
            return;
        }
        $sender->sendMessage($message);
    }
    
    /**
     * Отправить заголовок (title) игроку
     */
    public function sendTitle(Player $player, string $titleKey): void {
        $titleData = $this->messagesConfig->getNested("welcome." . $titleKey);
        
        if (!is_array($titleData)) {
            return;
        }
        
        $config = $this->plugin->getConfig();
        
        // Настройки визуальных эффектов
        $fadeIn = $config->getNested("visual.title-fade-in", 10);
        $stay = $config->getNested("visual.title-stay", 70);
        $fadeOut = $config->getNested("visual.title-fade-out", 20);
        
        foreach ($titleData as $line) {
            if (empty($line)) {
                continue;
            }
            
            // Обработка линий с разделителями
            if (strpos($line, "§m--") !== false) {
                $player->sendTip($line);
            } else {
                $player->sendMessage($line);
            }
        }
    }
    
    /**
     * Отправить приветственное сообщение при входе
     */
    public function sendWelcomeMessage(Player $player, bool $isRegistered): void {
        $config = $this->plugin->getConfig();
        $delay = $config->getNested("visual.welcome-delay", 20);
        
        $this->plugin->getScheduler()->scheduleDelayedTask(
            new class($this, $player, $isRegistered) extends \pocketmine\scheduler\Task {
                private MessageManager $messageManager;
                private Player $player;
                private bool $isRegistered;
                
                public function __construct(MessageManager $messageManager, Player $player, bool $isRegistered) {
                    $this->messageManager = $messageManager;
                    $this->player = $player;
                    $this->isRegistered = $isRegistered;
                }
                
                public function onRun(int $currentTick): void {
                    if ($this->isRegistered) {
                        $this->messageManager->sendWelcomeLines(
                            $this->player, 
                            "registered-player",
                            ["{PLAYER}" => $this->player->getName()]
                        );
                    } else {
                        $this->messageManager->sendWelcomeLines(
                            $this->player, 
                            "new-player",
                            ["{PLAYER}" => $this->player->getName()]
                        );
                    }
                }
            },
            $delay
        );
    }
    
    /**
     * Отправить многострочное приветствие
     */
    public function sendWelcomeLines(Player $player, string $section, array $replacements): void {
        $lines = $this->messagesConfig->getNested("welcome." . $section, []);
        
        if (!is_array($lines)) {
            return;
        }
        
        foreach ($lines as $line) {
            if ($line === "") {
                $player->sendMessage("");
            } else {
                $processedLine = $this->processMessage($line, $replacements);
                $player->sendMessage($processedLine);
            }
        }
    }
    
    /**
     * Получить сообщение по ключу
     */
    private function getMessage(string $key): ?string {
        $parts = explode(".", $key);
        $value = $this->messagesConfig->getAll();
        
        foreach ($parts as $part) {
            if (!isset($value[$part])) {
                return null;
            }
            $value = $value[$part];
        }
        
        if (!is_string($value)) {
            return null;
        }
        
        return $value;
    }
    
    /**
     * Обработать сообщение с заменами
     */
    private function processMessage(string $message, array $replacements): string {
        // Добавление префикса если его нет
        if (strpos($message, "§8[§6") === false && strpos($message, $this->prefix) === false) {
            $message = $this->prefix . " " . $message;
        }
        
        // Применение замен
        foreach ($replacements as $placeholder => $replacement) {
            $message = str_replace($placeholder, $replacement, $message);
        }
        
        return $message;
    }
}
