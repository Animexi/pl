<?php

namespace LiteAuth\util;

/**
 * Менеджер паролей - безопасное хеширование
 * Совместимо с PHP 7.0+ и старыми версиями PocketMine
 */
class PasswordManager {
    
    /**
     * Создать случайную соль
     * @return string
     */
    public function generateSalt() {
        // Используем random_bytes если доступен (PHP 7+)
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(16));
        }
        
        // Фоллбэк для старых версий
        return bin2hex(mcrypt_create_iv(16, MCRYPT_DEV_URANDOM));
    }
    
    /**
     * Хеширование пароля с солью (SHA-256)
     * @param string $password
     * @param string $salt
     * @return string
     */
    public function hashPassword($password, $salt) {
        // Комбинируем соль с паролем для дополнительной защиты
        return hash('sha256', $salt . $password . $salt);
    }
    
    /**
     * Проверка пароля с защитой от timing-атак
     * @param string $password Введённый пароль
     * @param string $storedHash Сохранённый хеш
     * @param string $salt Соль
     * @return bool
     */
    public function verifyPassword($password, $storedHash, $salt) {
        $computedHash = $this->hashPassword($password, $salt);
        
        // Используем hash_equals для защиты от timing-атак
        if (function_exists('hash_equals')) {
            return hash_equals($storedHash, $computedHash);
        }
        
        // Фоллбэк для очень старых версий PHP
        return $storedHash === $computedHash;
    }
    
    /**
     * Проверка сложности пароля
     * @param string $password
     * @param int $minLength
     * @param int $maxLength
     * @return bool
     */
    public function isPasswordValid($password, $minLength, $maxLength) {
        $length = strlen($password);
        
        // Проверка длины
        if ($length < $minLength || $length > $maxLength) {
            return false;
        }
        
        // Проверка на пустой пароль или только пробелы
        if (trim($password) === '') {
            return false;
        }
        
        // Проверка на недопустимые символы (управляющие символы)
        for ($i = 0; $i < $length; $i++) {
            $char = ord($password[$i]);
            // Разрешаем печатные ASCII символы (32-126)
            if ($char < 32 || $char > 126) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Проверка содержит ли пароль запрещённые символы
     * @param string $password
     * @return bool
     */
    public function hasInvalidCharacters($password) {
        // Запрещаем управляющие символы и специальные Unicode
        return preg_match('/[\x00-\x1F\x7F]/', $password) === 1;
    }
    
    /**
     * Нормализация ника игрока для хранения
     * @param string $username
     * @return string
     */
    public function normalizeUsername($username) {
        // Приводим к нижнему регистру для единообразия
        return strtolower(trim($username));
    }
}
