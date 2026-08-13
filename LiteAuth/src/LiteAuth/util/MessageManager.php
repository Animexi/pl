<?php

namespace LiteAuth\util;

use pocketmine\utils\Config;
use LiteAuth\LiteAuthPlugin;

/**
 * Менеджер сообщений плагина
 */
class MessageManager {
    
    /** @var LiteAuthPlugin */
    private $plugin;
    
    /** @var Config */
    private $messages;
    
    public function __construct(LiteAuthPlugin $plugin) {
        $this->plugin = $plugin;
        
        // Загрузка сообщений
        $this->messages = new Config($plugin->getDataFolder() . "messages.yml", Config::YAML);
    }
    
    /**
     * Перезагрузка сообщений
     */
    public function reload() {
        $this->messages->reload();
    }
    
    /**
     * Получить сообщение по ключу с заменой переменных
     * @param string $key Ключ сообщения
     * @param array $vars Переменные для замены
     * @return string
     */
    public function getMessage($key, $vars = array()) {
        $message = (string) $this->messages->get($key, "§cMessage not found: {$key}");
        
        // Замена переменных
        foreach ($vars as $var => $value) {
            $message = str_replace("{" . $var . "}", (string) $value, $message);
        }
        
        return $message;
    }
    
    /**
     * Отправить сообщение игроку
     * @param mixed $player
     * @param string $key
     * @param array $vars
     */
    public function send($player, $key, $vars = array()) {
        $message = $this->getMessage($key, $vars);
        
        if ($player instanceof \pocketmine\Player) {
            $player->sendMessage($message);
        } elseif ($player instanceof \pocketmine\command\CommandSender) {
            $player->sendMessage($message);
        }
    }
    
    /**
     * Получить префикс
     * @return string
     */
    public function getPrefix() {
        return (string) $this->messages->get("prefix", "§e§lLITE§f§lAUTH §8┃");
    }
    
    // ==================== ПРИВЕТСТВИЕ ====================
    
    public function getWelcomeNew() {
        return $this->getMessage("welcome.new");
    }
    
    public function getWelcomeRegistered() {
        return $this->getMessage("welcome.registered");
    }
    
    public function getAuthorized($playerName) {
        return $this->getMessage("authorized", array("player" => $playerName));
    }
    
    public function getAutoLogin() {
        return $this->getMessage("auto-login");
    }
    
    public function getSessionExpired() {
        return $this->getMessage("session-expired");
    }
    
    // ==================== РЕГИСТРАЦИЯ ====================
    
    public function getRegisterSuccess() {
        return $this->getMessage("register.success");
    }
    
    public function getRegisterAlreadyRegistered() {
        return $this->getMessage("register.already-registered");
    }
    
    public function getRegisterPasswordsNotMatch() {
        return $this->getMessage("register.passwords-not-match");
    }
    
    public function getRegisterPasswordTooShort($min) {
        return $this->getMessage("register.password-too-short", array("min" => $min));
    }
    
    public function getRegisterPasswordTooLong($max) {
        return $this->getMessage("register.password-too-long", array("max" => $max));
    }
    
    public function getRegisterEmptyPassword() {
        return $this->getMessage("register.empty-password");
    }
    
    public function getRegisterInvalidCharacters() {
        return $this->getMessage("register.invalid-characters");
    }
    
    public function getRegisterTooSimple() {
        return $this->getMessage("register.too-simple");
    }
    
    public function getRegisterDisabled() {
        return $this->getMessage("register.registration-disabled");
    }
    
    public function getRegisterMaxIpReached() {
        return $this->getMessage("register.max-ip-reached");
    }
    
    public function getRegisterUsage() {
        return $this->getMessage("register.usage");
    }
    
    // ==================== АВТОРИЗАЦИЯ ====================
    
    public function getLoginSuccess($playerName) {
        return $this->getMessage("login.success", array("player" => $playerName));
    }
    
    public function getLoginWrongPassword($attempts) {
        return $this->getMessage("login.wrong-password", array("attempts" => $attempts));
    }
    
    public function getLoginNotRegistered() {
        return $this->getMessage("login.not-registered");
    }
    
    public function getLoginAlreadyLoggedIn() {
        return $this->getMessage("login.already-logged-in");
    }
    
