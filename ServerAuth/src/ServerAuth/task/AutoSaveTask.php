<?php

declare(strict_types=1);

namespace ServerAuth\task;

use pocketmine\scheduler\Task;
use ServerAuth\ServerAuthPlugin;

class AutoSaveTask extends Task {
    
    private ServerAuthPlugin $plugin;
    
    public function __construct(ServerAuthPlugin $plugin) {
        $this->plugin = $plugin;
    }
    
    public function onRun(int $currentTick): void {
        // Сохранение всех данных игроков из кэша
        $this->plugin->getStorageManager()->saveAllFromCache();
        
        // Логирование (опционально)
        // $this->plugin->getLogger()->debug("Автосохранение данных игроков выполнено.");
    }
}
