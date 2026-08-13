<?php

namespace LiteAuth\model;

/**
 * Состояния авторизации игрока
 */
class AuthState {
    
    /** Игрок не зарегистрирован */
    const UNREGISTERED = 0;
    
    /** Игрок зарегистрирован, но не авторизован */
    const REGISTERED_NOT_LOGGED = 1;
    
    /** Игрок проходит капчу после регистрации */
    const CAPTCHA_REQUIRED = 2;
    
    /** Игрок авторизован */
    const LOGGED_IN = 3;
    
    /**
     * Получить строковое представление состояния
     * @param int $state
     * @return string
     */
    public static function toString($state) {
        switch ($state) {
            case self::UNREGISTERED:
                return "UNREGISTERED";
            case self::REGISTERED_NOT_LOGGED:
                return "REGISTERED_NOT_LOGGED";
            case self::CAPTCHA_REQUIRED:
                return "CAPTCHA_REQUIRED";
            case self::LOGGED_IN:
                return "LOGGED_IN";
            default:
                return "UNKNOWN";
        }
    }
}
