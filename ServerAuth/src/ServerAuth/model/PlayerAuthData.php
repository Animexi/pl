<?php

declare(strict_types=1);

namespace ServerAuth\model;

/**
 * Данные авторизации игрока
 */
class PlayerAuthData {
    
    private string $username;
    private string $uuid;
    private string $passwordHash;
    private string $salt;
    private int $createdAt;
    private int $lastLogin;
    private int $failedAttempts;
    private int $lockedUntil;
    
    public function __construct(
        string $username,
        string $uuid,
        string $passwordHash,
        string $salt,
        int $createdAt = 0,
        int $lastLogin = 0,
        int $failedAttempts = 0,
        int $lockedUntil = 0
    ) {
        $this->username = $username;
        $this->uuid = $uuid;
        $this->passwordHash = $passwordHash;
        $this->salt = $salt;
        $this->createdAt = $createdAt ?: time();
        $this->lastLogin = $lastLogin;
        $this->failedAttempts = $failedAttempts;
        $this->lockedUntil = $lockedUntil;
    }
    
    public function getUsername(): string {
        return $this->username;
    }
    
    public function getUuid(): string {
        return $this->uuid;
    }
    
    public function getPasswordHash(): string {
        return $this->passwordHash;
    }
    
    public function getSalt(): string {
        return $this->salt;
    }
    
    public function getCreatedAt(): int {
        return $this->createdAt;
    }
    
    public function getLastLogin(): int {
        return $this->lastLogin;
    }
    
    public function setLastLogin(int $time): void {
        $this->lastLogin = $time;
    }
    
    public function getFailedAttempts(): int {
        return $this->failedAttempts;
    }
    
    public function setFailedAttempts(int $count): void {
        $this->failedAttempts = $count;
    }
    
    public function incrementFailedAttempts(): void {
        $this->failedAttempts++;
    }
    
    public function resetFailedAttempts(): void {
        $this->failedAttempts = 0;
    }
    
    public function getLockedUntil(): int {
        return $this->lockedUntil;
    }
    
    public function setLockedUntil(int $timestamp): void {
        $this->lockedUntil = $timestamp;
    }
    
    public function isLocked(): bool {
        return $this->lockedUntil > time();
    }
    
    public function setPassword(string $newHash, string $newSalt): void {
        $this->passwordHash = $newHash;
        $this->salt = $newSalt;
    }
    
    /**
     * Сериализация в массив для сохранения
     */
    public function toArray(): array {
        return [
            "username" => $this->username,
            "uuid" => $this->uuid,
            "password_hash" => $this->passwordHash,
            "salt" => $this->salt,
            "created_at" => $this->createdAt,
            "last_login" => $this->lastLogin,
            "failed_attempts" => $this->failedAttempts,
            "locked_until" => $this->lockedUntil
        ];
    }
    
    /**
     * Десериализация из массива
     */
    public static function fromArray(array $data): ?PlayerAuthData {
        if (!isset($data["username"], $data["uuid"], $data["password_hash"], $data["salt"])) {
            return null;
        }
        
        return new self(
            $data["username"],
            $data["uuid"],
            $data["password_hash"],
            $data["salt"],
            $data["created_at"] ?? 0,
            $data["last_login"] ?? 0,
            $data["failed_attempts"] ?? 0,
            $data["locked_until"] ?? 0
        );
    }
}
