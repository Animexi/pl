<?php

declare(strict_types=1);

namespace ServerAuth\util;

use pocketmine\utils\Config;
use ServerAuth\ServerAuthPlugin;

/**
 * Менеджер сообщений плагина
 */
class MessageManager {
    
    private ServerAuthPlugin $plugin;
    private Config $messages;
    
    public function __construct(ServerAuthPlugin $plugin) {
        $this->plugin = $plugin;
        
        // Создание messages.yml если не существует
        $plugin->saveResource("messages.yml", false);
        $this->messages = new Config($plugin->getDataFolder() . "messages.yml", Config::YAML);
    }
    
    /**
     * Перезагрузка сообщений
     */
    public function reload(): void {
        $this->messages->reload();
    }
    
    /**
     * Получить сообщение по ключу с заменой переменных
     * 
     * @param string $key Ключ сообщения
     * @param array $vars Переменные для замены ({PLAYER}, {PREFIX} и т.д.)
     */
    public function getMessage(string $key, array $vars = []): string {
        $message = (string) $this->messages->get($key, "§cMessage not found: {$key}");
        
        // Замена стандартных переменных
        foreach ($vars as $var => $value) {
            $message = str_replace("{" . $var . "}", (string) $value, $message);
        }
        
        return $message;
    }
    
    /**
     * Отправить сообщение игроку
     */
    public function send(object $player, string $key, array $vars = []): void {
        $message = $this->getMessage($key, $vars);
        
        if ($player instanceof \pocketmine\Player) {
            $player->sendMessage($message);
        } elseif ($player instanceof \pocketmine\command\CommandSender) {
            $player->sendMessage($message);
        }
    }
    
    /**
     * Отправить сообщение всем игрокам
     */
    public function broadcast(string $key, array $vars = []): void {
        $message = $this->getMessage($key, $vars);
        $this->plugin->getServer()->broadcastMessage($message);
    }
    
    // ==================== ПРЕФИКС ====================
    
    public function getPrefix(): string {
        return (string) $this->messages->get("prefix", "§8[§6Server§8]");
    }
    
    // ==================== ПРИВЕТСТВЕННЫЕ СООБЩЕНИЯ ====================
    
    public function getWelcomeNew(string $playerName): string {
        $prefix = $this->getPrefix();
        return str_replace(
            ["{PLAYER}"],
            [$playerName],
            $this->messages->get("welcome.new", <<<EOT
{$prefix} §fДобро пожаловать на сервер.

§7Аккаунт: §6{PLAYER}

§fДля начала зарегистрируйтесь:
§6/register <пароль> <повтор>
EOT
            )
        );
    }
    
    public function getWelcomeRegistered(string $playerName): string {
        $prefix = $this->getPrefix();
        return str_replace(
            ["{PLAYER}"],
            [$playerName],
            $this->messages->get("welcome.registered", <<<EOT
{$prefix} §fДобро пожаловать обратно.

§7Аккаунт: §6{PLAYER}

§fДля продолжения авторизуйтесь:
§6/login <пароль>
EOT
            )
        );
    }
    
    public function getAuthorized(string $playerName): string {
        $prefix = $this->getPrefix();
        return str_replace(
            ["{PLAYER}"],
            [$playerName],
            $this->messages->get("authorized", <<<EOT
{$prefix} §aАвторизация успешно выполнена.
§fПриятной игры, §6{PLAYER}§f.
EOT
            )
        );
    }
    
    // ==================== РЕГИСТРАЦИЯ ====================
    
    public function getRegisterUsage(): string {
        $prefix = $this->getPrefix();
        return $this->messages->get("register.usage", "{$prefix} §fДля регистрации используйте:\n§6/register <пароль> <повтор>");
    }
    
    public function getRegisterSuccess(): string {
        $prefix = $this->getPrefix();
        return $this->messages->get("register.success", "{$prefix} §aРегистрация успешно завершена.");
    }
    