    public function getLoginTooManyAttempts() {
        return $this->getMessage("login.too-many-attempts");
    }
    
    public function getLoginCooldown($seconds) {
        return $this->getMessage("login.cooldown", array("seconds" => $seconds));
    }
    
    public function getLoginLocked($minutes) {
        return $this->getMessage("login.locked", array("minutes" => $minutes));
    }
    
    public function getLoginTimeout() {
        return $this->getMessage("login.timeout");
    }
    
    public function getLoginUsage() {
        return $this->getMessage("login.usage");
    }
    
    // ==================== КАПЧА ====================
    
    public function getCaptchaRequest($question) {
        return $this->getMessage("captcha.request", array("question" => $question));
    }
    
    public function getCaptchaSuccess() {
        return $this->getMessage("captcha.success");
    }
    
    public function getCaptchaWrongAnswer() {
        return $this->getMessage("captcha.wrong-answer");
    }
    
    public function getCaptchaTooManyAttempts() {
        return $this->getMessage("captcha.too-many-attempts");
    }
    
    public function getCaptchaTimeout() {
        return $this->getMessage("captcha.timeout");
    }
    
    public function getCaptchaCooldown($seconds) {
        return $this->getMessage("captcha.cooldown", array("seconds" => $seconds));
    }
    
    public function getCaptchaNotRequired() {
        return $this->getMessage("captcha.not-required");
    }
    
    public function getCaptchaUsage() {
        return $this->getMessage("captcha.usage");
    }
    
    // ==================== ЗАЩИТА ====================
    
    public function getProtectionMove() {
        return $this->getMessage("protection.move");
    }
    
    public function getProtectionInteract() {
        return $this->getMessage("protection.interact");
    }
    
    public function getProtectionDamage() {
        return $this->getMessage("protection.damage");
    }
    
    public function getProtectionDrop() {
        return $this->getMessage("protection.drop");
    }
    
    public function getProtectionCommand() {
        return $this->getMessage("protection.command");
    }
    
    public function getProtectionChat() {
        return $this->getMessage("protection.chat");
    }
    
    // ==================== АДМИНИСТРАТИВНЫЕ ====================
    
    public function getAdminReloadSuccess() {
        return $this->getMessage("admin.reload-success");
    }
    
    public function getAdminReloadError() {
        return $this->getMessage("admin.reload-error");
    }
    
    public function getAdminUnregisterSuccess($playerName) {
        return $this->getMessage("admin.unregister-success", array("player" => $playerName));
    }
    
    public function getAdminUnregisterNotFound() {
        return $this->getMessage("admin.unregister-not-found");
    }
    
    public function getAdminChangePasswordSuccess($playerName) {
        return $this->getMessage("admin.changepassword-success", array("player" => $playerName));
    }
    
    public function getAdminLogoutSuccess($playerName) {
        return $this->getMessage("admin.logout-success", array("player" => $playerName));
    }
    
    public function getAdminInfoHeader($playerName, $registered, $session, $autologin, $attempts, $maxAttempts, $lastLogin) {
        return $this->getMessage("admin.info-header", array(
            "player" => $playerName,
            "registered" => $registered,
            "session" => $session,
            "autologin" => $autologin,
            "attempts" => $attempts,
            "max_attempts" => $maxAttempts,
            "last_login" => $lastLogin
        ));
    }
    
    public function getAdminNoPermission() {
        return $this->getMessage("admin.no-permission");
    }
    
    public function getAdminUsage() {
        return $this->getMessage("admin.usage");
    }
    
    // ==================== /auth СТАТУС ====================
    
    public function getAuthStatus($status, $session, $autologin) {
        return $this->getMessage("auth-status", array(
            "status" => $status,
            "session" => $session,
            "autologin" => $autologin
        ));
    }
    
    // ==================== ОШИБКИ ====================
    
    public function getErrorPlayerNotFound() {
        return $this->getMessage("error.player-not-found");
    }
    
    public function getErrorInternal() {
        return $this->getMessage("error.internal");
    }
    
    public function getErrorConsoleOnly() {
        return $this->getMessage("error.console-only");
    }
    
    public function getErrorPlayerOnly() {
        return $this->getMessage("error.player-only");
    }
}
