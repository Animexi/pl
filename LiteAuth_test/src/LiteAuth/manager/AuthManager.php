<?php

namespace LiteAuth\manager;

use pocketmine\Player;
use LiteAuth\LiteAuthPlugin;
use LiteAuth\model\AuthState;
use LiteAuth\model\PlayerAuthData;
use LiteAuth\storage\StorageManager;
use LiteAuth\util\ConfigManager;
use LiteAuth\util\MessageManager;
use LiteAuth\util\PasswordManager;

/**
 * Основной менеджер авторизации
 */
class AuthManager {
    
    /** @var LiteAuthPlugin */
    private $plugin;
    
    /** @var StorageManager */
    private $storageManager;
    
    /** @var ConfigManager */
    private $configManager;
    
    /** @var MessageManager */
    private $messageManager;
    
    /** @var PasswordManager */
    private $passwordManager;
    
    /** @var array Состояния игроков по UUID */
    private $playerStates = array();
    
    /** @var array Время последней попытки входа */
    private $lastAttemptTime = array();
    
    /** @var array Данные капчи игроков */
    private $captchaData = array();
    
    /** @var array Время подключения игроков (для timeout) */
    private $playerJoinTime = array();
    
    /** @var array Состояния игроков по имени (для совместимости) */
    private $playerStatesByName = array();
    
    public function __construct(
        LiteAuthPlugin $plugin,
        StorageManager $storageManager,
        ConfigManager $configManager,
        MessageManager $messageManager,
        PasswordManager $passwordManager
    ) {
        $this->plugin = $plugin;
        $this->storageManager = $storageManager;
        $this->configManager = $configManager;
        $this->messageManager = $messageManager;
        $this->passwordManager = $passwordManager;
    }
    
    /**
     * Получить состояние игрока по объекту Player
     * @param Player $player
     * @return int
     */
    public function getState(Player $player) {
        $uuid = $this->getPlayerUUID($player);
        return isset($this->playerStates[$uuid]) ? $this->playerStates[$uuid] : AuthState::UNREGISTERED;
    }
    
    /**
     * Получить состояние игрока по имени
     * @param string $playerName
     * @return int
     */
    public function getStateByName($playerName) {
        $normalizedName = strtolower($playerName);
        return isset($this->playerStatesByName[$normalizedName]) ? $this->playerStatesByName[$normalizedName] : AuthState::UNREGISTERED;
    }
    
    /**
     * Установить состояние игрока по объекту Player
     * @param Player $player
     * @param int $state
     */
    public function setState(Player $player, $state) {
        $uuid = $this->getPlayerUUID($player);
        $this->playerStates[$uuid] = $state;
        // Также сохраняем по имени для совместимости
        $this->playerStatesByName[strtolower($player->getName())] = $state;
    }
    
    /**
     * Установить состояние игрока по имени
     * @param string $playerName
     * @param int $state
     */
    public function setStateByName($playerName, $state) {
        $normalizedName = strtolower($playerName);
        $this->playerStatesByName[$normalizedName] = $state;
    }
    
    /**
     * Инициализировать игрока при подключении
     * @param Player $player
     */
    public function initializePlayer(Player $player) {
        $uuid = $this->getPlayerUUID($player);
        $playerName = $player->getName();
        
        // Сохраняем время подключения для timeout
        $this->playerJoinTime[$uuid] = time();
        
        // Проверка bypass permission
        if ($player->hasPermission("liteauth.bypass")) {
            $this->setState($player, AuthState::LOGGED_IN);
            return;
        }
        
        // Загрузка данных из хранилища
        $authData = $this->storageManager->load($playerName);
        
        if ($authData !== null) {
            // Игрок зарегистрирован
            $this->setState($player, AuthState::AUTH_REQUIRED);
            
            // Проверка блокировки
            if ($authData->isLocked()) {
                $lockTimeRemaining = ceil(($authData->getLockedUntil() - time()) / 60);
                $this->messageManager->sendBoxedMessage(
                    $player,
                    "§e§lLITEAUTH",
                    [
                        "§cАккаунт заблокирован.",
                        "§7Попробуйте через §f{$lockTimeRemaining} §7мин."
                    ]
                );
                $this->logSecurity("Игрок {$playerName} заблокирован на {$lockTimeRemaining} мин.");
                return;
            }
        } else {
            // Новый игрок
            $this->setState($player, AuthState::UNREGISTERED);
        }
    }
    
