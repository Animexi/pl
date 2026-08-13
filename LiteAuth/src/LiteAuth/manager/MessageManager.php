<?php

declare(strict_types=1);

namespace LiteAuth\manager;

use LiteAuth\LiteAuthPlugin;
use pocketmine\Player;
use pocketmine\utils\Config;

class MessageManager {

    private $plugin;
    private $messages;

    public function __construct(LiteAuthPlugin $plugin) {
        $this->plugin = $plugin;
        $this->messages = $plugin->getMessages();
    }

    public function get(string $key, array $vars = []): string {
        $msg = $this->messages->get($key);
        
        if ($msg === null) {
            return "§cMessage not found: " . $key;
        }

        foreach ($vars as $var => $value) {
            $msg = str_replace("{" . $var . "}", (string)$value, $msg);
        }

        return $msg;
    }

    public function send(Player $player, string $key, array $vars = []) {
        $msg = $this->get($key, $vars);
        if (!empty($msg)) {
            $player->sendMessage($msg);
        }
    }

    public function sendRaw(Player $player, string $message) {
        $player->sendMessage($message);
    }
}
