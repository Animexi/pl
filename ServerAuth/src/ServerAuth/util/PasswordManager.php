<?php

declare(strict_types=1);

namespace ServerAuth\util;

/**
 * Менеджер паролей - безопасное хеширование
 */
class PasswordManager {
    
    /**
     * Создать случайную соль
     */
    public function generateSalt(): string {
        return bin2hex(random_bytes(16));
    }
    
    /**
     * Хеширование пароля с солью (SHA-256)
     */
    public function hashPassword(string $password, string $salt): string {
        return hash('sha256', $salt . $password . $salt);
    }
    
    /**
     * Проверка пароля
     * 
     * @param string $password Введённый пароль
     * @param string $storedHash Сохранённый хеш
     * @param string $salt Соль
     */
    public function verifyPassword(string $password, string $storedHash, string $salt): bool {
        $computedHash = $this->hashPassword($password, $salt);
        return hash_equals($storedHash, $computedHash);
    }
    
    /**
     * Проверка сложности пароля
     */
    public function isPasswordValid(string $password, int $minLength, int $maxLength): bool {
        $length = strlen($password);
        return $length >= $minLength && $length <= $maxLength;
    }
}