    public function getRegisterAlreadyRegistered(): string {
        $prefix = $this->getPrefix();
        return $this->messages->get("register.already_registered", "{$prefix} §cИгрок с таким именем уже зарегистрирован.");
    }
    
    public function getRegisterPasswordsNotMatch(): string {
        $prefix = $this->getPrefix();
        return $this->messages->get("register.passwords_not_match", "{$prefix} §cПароли не совпадают.");
    }
    
    public function getRegisterPasswordTooShort(int $minLength): string {
        $prefix = $this->getPrefix();
        return str_replace(
            ["{MIN_LENGTH}"],
            [$minLength],
            $this->messages->get("register.password_too_short", "{$prefix} §cПароль слишком короткий. Минимум {MIN_LENGTH} символов.")
        );
    }
    
    public function getRegisterPasswordTooLong(int $maxLength): string {
        $prefix = $this->getPrefix();
        return str_replace(
            ["{MAX_LENGTH}"],
            [$maxLength],
            $this->messages->get("register.password_too_long", "{$prefix} §cПароль слишком длинный. Максимум {MAX_LENGTH} символов.")
        );
    }
    
    public function getRegisterEmptyPassword(): string {
        $prefix = $this->getPrefix();
        return $this->messages->get("register.empty_password", "{$prefix} §cПароль не может быть пустым.");
    }
    
    // ==================== АВТОРИЗАЦИЯ ====================
    
    public function getLoginUsage(): string {
        $prefix = $this->getPrefix();
        return $this->messages->get("login.usage", "{$prefix} §fДля входа используйте:\n§6/login <пароль>");
    }
    
    public function getLoginSuccess(string $playerName): string {
        $prefix = $this->getPrefix();
        return str_replace(
            ["{PLAYER}"],
            [$playerName],
            $this->messages->get("login.success", "{$prefix} §aАвторизация выполнена успешно.")
        );
    }
    
    public function getLoginWrongPassword(): string {
        $prefix = $this->getPrefix();
        return $this->messages->get("login.wrong_password", "{$prefix} §cНеверный пароль.");
    }
    
    public function getLoginNotRegistered(): string {
        $prefix = $this->getPrefix();
        return $this->messages->get("login.not_registered", "{$prefix} §cВы не зарегистрированы. Используйте /register.");
    }
    
    public function getLoginAlreadyLoggedIn(): string {
        $prefix = $this->getPrefix();
        return $this->messages->get("login.already_logged_in", "{$prefix} §eВы уже авторизованы.");
    }
    
    public function getLoginTooManyAttempts(): string {
        $prefix = $this->getPrefix();
        return $this->messages->get("login.too_many_attempts", "{$prefix} §cСлишком много попыток. Повторите позже.");
    }
    
    public function getLoginCooldown(int $seconds): string {
        $prefix = $this->getPrefix();
        return str_replace(
            ["{SECONDS}"],
            [$seconds],
            $this->messages->get("login.cooldown", "{$prefix} §eПодождите {SECONDS} сек. перед следующей попыткой.")
        );
    }
    
    public function getLoginLocked(int $minutes): string {
        $prefix = $this->getPrefix();
        return str_replace(
            ["{MINUTES}"],
            [$minutes],
            $this->messages->get("login.locked", "{$prefix} §cАккаунт заблокирован на {MINUTES} мин. из-за множества неудачных попыток.")
        );
    }
    
    // ==================== СМЕНА ПАРОЛЯ ====================
    
    public function getChangePasswordUsage(): string {
        $prefix = $this->getPrefix();
        return $this->messages->get("changepassword.usage", "{$prefix} §fДля смены пароля используйте:\n§6/changepassword <старый> <новый>");
    }
    
    public function getChangePasswordSuccess(): string {
        $prefix = $this->getPrefix();
        return $this->messages->get("changepassword.success", "{$prefix} §aПароль успешно изменён.");
    }
    
