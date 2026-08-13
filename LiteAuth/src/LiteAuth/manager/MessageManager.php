<?php

declare(strict_types=1);

namespace LiteAuth\manager;

use LiteAuth\LiteAuthPlugin;
use pocketmine\Player;

class MessageManager {

    private $plugin;

    public function __construct(LiteAuthPlugin $plugin) {
        $this->plugin = $plugin;
    }

    public function getPrefix(): string {
        return "§e§lLITE§f§lAUTH §8┃ ";
    }

    public function sendWelcome(Player $player) {
        $messages = [
            "╔══════════════════════════════╗",
            "║       §e§lLITEAUTH",
            "║",
            "║ §fДобро пожаловать на сервер.",
            "║ §7Для продолжения необходимо",
            "║ §7создать аккаунт.",
            "║",
            "║ §e/register <пароль> <пароль>",
            "║",
            "║ §7Пример: §f/register password password",
            "╚══════════════════════════════╝"
        ];
        foreach ($messages as $msg) {
            $player->sendMessage($msg);
        }
    }

    public function sendLoginRequest(Player $player) {
        $messages = [
            "╔══════════════════════════════╗",
            "║ §e§lLITEAUTH",
            "║",
            "║ §fАккаунт найден.",
            "║ §7Введите пароль для входа.",
            "║",
            "║ §e/login <пароль>",
            "╚══════════════════════════════╝"
        ];
        foreach ($messages as $msg) {
            $player->sendMessage($msg);
        }
    }

    public function sendRegisterSuccess(Player $player) {
        $messages = [
            "╔══════════════════════════════╗",
            "║ §e§lLITEAUTH",
            "║",
            "║ §aАккаунт успешно создан.",
            "║",
            "║ §7Теперь необходимо пройти",
            "║ §7небольшую проверку.",
            "╚══════════════════════════════╝"
        ];
        foreach ($messages as $msg) {
            $player->sendMessage($msg);
        }
    }

    public function sendCaptcha(Player $player, string $captcha) {
        $messages = [
            "╔══════════════════════════════╗",
            "║ §e§lПРОВЕРКА",
            "║",
            "║ §fРешите пример:",
            "║",
            "║ §e§l$captcha",
            "║",
            "║ §7Ответ: §e/captcha <число>",
            "╚══════════════════════════════╝"
        ];
        foreach ($messages as $msg) {
            $player->sendMessage($msg);
        }
    }

    public function sendCaptchaSuccess(Player $player) {
        $messages = [
            "╔══════════════════════════════╗",
            "║ §e§lLITEAUTH",
            "║",
            "║ §aПроверка успешно пройдена.",
            "║ §7Аккаунт готов к использованию.",
            "╚══════════════════════════════╝"
        ];
        foreach ($messages as $msg) {
            $player->sendMessage($msg);
        }
    }

    public function sendCaptchaFailed(Player $player) {
        $player->sendMessage($this->getPrefix() . "§cНеверный ответ. Попробуйте ещё раз.");
    }

    public function sendLoginSuccess(Player $player) {
        $name = $player->getName();
        $messages = [
            "╔══════════════════════════════╗",
            "║ §e§lLITEAUTH",
            "║",
            "║ §aАвторизация выполнена.",
            "║ §7Добро пожаловать, §f$name§7.",
            "╚══════════════════════════════╝"
        ];
        foreach ($messages as $msg) {
            $player->sendMessage($msg);
        }
    }

    public function sendAutoLogin(Player $player) {
        $player->sendMessage($this->getPrefix() . "§aАвтоматическая авторизация выполнена.");
    }

    public function sendAlreadyRegistered(Player $player) {
        $player->sendMessage($this->getPrefix() . "§cЭтот аккаунт уже зарегистрирован.");
        $player->sendMessage($this->getPrefix() . "§7Используйте §e/login <пароль>§7.");
    }

    public function sendNotRegistered(Player $player) {
        $player->sendMessage($this->getPrefix() . "§cАккаунт не зарегистрирован.");
        $player->sendMessage($this->getPrefix() . "§7Используйте §e/register <пароль> <пароль>§7.");
    }

