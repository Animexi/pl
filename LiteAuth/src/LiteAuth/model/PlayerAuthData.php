<?php

namespace LiteAuth\model;

/**
 * Данные авторизации игрока
 */
class PlayerAuthData {
    
    /** @var string Имя игрока */
    private $username;
    
    /** @var string UUID игрока */
    private $uuid;
    
    /** @var string Хеш пароля */
    private $passwordHash;
    
    /** @var string Соль */
    private $salt;
    
    /** @var int Время регистрации (timestamp) */
    private $createdAt;
    
    /** @var int Последняя авторизация (timestamp) */
    private $lastLogin;
    
    /** @var int Количество неудачных попыток */
    private $failedAttempts;
    
    /** @var int Время блокировки (timestamp) */
    private $lockedUntil;
    
    /** @var string Последний IP (если включено) */
    private $lastIp;
    
    /** @var int Время создания сессии */
    private $sessionCreatedAt;
    
    public function __construct($username, $uuid, $passwordHash, $salt, $createdAt = 0, $lastLogin = 0, $failedAttempts = 0, $lockedUntil = 0) {
        $this->username = $username;
        $this->uuid = $uuid;
        $this->passwordHash = $passwordHash;
        $this->salt = $salt;
        $this->createdAt = $createdAt > 0 ? $createdAt : time();
        $this->lastLogin = $lastLogin;
        $this->failedAttempts = $failedAttempts;
        $this->lockedUntil = $lockedUntil;
        $this->lastIp = "";
        $this->sessionCreatedAt = 0;
    }
    
    public function getUsername() {
        return $this->username;
    }
    
    public function getUuid() {
        return $this->uuid;
    }
    
    public function getPasswordHash() {
        return $this->passwordHash;
    }
    
    public function getSalt() {
        return $this->salt;
    }
    
    public function getCreatedAt() {
        return $this->createdAt;
    }
    
    public function getLastLogin() {
        return $this->lastLogin;
    }
    
    public function setLastLogin($time) {
        $this->lastLogin = $time;
    }
    
    public function getFailedAttempts() {
        return $this->failedAttempts;
    }
    
    public function setFailedAttempts($count) {
        $this->failedAttempts = $count;
    }
    
    public function incrementFailedAttempts() {
        $this->failedAttempts++;
    }
    
    public function resetFailedAttempts() {
        $this->failedAttempts = 0;
    }
    
    public function getLockedUntil() {
        return $this->lockedUntil;
    }
    
    public function setLockedUntil($timestamp) {
        $this->lockedUntil = $timestamp;
    }
    
    public function isLocked() {
        return $this->lockedUntil > time();
    }
    
    public function setPassword($newHash, $newSalt) {
        $this->passwordHash = $newHash;
        $this->salt = $newSalt;
    }
    
    public function getLastIp() {
        return $this->lastIp;
    }
    
    public function setLastIp($ip) {
        $this->lastIp = $ip;
    }
    
    public function getSessionCreatedAt() {
        return $this->sessionCreatedAt;
    }
    
    public function setSessionCreatedAt($time) {
        $this->sessionCreatedAt = $time;
    }
    
    public function isSessionExpired($lifetime) {
        if ($lifetime <= 0) {
            return false; // Бессрочная сессия
        }
        return ($this->sessionCreatedAt + $lifetime) < time();
    }
    
    /**
     * Сериализация в массив для сохранения
     * @return array
     */
    public function toArray() {
        return array(
            "username" => $this->username,
            "uuid" => $this->uuid,
            "password_hash" => $this->passwordHash,
            "salt" => $this->salt,
            "created_at" => $this->createdAt,
            "last_login" => $this->lastLogin,
            "failed_attempts" => $this->failedAttempts,
            "locked_until" => $this->lockedUntil,
            "last_ip" => $this->lastIp,
            "session_created_at" => $this->sessionCreatedAt
        );
    }
    
    /**
     * Десериализация из массива
     * @param array $data
     * @return PlayerAuthData|null
     */
    public static function fromArray($data) {
        if (!isset($data["username"], $data["uuid"], $data["password_hash"], $data["salt"])) {
            return null;
        }
        
        $authData = new self(
            $data["username"],
            $data["uuid"],
            $data["password_hash"],
            $data["salt"],
            isset($data["created_at"]) ? $data["created_at"] : 0,
            isset($data["last_login"]) ? $data["last_login"] : 0,
            isset($data["failed_attempts"]) ? $data["failed_attempts"] : 0,
            isset($data["locked_until"]) ? $data["locked_until"] : 0
        );
        
        if (isset($data["last_ip"])) {
            $authData->setLastIp($data["last_ip"]);
        }
        
        if (isset($data["session_created_at"])) {
            $authData->setSessionCreatedAt($data["session_created_at"]);
        }
        
        return $authData;
    }
}
