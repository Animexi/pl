<?php

namespace LiteAuth\model;

/**
 * Состояния авторизации игрока
 */
class AuthState {
    
    /** Игрок не зарегистрирован */
    const UNREGISTERED = 0;
    
    /** Игрок зарегистрирован, но не авторизован */
    const AUTH_REQUIRED = 1;
    
    /** Игрок проходит капчу после регистрации */
    const CAPTCHA_REQUIRED = 2;
    
    /** Игрок авторизован */
    const LOGGED_IN = 3;
    
    /** Информация о блокировке чата уже показана */
    const CHAT_BLOCKED_INFO = 4;
    
    /**
     * Получить строковое представление состояния
     * @param int $state
     * @return string
     */
    public static function toString($state) {
        switch ($state) {
            case self::UNREGISTERED:
                return "UNREGISTERED";
            case self::AUTH_REQUIRED:
                return "AUTH_REQUIRED";
            case self::CAPTCHA_REQUIRED:
                return "CAPTCHA_REQUIRED";
            case self::LOGGED_IN:
                return "LOGGED_IN";
            case self::CHAT_BLOCKED_INFO:
                return "CHAT_BLOCKED_INFO";
            default:
                return "UNKNOWN";
        }
    }
}