    public function getChangePasswordWrongOld(): string {
        $prefix = $this->getPrefix();
        return $this->messages->get("changepassword.wrong_old", "{$prefix} §cНеверный старый пароль.");
    }
    
    public function getChangePasswordSameAsOld(): string {
        $prefix = $this->getPrefix();
        return $this->messages->get("changepassword.same_as_old", "{$prefix} §cНовый пароль совпадает со старым.");
    }
    
    // ==================== ЗАЩИТА ====================
    
    public function getProtectionMove(): string {
        $prefix = $this->getPrefix();
        return $this->messages->get("protection.move", "{$prefix} §cАвторизуйтесь для перемещения.");
    }
    
    public function getProtectionInteract(): string {
        $prefix = $this->getPrefix();
        return $this->messages->get("protection.interact", "{$prefix} §cАвторизуйтесь для взаимодействия.");
    }
    
    public function getProtectionDamage(): string {
        $prefix = $this->getPrefix();
        return $this->messages->get("protection.damage", "{$prefix} §cАвторизуйтесь для получения/нанесения урона.");
    }
    
    public function getProtectionDrop(): string {
        $prefix = $this->getPrefix();
        return $this->messages->get("protection.drop", "{$prefix} §cАвторизуйтесь для выброса предметов.");
    }
    
    public function getProtectionCommand(): string {
        $prefix = $this->getPrefix();
        return $this->messages->get("protection.command", "{$prefix} §cАвторизуйтесь для использования команд.");
    }
    
    // ==================== АДМИНИСТРАТИВНЫЕ ====================
    
    public function getAdminReloadSuccess(): string {
        $prefix = $this->getPrefix();
        return $this->messages->get("admin.reload_success", "{$prefix} §aКонфигурация перезагружена.");
    }
    
    public function getAdminUnregisterSuccess(string $playerName): string {
        $prefix = $this->getPrefix();
        return str_replace(
            ["{PLAYER}"],
            [$playerName],
            $this->messages->get("admin.unregister_success", "{$prefix} §aАккаунт игрока {PLAYER} удалён.")
        );
    }
    
    public function getAdminUnregisterNotFound(): string {
        $prefix = $this->getPrefix();
        return $this->messages->get("admin.unregister_not_found", "{$prefix} §cАккаунт не найден.");
    }
    
    public function getAdminInfoHeader(string $playerName): string {
        $prefix = $this->getPrefix();
        return str_replace(
            ["{PLAYER}"],
            [$playerName],
            $this->messages->get("admin.info_header", "{$prefix} §fИнформация об аккаунте §6{PLAYER}")
        );
    }
    
    public function getAdminInfoRegistered(string $status): string {
        $prefix = $this->getPrefix();
        return str_replace(
            ["{STATUS}"],
            [$status],
            $this->messages->get("admin.info_registered", "{$prefix} §7Зарегистрирован: §6{STATUS}")
        );
    }
    
    public function getAdminInfoLastLogin(string $lastLogin): string {
        $prefix = $this->getPrefix();
        return str_replace(
            ["{LAST_LOGIN}"],
            [$lastLogin],
            $this->messages->get("admin.info_last_login", "{$prefix} §7Последний вход: §6{LAST_LOGIN}")
        );
    }
    
    public function getAdminNoPermission(): string {
        $prefix = $this->getPrefix();
        return $this->messages->get("admin.no_permission", "{$prefix} §cУ вас нет прав для этой команды.");
    }
    
    // ==================== ОШИБКИ ====================
    
    public function getErrorPlayerNotFound(): string {
        $prefix = $this->getPrefix();
        return $this->messages->get("error.player_not_found", "{$prefix} §cИгрок не найден.");
    }
    
    public function getErrorInternal(): string {
        $prefix = $this->getPrefix();
        return $this->messages->get("error.internal", "{$prefix} §cПроизошла внутренняя ошибка.");
    }
}
