<?php
/**
 * Класс для аутентификации и авторизации пользователей
 * 
 * @package Core
 */

namespace Core;

use PDOException;

class Auth
{
    /**
     * @var Database Объект базы данных
     */
    private $db;
    
    /**
     * @var string Название сессии
     */
    private $sessionName = 'psychologist_system';
    
    /**
     * @var string Название cookie
     */
    private $cookieName = 'psychologist_remember';
    
    /**
     * @var int Время жизни сессии (в секундах)
     */
    private $sessionLifetime = 86400; // 24 часа
    
    /**
     * @var int Время жизни cookie "запомнить меня" (в секундах)
     */
    private $cookieLifetime = 2592000; // 30 дней
    
    /**
     * Конструктор класса
     * 
     * @param Database $db Объект базы данных
     */
    public function __construct(Database $db)
    {
        $this->db = $db;
        
        // Инициализация сессии, если еще не начата
        if (session_status() === PHP_SESSION_NONE) {
            session_name($this->sessionName);
            session_start();
        }
    }
    
    /**
     * Вход пользователя в систему
     * 
     * @param string $username Имя пользователя (email или psychologist_id)
     * @param string $password Пароль
     * @param bool $remember Флаг "запомнить меня"
     * @return bool
     */
    public function login(string $username, string $password, bool $remember = false): bool
    {
        // Поиск пользователя по email или psychologist_id
        $sql = "SELECT * FROM users 
                WHERE (email = :username OR psychologist_id = :psychologist_id) 
                AND is_active = 1";
        
        $params = [
            ':username' => $username,
            ':psychologist_id' => is_numeric($username) ? (int)$username : null
        ];
        
        $user = $this->db->fetch($sql, $params);
        
        // Проверка пользователя и пароля
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        
        // Установка сессии
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['psychologist_id'] = $user['psychologist_id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['login_time'] = time();
        
        // Установка cookie "запомнить меня"
        if ($remember) {
            $this->setRememberCookie($user['id']);
        }
        
        // Обновление времени последнего входа
        $this->updateLastLogin($user['id']);
        
        return true;
    }
    
    /**
     * Установка cookie "запомнить меня"
     * 
     * @param int $userId ID пользователя
     */
    private function setRememberCookie(int $userId): void
    {
        // Генерация токена
        $token = bin2hex(random_bytes(32));
        $selector = bin2hex(random_bytes(16));
        
        // Хэширование токена для хранения в БД
        $tokenHash = hash('sha256', $token);
        
        // Срок действия токена
        $expires = time() + $this->cookieLifetime;
        $expiresDate = date('Y-m-d H:i:s', $expires);
        
        // Сохранение токена в БД
        try {
            $this->db->insert('auth_tokens', [
                'user_id' => $userId,
                'selector' => $selector,
                'token_hash' => $tokenHash,
                'expires_at' => $expiresDate,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Установка cookie
            $cookieValue = $selector . ':' . $token;
            setcookie(
                $this->cookieName,
                $cookieValue,
                [
                    'expires' => $expires,
                    'path' => '/',
                    'secure' => isset($_SERVER['HTTPS']),
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]
            );
            
        } catch (PDOException $e) {
            error_log("Ошибка при сохранении токена аутентификации: " . $e->getMessage());
        }
    }
    
    /**
     * Обновление времени последнего входа
     * 
     * @param int $userId ID пользователя
     */
    private function updateLastLogin(int $userId): void
    {
        try {
            $this->db->update(
                'users',
                ['last_login_at' => date('Y-m-d H:i:s')],
                ['id' => $userId]
            );
        } catch (PDOException $e) {
            error_log("Ошибка при обновлении времени входа: " . $e->getMessage());
        }
    }
    
    /**
     * Автоматический вход по cookie "запомнить меня"
     * 
     * @return bool
     */
    public function loginFromCookie(): bool
    {
        if (!isset($_COOKIE[$this->cookieName])) {
            return false;
        }
        
        list($selector, $token) = explode(':', $_COOKIE[$this->cookieName]);
        
        if (empty($selector) || empty($token)) {
            return false;
        }
        
        // Поиск токена в БД
        $sql = "SELECT at.*, u.* 
                FROM auth_tokens at
                JOIN users u ON at.user_id = u.id
                WHERE at.selector = :selector 
                AND at.expires_at > NOW() 
                AND u.is_active = 1";
        
        $tokenData = $this->db->fetch($sql, [':selector' => $selector]);
        
        if (!$tokenData) {
            $this->clearRememberCookie();
            return false;
        }
        
        // Проверка токена
        $tokenHash = hash('sha256', $token);
        if (!hash_equals($tokenData['token_hash'], $tokenHash)) {
            // Удаление невалидного токена
            $this->db->delete('auth_tokens', ['id' => $tokenData['id']]);
            $this->clearRememberCookie();
            return false;
        }
        
        // Установка сессии
        $_SESSION['user_id'] = $tokenData['user_id'];
        $_SESSION['psychologist_id'] = $tokenData['psychologist_id'];
        $_SESSION['user_email'] = $tokenData['email'];
        $_SESSION['full_name'] = $tokenData['full_name'];
        $_SESSION['login_time'] = time();
        
        // Обновление времени последнего входа
        $this->updateLastLogin($tokenData['user_id']);
        
        return true;
    }
    
    /**
     * Очистка cookie "запомнить меня"
     */
    private function clearRememberCookie(): void
    {
        setcookie($this->cookieName, '', time() - 3600, '/');
        unset($_COOKIE[$this->cookieName]);
    }
    
    /**
     * Выход пользователя из системы
     */
    public function logout(): void
    {
        // Удаление токена "запомнить меня" из БД
        if (isset($_COOKIE[$this->cookieName])) {
            list($selector) = explode(':', $_COOKIE[$this->cookieName]);
            if (!empty($selector)) {
                $this->db->delete('auth_tokens', ['selector' => $selector]);
            }
        }
        
        // Очистка cookie
        $this->clearRememberCookie();
        
        // Очистка сессии
        $_SESSION = [];
        
        // Уничтожение сессии
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
    
    /**
     * Проверка аутентификации пользователя
     * 
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        // Проверка сессии
        if (isset($_SESSION['user_id'], $_SESSION['psychologist_id'])) {
            // Проверка времени сессии
            if (isset($_SESSION['login_time']) && 
                (time() - $_SESSION['login_time'] < $this->sessionLifetime)) {
                return true;
            }
        }
        
        // Попытка входа по cookie
        if ($this->loginFromCookie()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Получение текущего пользователя
     * 
     * @return array|null
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        // Если пользователь уже загружен в сессии
        if (isset($_SESSION['user_data'])) {
            return $_SESSION['user_data'];
        }
        
        // Загрузка данных пользователя из БД
        $sql = "SELECT * FROM users WHERE id = :id AND is_active = 1";
        $user = $this->db->fetch($sql, [':id' => $_SESSION['user_id']]);
        
        if ($user) {
            $_SESSION['user_data'] = $user;
            return $user;
        }
        
        // Если пользователь не найден, завершаем сессию
        $this->logout();
        return null;
    }
    
    /**
     * Получение ID текущего психолога
     * 
     * @return int|null
     */
    public function getCurrentPsychologistId(): ?int
    {
        $user = $this->getCurrentUser();
        return $user ? (int)$user['psychologist_id'] : null;
    }
    
    /**
     * Проверка доступа к данным ребенка
     * 
     * @param int $childId ID ребенка
     * @return bool
     */
    public function canAccessChild(int $childId): bool
    {
        $psychologistId = $this->getCurrentPsychologistId();
        
        if (!$psychologistId) {
            return false;
        }
        
        $sql = "SELECT COUNT(*) as count 
                FROM children 
                WHERE id = :child_id 
                AND psychologist_id = :psychologist_id";
        
        $result = $this->db->fetch($sql, [
            ':child_id' => $childId,
            ':psychologist_id' => $psychologistId
        ]);
        
        return $result && $result['count'] > 0;
    }
    
    /**
     * Проверка доступа к результату теста
     * 
     * @param string $tableName Имя таблицы теста
     * @param int $resultId ID результата
     * @return bool
     */
    public function canAccessTestResult(string $tableName, int $resultId): bool
    {
        $psychologistId = $this->getCurrentPsychologistId();
        
        if (!$psychologistId) {
            return false;
        }
        
        $sql = "SELECT COUNT(*) as count 
                FROM {$tableName} 
                WHERE id = :result_id 
                AND psychologist_id = :psychologist_id";
        
        $result = $this->db->fetch($sql, [
            ':result_id' => $resultId,
            ':psychologist_id' => $psychologistId
        ]);
        
        return $result && $result['count'] > 0;
    }
    
    /**
     * Создание нового пользователя
     * 
     * @param array $data Данные пользователя
     * @return int ID нового пользователя
     */
    public function createUser(array $data): int
    {
        // Проверка обязательных полей
        $required = ['psychologist_id', 'full_name', 'email', 'password'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Поле '$field' обязательно для заполнения");
            }
        }
        
        // Проверка уникальности psychologist_id
        $existing = $this->db->fetch(
            "SELECT id FROM users WHERE psychologist_id = :psychologist_id",
            [':psychologist_id' => $data['psychologist_id']]
        );
        
        if ($existing) {
            throw new \InvalidArgumentException("Психолог с ID {$data['psychologist_id']} уже зарегистрирован");
        }
        
        // Проверка уникальности email
        $existing = $this->db->fetch(
            "SELECT id FROM users WHERE email = :email",
            [':email' => $data['email']]
        );
        
        if ($existing) {
            throw new \InvalidArgumentException("Пользователь с email {$data['email']} уже зарегистрирован");
        }
        
        // Хэширование пароля
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Подготовка данных для вставки
        $userData = [
            'psychologist_id' => $data['psychologist_id'],
            'full_name' => trim($data['full_name']),
            'email' => trim($data['email']),
            'password_hash' => $passwordHash,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->db->insert('users', $userData);
    }
    
    /**
     * Смена пароля пользователя
     * 
     * @param int $userId ID пользователя
     * @param string $newPassword Новый пароль
     * @return bool
     */
    public function changePassword(int $userId, string $newPassword): bool
    {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $result = $this->db->update(
            'users',
            ['password_hash' => $passwordHash],
            ['id' => $userId]
        );
        
        return $result > 0;
    }
    
    /**
     * Очистка просроченных токенов аутентификации
     */
    public function cleanupExpiredTokens(): void
    {
        try {
            $this->db->query("DELETE FROM auth_tokens WHERE expires_at < NOW()");
        } catch (PDOException $e) {
            error_log("Ошибка при очистке токенов: " . $e->getMessage());
        }
    }
    
    /**
     * Проверка необходимости смены пароля
     * 
     * @return bool
     */
    public function passwordNeedsReset(): bool
    {
        $user = $this->getCurrentUser();
        
        if (!$user) {
            return false;
        }
        
        // Проверка возраста пароля (90 дней)
        if (isset($user['password_changed_at'])) {
            $passwordAge = time() - strtotime($user['password_changed_at']);
            return $passwordAge > (90 * 86400);
        }
        
        return false;
    }
}