    public function sendWrongPassword(Player $player) {
        $player->sendMessage($this->getPrefix() . "§cНеверный пароль.");
        $maxAttempts = $this->plugin->getConfigValue("max-login-attempts", 5);
        $attempts = $this->plugin->getAuthManager()->getLoginAttempts($player);
        $player->sendMessage($this->getPrefix() . "§7Попыток осталось: §e" . ($maxAttempts - $attempts));
    }

    public function sendTooManyAttempts(Player $player) {
        $player->sendMessage($this->getPrefix() . "§cСлишком много неудачных попыток.");
    }

    public function sendAuthTimeout(Player $player) {
        $player->sendMessage($this->getPrefix() . "§cВремя авторизации истекло.");
    }

    public function sendCaptchaTimeout(Player $player) {
        $player->sendMessage($this->getPrefix() . "§cВремя проверки истекло.");
    }

    public function sendNeedAuth(Player $player) {
        $player->sendMessage($this->getPrefix() . "§cСначала необходимо авторизоваться.");
    }

    public function sendInvalidCommand(Player $player, string $usage) {
        $messages = [
            "╔══════════════════════════════╗",
            "║ §e§lLITEAUTH",
            "║",
            "║ §cНеверный формат команды.",
            "║",
            "║ §7Используйте:",
            "║ §e$usage",
            "╚══════════════════════════════╝"
        ];
        foreach ($messages as $msg) {
            $player->sendMessage($msg);
        }
    }

    public function sendPasswordMismatch(Player $player) {
        $player->sendMessage($this->getPrefix() . "§cПароли не совпадают.");
    }

    public function sendPasswordTooShort(Player $player, int $min) {
        $player->sendMessage($this->getPrefix() . "§cПароль слишком короткий. Минимум $min символов.");
    }

    public function sendPasswordTooLong(Player $player, int $max) {
        $player->sendMessage($this->getPrefix() . "§cПароль слишком длинный. Максимум $max символов.");
    }

    public function sendSimplePassword(Player $player) {
        $player->sendMessage($this->getPrefix() . "§cЭтот пароль слишком простой. Выберите другой.");
    }

    public function sendRegistrationDisabled(Player $player) {
        $player->sendMessage($this->getPrefix() . "§cРегистрация в данный момент отключена.");
    }

    public function sendMaxRegistrations(Player $player) {
        $player->sendMessage($this->getPrefix() . "§cПревышено максимальное количество регистраций с вашего IP.");
    }

    public function sendReloadSuccess(Player $sender) {
        $sender->sendMessage($this->getPrefix() . "§aКонфигурация успешно перезагружена.");
    }

    public function sendReloadFailed(Player $sender) {
        $sender->sendMessage($this->getPrefix() . "§cНе удалось загрузить конфигурацию.");
    }

    public function sendPlayerInfo(Player $sender, string $target, bool $registered, bool $authenticated, bool $hasSession) {
        $regStatus = $registered ? "§aЗарегистрирован" : "§cНе зарегистрирован";
        $authStatus = $authenticated ? "§aАвторизован" : "§cНе авторизован";
        $sessionStatus = $hasSession ? "§aЕсть" : "§cНет";
        
        $messages = [
            "╔══════════════════════════════╗",
            "║ §e§lLITEAUTH",
            "║",
            "║ §7Информация об игроке §f$target",
            "║",
            "║ §7Аккаунт: $regStatus",
            "║ §7Сессия: $sessionStatus",
            "║ §7Статус: $authStatus",
            "╚══════════════════════════════╝"
        ];
        foreach ($messages as $msg) {
            $sender->sendMessage($msg);
        }
    }

    public function sendPlayerUnregistered(Player $sender, string $target) {
        $sender->sendMessage($this->getPrefix() . "§aАккаунт игрока §f$target §aудалён.");
    }

    public function sendPasswordChanged(Player $sender, string $target) {
        $sender->sendMessage($this->getPrefix() . "§aПароль игрока §f$target §aизменён.");
    }

    public function sendPlayerLoggedOut(Player $sender, string $target) {
        $sender->sendMessage($this->getPrefix() . "§aИгрок §f$target §aвышел из системы.");
    }

    public function sendNoPermission(Player $sender) {
        $sender->sendMessage($this->getPrefix() . "§cУ вас нет прав для выполнения этой команды.");
    }

    public function sendPlayerNotFound(Player $sender, string $target) {
        $sender->sendMessage($this->getPrefix() . "§cИгрок §f$target §cне найден.");
    }
}