    /**
     * Очистить данные игрока при выходе
     * @param string $playerName
     */
    public function cleanupPlayer($playerName) {
        $normalizedName = strtolower($playerName);
        
        // Очистка состояний
        unset($this->playerStatesByName[$normalizedName]);
        
        // Очистка капчи
        unset($this->captchaData[$normalizedName]);
        
        // Очистка попыток
        unset($this->lastAttemptTime[$normalizedName]);
        
        // Очистка времени подключения
        unset($this->playerJoinTime[$normalizedName]);
    }
    
    /**
     * Сохранить сессию игрока
     * @param Player $player
     */
    public function savePlayerSession(Player $player) {
        $playerName = $player->getName();
        $authData = $this->storageManager->load($playerName);
        
        if ($authData !== null) {
            $authData->setLastLogin(time());
            $authData->setSessionCreatedAt(time());
            $authData->setLastIp($player->getAddress());
            $this->storageManager->save($authData);
        }
    }
    
    /**
     * Запустить таймер авторизации
     * @param Player $player
     */
    public function startAuthTimer(Player $player) {
        // Таймер будет проверяться периодически
        $this->playerJoinTime[$this->getPlayerUUID($player)] = time();
    }
    
    /**
     * Проверить авто-логин
     * @param Player $player
     * @return bool
     */
    public function checkAutoLogin(Player $player) {
        $playerName = $player->getName();
        
        if (!$this->configManager->isAutoLoginEnabled()) {
            return false;
        }
        
        $authData = $this->storageManager->load($playerName);
        
        if ($authData === null) {
            return false;
        }
        
        $lifetime = $this->configManager->getSessionLifetime();
        
        if ($authData->isSessionExpired($lifetime)) {
            return false;
        }
        
        // Проверка IP если включено
        if ($this->configManager->isSessionByIp()) {
            $playerIp = $player->getAddress();
            if ($playerIp !== $authData->getLastIp()) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Установить статус авторизации
     * @param string $playerName
     * @param bool $authenticated
     */
    public function setAuthenticated($playerName, $authenticated) {
        $normalizedName = strtolower($playerName);
        if ($authenticated) {
            $this->playerStatesByName[$normalizedName] = AuthState::LOGGED_IN;
        } else {
            $this->playerStatesByName[$normalizedName] = AuthState::AUTH_REQUIRED;
        }
    }
    
    /**
     * Проверить, зарегистрирован ли игрок
     * @param Player $player
     * @return bool
     */
    public function isRegistered(Player $player) {
        return $this->getState($player) !== AuthState::UNREGISTERED;
    }
    
    /**
     * Проверить, авторизован ли игрок
     * @param Player $player
     * @return bool
     */
    public function isLoggedIn(Player $player) {
        return $this->getState($player) === AuthState::LOGGED_IN;
    }
    
    /**
     * Проверить, требуется ли капча
     * @param Player $player
     * @return bool
     */
    public function isCaptchaRequired(Player $player) {
        return $this->getState($player) === AuthState::CAPTCHA_REQUIRED;
    }
    
    /**
     * Получить UUID игрока (совместимо со старыми API)
     * @param Player $player
     * @return string
     */
    private function getPlayerUUID(Player $player) {
        if (method_exists($player, 'getUniqueId')) {
            return $player->getUniqueId()->toString();
        }
        // Фоллбэк для очень старых версий
        return $player->getName();
    }
    
    /**
     * Загрузить данные игрока при подключении
     * @param Player $player
     */
    public function onPlayerJoin(Player $player) {
        $username = $player->getName();
        $uuid = $this->getPlayerUUID($player);
        
        // Сохраняем время подключения для timeout
        $this->playerJoinTime[$uuid] = time();
        
        // Проверка bypass permission
        if ($player->hasPermission("liteauth.bypass")) {
            $this->setState($player, AuthState::LOGGED_IN);
            return;
        }
        
        // Загрузка данных из хранилища
        $authData = $this->storageManager->load($username);
        
        if ($authData !== null) {
            // Игрок зарегистрирован
            $this->setState($player, AuthState::REGISTERED_NOT_LOGGED);
            
            // Проверка блокировки
            if ($authData->isLocked()) {
                $lockTimeRemaining = ceil(($authData->getLockedUntil() - time()) / 60);
                $this->messageManager->send($player, "login.locked", array("minutes" => $lockTimeRemaining));
                $this->logSecurity("Игрок {$username} заблокирован на {$lockTimeRemaining} мин.");
                return;
            }
            
            // Проверка авто-логина по сессии
            if ($this->configManager->isAutoLoginEnabled()) {
                $lifetime = $this->configManager->getSessionLifetime();
                
                if (!$authData->isSessionExpired($lifetime)) {
                    // Проверка IP если включено
                    if ($this->configManager->isSessionByIp()) {
                        $playerIp = $player->getAddress();
                        if ($playerIp === $authData->getLastIp()) {
                            $this->performAutoLogin($player, $authData);
                            return;
                        }
                    } else {
                        // Авто-логин без проверки IP
                        $this->performAutoLogin($player, $authData);
                        return;
                    }
                }
            }
            
            // Показать приветственное сообщение для зарегистрированного
            $this->messageManager->send($player, "welcome.registered");
            $this->logInfo("Игрок {$username} подключился (зарегистрирован, ожидает входа)");
            
        } else {
            // Новый игрок
            $this->setState($player, AuthState::UNREGISTERED);
            $this->messageManager->send($player, "welcome.new");
            $this->logInfo("Новый игрок подключился: {$username}");
        }
    }
    
    /**
     * Выполнить авто-логин
     * @param Player $player
     * @param PlayerAuthData $authData
     */
    private function performAutoLogin(Player $player, PlayerAuthData $authData) {
        $this->setState($player, AuthState::LOGGED_IN);
        $authData->setLastLogin(time());
        $this->storageManager->save($authData);
        
        $this->messageManager->send($player, "auto-login");
        $this->logInfo("Авто-логин для игрока " . $player->getName());
    }
    
    /**
     * Регистрация игрока
     * @param Player $player
     * @param string $password
     * @param string $confirmPassword
     * @return bool
     */
    public function register(Player $player, $password, $confirmPassword) {
        $username = $player->getName();
        $uuid = $this->getPlayerUUID($player);
        
        // Проверка состояния
        if ($this->getState($player) !== AuthState::UNREGISTERED) {
            $this->messageManager->send($player, "register.already-registered");
            return false;
        }
        
        // Проверка существования аккаунта
        if ($this->storageManager->exists($username)) {
            $this->messageManager->send($player, "register.already-registered");
            return false;
        }
        
        // Проверка включена ли регистрация
        if (!$this->configManager->isRegistrationEnabled()) {
            $this->messageManager->send($player, "register.registration-disabled");
            return false;
        }
        
        // Проверка лимита регистраций с IP
        $maxPerIp = $this->configManager->getMaxRegistrationsPerIp();
        if ($maxPerIp > 0) {
            $playerIp = $player->getAddress();
            $regCount = $this->countRegistrationsByIp($playerIp);
            if ($regCount >= $maxPerIp) {
                $this->messageManager->send($player, "register.max-ip-reached");
                return false;
            }
        }
        
        // Проверка паролей
        if ($password !== $confirmPassword) {
            $this->messageManager->send($player, "register.passwords-not-match");
            return false;
        }
        
        // Проверка длины пароля
        $minLength = $this->configManager->getMinPasswordLength();
        $maxLength = $this->configManager->getMaxPasswordLength();
        
        if (empty($password)) {
            $this->messageManager->send($player, "register.empty-password");
            return false;
        }
        
        if (!$this->passwordManager->isPasswordValid($password, $minLength, $maxLength)) {
            if (strlen($password) < $minLength) {
                $this->messageManager->send($player, "register.password-too-short", array("min" => $minLength));
            } else {
                $this->messageManager->send($player, "register.password-too-long", array("max" => $maxLength));
            }
            return false;
        }
        
        // Проверка на запрещённые символы
        if ($this->passwordManager->hasInvalidCharacters($password)) {
            $this->messageManager->send($player, "register.invalid-characters");
            return false;
        }
        
        // Проверка на простой пароль
        if ($this->configManager->isPasswordInBlacklist($password)) {
            $this->messageManager->send($player, "register.too-simple");
            return false;
        }
        
        // Создание аккаунта
        try {
            $salt = $this->passwordManager->generateSalt();
            $passwordHash = $this->passwordManager->hashPassword($password, $salt);
            
            $authData = new PlayerAuthData(
                $username,
                $uuid,
                $passwordHash,
                $salt,
                time(),
                0,
                0,
                0
            );
            
            $authData->setLastIp($player->getAddress());
            
            if ($this->storageManager->save($authData)) {
                // Если капча включена - переводим в состояние CAPTCHA_REQUIRED
                if ($this->configManager->isCaptchaEnabled()) {
                    $this->setState($player, AuthState::CAPTCHA_REQUIRED);
                    $this->generateCaptcha($player);
                    $this->messageManager->send($player, "register.success");
                } else {
                    $this->setState($player, AuthState::LOGGED_IN);
                    $this->messageManager->send($player, "register.success");
                    $this->messageManager->send($player, "authorized", array("player" => $username));
                }
                
                $this->logInfo("Игрок {$username} успешно зарегистрирован");
                return true;
            }
            
            $this->messageManager->send($player, "error.internal");
            $this->logError("Не удалось сохранить данные регистрации игрока {$username}");
            return false;
            
        } catch (\Exception $e) {
            $this->messageManager->send($player, "error.internal");
            $this->logError("Ошибка при регистрации игрока {$username}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Посчитать регистрации с IP
     * @param string $ip
     * @return int
     */
    private function countRegistrationsByIp($ip) {
        $count = 0;
        $dir = $this->configManager->getStoragePath();
        
        if (is_dir($dir)) {
            $files = scandir($dir);
            if ($files !== false) {
                foreach ($files as $file) {
                    if (strpos($file, '.yml') !== false) {
                        $data = $this->parseYamlFile($dir . $file);
                        if (isset($data['last_ip']) && $data['last_ip'] === $ip) {
                            $count++;
                        }
                    }
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Простой парсер YAML файла
     */
    private function parseYamlFile($file) {
        $content = file_get_contents($file);
        if ($content === false) return array();
        
        $result = array();
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) continue;
            
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $result[trim($key)] = trim(trim($value), '"\'');
            }
        }
        
        return $result;
    }
    
    /**
     * Авторизация игрока
     * @param Player $player
     * @param string $password
     * @return bool
     */
    public function login(Player $player, $password) {
        $username = $player->getName();
        $uuid = $this->getPlayerUUID($player);
        $currentTime = time();
        
        // Проверка состояния
        if ($this->isLoggedIn($player)) {
            $this->messageManager->send($player, "login.already-logged-in");
            return false;
        }
        
        if (!$this->isRegistered($player) && !$this->isCaptchaRequired($player)) {
            $this->messageManager->send($player, "login.not-registered");
            return false;
        }
        
        // Загрузка данных
        $authData = $this->storageManager->load($username);
        
        if ($authData === null) {
            $this->messageManager->send($player, "login.not-registered");
            return false;
        }
        
        // Проверка блокировки
        if ($authData->isLocked()) {
            $lockTimeRemaining = ceil(($authData->getLockedUntil() - $currentTime) / 60);
            $this->messageManager->send($player, "login.locked", array("minutes" => $lockTimeRemaining));
            $this->logSecurity("Попытка входа заблокированного игрока {$username}");
            return false;
        }
        
        // Проверка cooldown
        if (isset($this->lastAttemptTime[$uuid])) {
            $cooldown = $this->configManager->getLoginCooldown();
            $timeSinceLastAttempt = $currentTime - $this->lastAttemptTime[$uuid];
            
            if ($timeSinceLastAttempt < $cooldown) {
                $waitTime = $cooldown - $timeSinceLastAttempt;
                $this->messageManager->send($player, "login.cooldown", array("seconds" => $waitTime));
                return false;
            }
        }
        
        $this->lastAttemptTime[$uuid] = $currentTime;
        
        // Проверка пароля
        if (!$this->passwordManager->verifyPassword($password, $authData->getPasswordHash(), $authData->getSalt())) {
            $authData->incrementFailedAttempts();
            
            $maxAttempts = $this->configManager->getMaxLoginAttempts();
            
            if ($authData->getFailedAttempts() >= $maxAttempts) {
                // Блокировка
                $lockTime = $this->configManager->getLockTime();
                $authData->setLockedUntil($currentTime + $lockTime);
                $this->storageManager->save($authData);
                
                $lockMinutes = ceil($lockTime / 60);
                $this->messageManager->send($player, "login.locked", array("minutes" => $lockMinutes));
                $this->logSecurity("Игрок {$username} заблокирован после {$maxAttempts} неудачных попыток");
            } else {
                $this->storageManager->save($authData);
                $remaining = $maxAttempts - $authData->getFailedAttempts();
                $this->messageManager->send($player, "login.wrong-password", array("attempts" => $remaining));
                $this->logSecurity("Неверный пароль для игрока {$username} (попытка {$authData->getFailedAttempts()}/{$maxAttempts})");
            }
            
            return false;
        }
        
        // Успешная авторизация
        $authData->resetFailedAttempts();
        $authData->setLastLogin($currentTime);
        $authData->setSessionCreatedAt($currentTime);
        $authData->setLastIp($player->getAddress());
        $this->storageManager->save($authData);
        
        $this->setState($player, AuthState::LOGGED_IN);
        $this->messageManager->send($player, "login.success", array("player" => $username));
        
        // Очистка времени подключения
        unset($this->playerJoinTime[$uuid]);
        
        $this->logInfo("Игрок {$username} успешно авторизован");
        
        return true;
    }
    
    /**
     * Генерация капчи для игрока
     * @param Player $player
     */
    public function generateCaptcha(Player $player) {
        $uuid = $this->getPlayerUUID($player);
        
        // Генерация случайного примера
        $num1 = rand(1, 10);
        $num2 = rand(1, 10);
        $operators = array('+', '-', '*');
        $operator = $operators[array_rand($operators)];
        
        switch ($operator) {
            case '+':
                $answer = $num1 + $num2;
                $question = "{$num1} + {$num2} = ?";
                break;
            case '-':
                // Чтобы не было отрицательных чисел
                if ($num1 < $num2) {
                    $temp = $num1;
                    $num1 = $num2;
                    $num2 = $temp;
                }
                $answer = $num1 - $num2;
                $question = "{$num1} - {$num2} = ?";
                break;
            case '*':
                // Небольшие числа для умножения
                $num1 = rand(1, 5);
                $num2 = rand(1, 5);
                $answer = $num1 * $num2;
                $question = "{$num1} × {$num2} = ?";
                break;
        }
        
        $this->captchaData[$uuid] = array(
            'answer' => $answer,
            'question' => $question,
            'attempts' => 0,
            'created' => time()
        );
        
        $this->messageManager->send($player, "captcha.request", array("question" => $question));
    }
    
    /**
     * Проверка ответа капчи
     * @param Player $player
     * @param int $answer
     * @return bool
     */
    public function checkCaptcha(Player $player, $answer) {
        $uuid = $this->getPlayerUUID($player);
        
        if (!isset($this->captchaData[$uuid])) {
            $this->messageManager->send($player, "captcha.not-required");
            return false;
        }
        
        $captcha = $this->captchaData[$uuid];
        $maxAttempts = $this->configManager->getCaptchaMaxAttempts();
        
        // Проверка времени
        $timeout = $this->configManager->getCaptchaTimeout();
        if ($timeout > 0 && (time() - $captcha['created']) > $timeout) {
            unset($this->captchaData[$uuid]);
            $this->messageManager->send($player, "captcha.timeout");
            
            // Если капча истекла - можно выкинуть или создать новую
            if ($this->configManager->kickOnTimeout()) {
                $player->kick("Время на прохождение капчи истекло.");
            }
            return false;
        }
        
        if ((int)$answer === $captcha['answer']) {
            // Правильный ответ
            unset($this->captchaData[$uuid]);
            $this->setState($player, AuthState::LOGGED_IN);
            $this->messageManager->send($player, "captcha.success");
            $this->logInfo("Игрок " . $player->getName() . " прошёл капчу");
            return true;
        }
        
        // Неправильный ответ
        $this->captchaData[$uuid]['attempts']++;
        
        if ($this->captchaData[$uuid]['attempts'] >= $maxAttempts) {
            // Слишком много попыток - генерируем новую капчу
            $this->messageManager->send($player, "captcha.too-many-attempts");
            $this->generateCaptcha($player);
        } else {
            $this->messageManager->send($player, "captcha.wrong-answer");
        }
        
        return false;
    }
    
    /**
     * Выход игрока (logout)
     * @param Player $player
     */
    public function onPlayerQuit(Player $player) {
        $uuid = $this->getPlayerUUID($player);
        
        unset($this->playerStates[$uuid]);
        unset($this->lastAttemptTime[$uuid]);
        unset($this->captchaData[$uuid]);
        unset($this->playerJoinTime[$uuid]);
        
        $this->logInfo("Игрок " . $player->getName() . " вышел (автоматический logout)");
    }
    
    /**
     * Проверка timeout авторизации
     * @param Player $player
     * @return bool true если нужно кикнуть
     */
    public function checkAuthTimeout(Player $player) {
        $uuid = $this->getPlayerUUID($player);
        
        if ($this->isLoggedIn($player)) {
            return false;
        }
        
        $timeout = $this->configManager->getAuthTimeout();
        if ($timeout <= 0) {
            return false;
        }
        
        if (isset($this->playerJoinTime[$uuid])) {
            $elapsed = time() - $this->playerJoinTime[$uuid];
            if ($elapsed > $timeout) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Сохранить всех игроков
     */
    public function saveAllPlayers() {
        foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
            $username = $player->getName();
            $authData = $this->storageManager->load($username);
            
            if ($authData !== null && $this->isLoggedIn($player)) {
                $authData->setLastLogin(time());
                $this->storageManager->save($authData);
            }
        }
    }
    
    /**
     * Административное удаление аккаунта
     * @param string $username
     * @return bool
     */
    public function unregisterAccount($username) {
        if ($this->storageManager->delete($username)) {
            $this->storageManager->clearCache($username);
            $this->logInfo("Аккаунт игрока {$username} удалён администратором");
            return true;
        }
        return false;
    }
    
    /**
     * Административная смена пароля
     * @param string $username
     * @param string $newPassword
     * @return bool
     */
    public function adminChangePassword($username, $newPassword) {
        $authData = $this->storageManager->load($username);
        
        if ($authData === null) {
            return false;
        }
        
        $salt = $this->passwordManager->generateSalt();
        $passwordHash = $this->passwordManager->hashPassword($newPassword, $salt);
        
        $authData->setPassword($passwordHash, $salt);
        
        return $this->storageManager->save($authData);
    }
    
    /**
     * Административный logout игрока
     * @param string $username
     * @return bool
     */
    public function adminLogout($username) {
        $player = $this->plugin->getServer()->getPlayer($username);
        
        if ($player !== null) {
            $this->setState($player, AuthState::REGISTERED_NOT_LOGGED);
            return true;
        }
        
        return false;
    }
    
    /**
     * Получить информацию об аккаунте
     * @param string $username
     * @return array|null
     */
    public function getAccountInfo($username) {
        $authData = $this->storageManager->load($username);
        
        if ($authData === null) {
            return null;
        }
        
        return array(
            "username" => $authData->getUsername(),
            "uuid" => $authData->getUuid(),
            "registered_at" => date("Y-m-d H:i:s", $authData->getCreatedAt()),
            "last_login" => $authData->getLastLogin() > 0 
                ? date("Y-m-d H:i:s", $authData->getLastLogin()) 
                : "Никогда",
            "failed_attempts" => $authData->getFailedAttempts(),
            "is_locked" => $authData->isLocked()
        );
    }
    
    /**
     * Логирование информационных событий
     */
    private function logInfo($message) {
        if ($this->configManager->isLoggingEnabled()) {
            $this->plugin->getLogger()->info($message);
        }
    }
    
    /**
     * Логирование событий безопасности
     */
    private function logSecurity($message) {
        if ($this->configManager->isSecurityLoggingEnabled()) {
            $this->plugin->getLogger()->warning("[SECURITY] " . $message);
        }
    }
    
    /**
     * Логирование ошибок
     */
    private function logError($message) {
        $this->plugin->getLogger()->error($message);
    }
}
