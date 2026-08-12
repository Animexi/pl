<?php

declare(strict_types=1);

namespace ServerAuth\model;

/**
 * Состояния авторизации игрока
 */
class AuthState {
    
    public const UNREGISTERED = 0;
    public const REGISTERED_NOT_LOGGED = 1;
    public const LOGGED_IN = 2;
    
    /**
     * Получить строковое представление состояния
     */
    public static function toString(int $state): string {
        return match($state) {
            self::UNREGISTERED => "UNREGISTERED",
            self::REGISTERED_NOT_LOGGED => "REGISTERED_NOT_LOGGED",
            self::LOGGED_IN => "LOGGED_IN",
            default => "UNKNOWN"
        };
    }
}
