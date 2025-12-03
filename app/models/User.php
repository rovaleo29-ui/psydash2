<?php
/**
 * Модель пользователя (психолога)
 * 
 * @package App\Models
 */

namespace App\Models;

use Core\Database;
use PDOException;
use Exception;

class User
{
    /**
     * @var Database Объект базы данных
     */
    private $db;
    
    /**
     * @var string Имя таблицы в БД
     */
    private $table = 'users';
    
    /**
     * Конструктор модели
     * 
     * @param Database $db Объект базы данных
     */
    public function __construct(Database $db)
    {
        $this->db = $db;
    }
    
    /**
     * Создание нового пользователя
     * 
     * @param array $data Данные пользователя
     * @return int ID созданного пользователя
     * @throws Exception
     */
    public function create(array $data): int
    {
        // Валидация обязательных полей
        $required = ['psychologist_id', 'full_name', 'email', 'password'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Поле '$field' обязательно для заполнения");
            }
        }
        
        // Проверка уникальности psychologist_id
        if ($this->psychologistIdExists($data['psychologist_id'])) {
            throw new Exception("Психолог с ID {$data['psychologist_id']} уже зарегистрирован");
        }
        
        // Проверка уникальности email
        if ($this->emailExists($data['email'])) {
            throw new Exception("Пользователь с email {$data['email']} уже зарегистрирован");
        }
        
        // Валидация email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Некорректный формат email");
        }
        
        // Валидация psychologist_id (только цифры)
        if (!preg_match('/^\d+$/', $data['psychologist_id'])) {
            throw new Exception("ID психолога должен содержать только цифры");
        }
        
        // Хэширование пароля
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Подготовка данных для вставки
        $userData = [
            'psychologist_id' => (int)$data['psychologist_id'],
            'full_name' => trim($data['full_name']),
            'email' => trim($data['email']),
            'password_hash' => $passwordHash,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Дополнительные поля (если переданы)
        if (!empty($data['phone'])) {
            $userData['phone'] = trim($data['phone']);
        }
        
        if (!empty($data['position'])) {
            $userData['position'] = trim($data['position']);
        }
        
        if (!empty($data['institution'])) {
            $userData['institution'] = trim($data['institution']);
        }
        
        // Вставка в БД
        return $this->db->insert($this->table, $userData);
    }
    
    /**
     * Поиск пользователя по ID
     * 
     * @param int $id ID пользователя
     * @return array|null Данные пользователя или null
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id AND is_active = 1";
        return $this->db->fetch($sql, [':id' => $id]);
    }
    
    /**
     * Поиск пользователя по psychologist_id
     * 
     * @param int $psychologistId ID психолога
     * @return array|null Данные пользователя или null
     */
    public function findByPsychologistId(int $psychologistId): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE psychologist_id = :psychologist_id AND is_active = 1";
        return $this->db->fetch($sql, [':psychologist_id' => $psychologistId]);
    }
    
    /**
     * Поиск пользователя по email
     * 
     * @param string $email Email пользователя
     * @return array|null Данные пользователя или null
     */
    public function findByEmail(string $email): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE email = :email AND is_active = 1";
        return $this->db->fetch($sql, [':email' => $email]);
    }
    
    /**
     * Проверка существования psychologist_id
     * 
     * @param int $psychologistId ID психолога
     * @return bool
     */
    public function psychologistIdExists(int $psychologistId): bool
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE psychologist_id = :psychologist_id";
        $result = $this->db->fetch($sql, [':psychologist_id' => $psychologistId]);
        return $result && $result['count'] > 0;
    }
    
    /**
     * Проверка существования email
     * 
     * @param string $email Email пользователя
     * @return bool
     */
    public function emailExists(string $email): bool
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE email = :email";
        $result = $this->db->fetch($sql, [':email' => $email]);
        return $result && $result['count'] > 0;
    }
    
    /**
     * Проверка пароля пользователя
     * 
     * @param int $userId ID пользователя
     * @param string $password Пароль для проверки
     * @return bool
     */
    public function verifyPassword(int $userId, string $password): bool
    {
        $user = $this->findById($userId);
        
        if (!$user || empty($user['password_hash'])) {
            return false;
        }
        
        return password_verify($password, $user['password_hash']);
    }
    
    /**
     * Обновление пароля пользователя
     * 
     * @param int $userId ID пользователя
     * @param string $newPassword Новый пароль
     * @return bool
     */
    public function updatePassword(int $userId, string $newPassword): bool
    {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $result = $this->db->update(
            $this->table,
            [
                'password_hash' => $passwordHash,
                'password_changed_at' => date('Y-m-d H:i:s')
            ],
            ['id' => $userId]
        );
        
        return $result > 0;
    }
    
    /**
     * Обновление данных пользователя
     * 
     * @param int $userId ID пользователя
     * @param array $data Данные для обновления
     * @return bool
     */
    public function update(int $userId, array $data): bool
    {
        // Подготовка данных для обновления
        $updateData = [];
        
        if (isset($data['full_name'])) {
            $updateData['full_name'] = trim($data['full_name']);
        }
        
        if (isset($data['email']) && !empty($data['email'])) {
            // Проверка уникальности нового email (кроме текущего пользователя)
            $existing = $this->db->fetch(
                "SELECT id FROM {$this->table} WHERE email = :email AND id != :id",
                [
                    ':email' => $data['email'],
                    ':id' => $userId
                ]
            );
            
            if ($existing) {
                throw new Exception("Пользователь с email {$data['email']} уже существует");
            }
            
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Некорректный формат email");
            }
            
            $updateData['email'] = trim($data['email']);
        }
        
        if (isset($data['phone'])) {
            $updateData['phone'] = trim($data['phone']);
        }
        
        if (isset($data['position'])) {
            $updateData['position'] = trim($data['position']);
        }
        
        if (isset($data['institution'])) {
            $updateData['institution'] = trim($data['institution']);
        }
        
        if (isset($data['is_active'])) {
            $updateData['is_active'] = (bool)$data['is_active'];
        }
        
        if (empty($updateData)) {
            return false;
        }
        
        $updateData['updated_at'] = date('Y-m-d H:i:s');
        
        $result = $this->db->update($this->table, $updateData, ['id' => $userId]);
        return $result > 0;
    }
    
    /**
     * Удаление пользователя (мягкое удаление)
     * 
     * @param int $userId ID пользователя
     * @return bool
     */
    public function delete(int $userId): bool
    {
        // Вместо физического удаления деактивируем пользователя
        $result = $this->db->update(
            $this->table,
            [
                'is_active' => 0,
                'deleted_at' => date('Y-m-d H:i:s')
            ],
            ['id' => $userId]
        );
        
        return $result > 0;
    }
    
    /**
     * Получение всех активных пользователей
     * 
     * @param array $filters Фильтры
     * @return array
     */
    public function getAllActive(array $filters = []): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE is_active = 1";
        $params = [];
        
        // Применение фильтров
        if (!empty($filters['search'])) {
            $sql .= " AND (full_name LIKE :search OR email LIKE :search OR psychologist_id LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        if (!empty($filters['institution'])) {
            $sql .= " AND institution LIKE :institution";
            $params[':institution'] = '%' . $filters['institution'] . '%';
        }
        
        // Сортировка
        $sql .= " ORDER BY full_name";
        
        // Пагинация
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT :limit";
            $params[':limit'] = (int)$filters['limit'];
            
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET :offset";
                $params[':offset'] = (int)$filters['offset'];
            }
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Подсчет активных пользователей
     * 
     * @param array $filters Фильтры
     * @return int
     */
    public function countActive(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE is_active = 1";
        $params = [];
        
        if (!empty($filters['search'])) {
            $sql .= " AND (full_name LIKE :search OR email LIKE :search OR psychologist_id LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        if (!empty($filters['institution'])) {
            $sql .= " AND institution LIKE :institution";
            $params[':institution'] = '%' . $filters['institution'] . '%';
        }
        
        $result = $this->db->fetch($sql, $params);
        return $result ? (int)$result['count'] : 0;
    }
    
    /**
     * Получение статистики по пользователям
     * 
     * @return array
     */
    public function getStats(): array
    {
        // Общее количество пользователей
        $total = $this->db->fetch("SELECT COUNT(*) as count FROM {$this->table}")['count'] ?? 0;
        
        // Активных пользователей
        $active = $this->db->fetch("SELECT COUNT(*) as count FROM {$this->table} WHERE is_active = 1")['count'] ?? 0;
        
        // Неактивных пользователей
        $inactive = $total - $active;
        
        // Пользователей за последние 30 дней
        $recent = $this->db->fetch(
            "SELECT COUNT(*) as count FROM {$this->table} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        )['count'] ?? 0;
        
        // Пользователей по месяцам (последние 6 месяцев)
        $byMonth = $this->db->fetchAll(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
             FROM {$this->table} 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m') 
             ORDER BY month DESC"
        );
        
        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'recent' => $recent,
            'by_month' => $byMonth
        ];
    }
    
    /**
     * Обновление времени последнего входа
     * 
     * @param int $userId ID пользователя
     * @return bool
     */
    public function updateLastLogin(int $userId): bool
    {
        $result = $this->db->update(
            $this->table,
            ['last_login_at' => date('Y-m-d H:i:s')],
            ['id' => $userId]
        );
        
        return $result > 0;
    }
    
    /**
     * Проверка необходимости смены пароля
     * 
     * @param int $userId ID пользователя
     * @param int $maxDays Максимальное количество дней
     * @return bool
     */
    public function passwordNeedsReset(int $userId, int $maxDays = 90): bool
    {
        $user = $this->findById($userId);
        
        if (!$user || empty($user['password_changed_at'])) {
            return true;
        }
        
        $changedDate = new \DateTime($user['password_changed_at']);
        $currentDate = new \DateTime();
        $interval = $currentDate->diff($changedDate);
        
        return $interval->days > $maxDays;
    }
    
    /**
     * Создание токена сброса пароля
     * 
     * @param int $userId ID пользователя
     * @return string Токен
     */
    public function createPasswordResetToken(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1 час
        
        // Удаление старых токенов пользователя
        $this->db->delete('password_resets', ['user_id' => $userId]);
        
        // Создание нового токена
        $this->db->insert('password_resets', [
            'user_id' => $userId,
            'token' => hash('sha256', $token),
            'expires_at' => $expires,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        return $token;
    }
    
    /**
     * Проверка токена сброса пароля
     * 
     * @param string $token Токен
     * @return int|null ID пользователя или null
     */
    public function verifyPasswordResetToken(string $token): ?int
    {
        $tokenHash = hash('sha256', $token);
        
        $result = $this->db->fetch(
            "SELECT user_id FROM password_resets WHERE token = :token AND expires_at > NOW()",
            [':token' => $tokenHash]
        );
        
        if ($result) {
            return (int)$result['user_id'];
        }
        
        return null;
    }
    
    /**
     * Удаление токена сброса пароля
     * 
     * @param string $token Токен
     * @return bool
     */
    public function deletePasswordResetToken(string $token): bool
    {
        $tokenHash = hash('sha256', $token);
        
        $result = $this->db->delete('password_resets', ['token' => $tokenHash]);
        return $result > 0;
    }
    
    /**
     * Получение истории действий пользователя
     * 
     * @param int $userId ID пользователя
     * @param int $limit Количество записей
     * @return array
     */
    public function getActivityHistory(int $userId, int $limit = 50): array
    {
        try {
            // Проверка существования таблицы активности
            if (!$this->db->tableExists('user_activity')) {
                return [];
            }
            
            return $this->db->fetchAll(
                "SELECT * FROM user_activity 
                 WHERE user_id = :user_id 
                 ORDER BY created_at DESC 
                 LIMIT :limit",
                [
                    ':user_id' => $userId,
                    ':limit' => $limit
                ]
            );
            
        } catch (PDOException $e) {
            error_log("Ошибка при получении истории действий: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Логирование действия пользователя
     * 
     * @param int $userId ID пользователя
     * @param string $action Действие
     * @param string $description Описание
     * @param array $details Детали
     * @return bool
     */
    public function logActivity(int $userId, string $action, string $description, array $details = []): bool
    {
        try {
            // Проверка существования таблицы активности
            if (!$this->db->tableExists('user_activity')) {
                $this->createActivityTable();
            }
            
            $result = $this->db->insert('user_activity', [
                'user_id' => $userId,
                'action' => $action,
                'description' => $description,
                'details' => !empty($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            return $result > 0;
            
        } catch (PDOException $e) {
            error_log("Ошибка при логировании действия: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Создание таблицы активности пользователей
     */
    private function createActivityTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS user_activity (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            action VARCHAR(100) NOT NULL,
            description TEXT,
            details TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_action (action),
            INDEX idx_created (created_at),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        
        $this->db->query($sql);
    }
    
    /**
     * Получение пользователей с истекшим сроком действия пароля
     * 
     * @param int $maxDays Максимальное количество дней
     * @return array
     */
    public function getUsersWithExpiredPassword(int $maxDays = 90): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE is_active = 1 
                AND (
                    password_changed_at IS NULL 
                    OR password_changed_at < DATE_SUB(NOW(), INTERVAL :max_days DAY)
                )";
        
        return $this->db->fetchAll($sql, [':max_days' => $maxDays]);
    }
    
    /**
     * Получение неактивных пользователей
     * 
     * @param int $inactiveDays Количество дней неактивности
     * @return array
     */
    public function getInactiveUsers(int $inactiveDays = 90): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE is_active = 1 
                AND (
                    last_login_at IS NULL 
                    OR last_login_at < DATE_SUB(NOW(), INTERVAL :inactive_days DAY)
                )";
        
        return $this->db->fetchAll($sql, [':inactive_days' => $inactiveDays]);
    }
    
    /**
     * Экспорт данных пользователей
     * 
     * @param array $filters Фильтры
     * @return array
     */
    public function exportData(array $filters = []): array
    {
        $users = $this->getAllActive($filters);
        
        // Добавление дополнительной информации
        foreach ($users as &$user) {
            // Маскирование пароля
            unset($user['password_hash']);
            
            // Форматирование дат
            if (!empty($user['created_at'])) {
                $user['created_at_formatted'] = date('d.m.Y H:i', strtotime($user['created_at']));
            }
            
            if (!empty($user['last_login_at'])) {
                $user['last_login_at_formatted'] = date('d.m.Y H:i', strtotime($user['last_login_at']));
            }
            
            if (!empty($user['password_changed_at'])) {
                $user['password_changed_at_formatted'] = date('d.m.Y H:i', strtotime($user['password_changed_at']));
            }
            
            // Расчет дней с последнего входа
            if (!empty($user['last_login_at'])) {
                $lastLogin = new \DateTime($user['last_login_at']);
                $now = new \DateTime();
                $user['days_since_last_login'] = $now->diff($lastLogin)->days;
            } else {
                $user['days_since_last_login'] = 'никогда';
            }
            
            // Расчет дней с изменения пароля
            if (!empty($user['password_changed_at'])) {
                $changed = new \DateTime($user['password_changed_at']);
                $now = new \DateTime();
                $user['days_since_password_change'] = $now->diff($changed)->days;
            } else {
                $user['days_since_password_change'] = 'никогда';
            }
        }
        
        return $users;
    }
    
    /**
     * Проверка существования пользователя по ID
     * 
     * @param int $id ID пользователя
     * @return bool
     */
    public function exists(int $id): bool
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE id = :id";
        $result = $this->db->fetch($sql, [':id' => $id]);
        return $result && $result['count'] > 0;
    }
    
    /**
     * Поиск пользователей по нескольким критериям
     * 
     * @param array $criteria Критерии поиска
     * @return array
     */
    public function search(array $criteria): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE is_active = 1";
        $params = [];
        
        if (!empty($criteria['psychologist_id'])) {
            $sql .= " AND psychologist_id = :psychologist_id";
            $params[':psychologist_id'] = $criteria['psychologist_id'];
        }
        
        if (!empty($criteria['email'])) {
            $sql .= " AND email LIKE :email";
            $params[':email'] = '%' . $criteria['email'] . '%';
        }
        
        if (!empty($criteria['full_name'])) {
            $sql .= " AND full_name LIKE :full_name";
            $params[':full_name'] = '%' . $criteria['full_name'] . '%';
        }
        
        if (!empty($criteria['institution'])) {
            $sql .= " AND institution LIKE :institution";
            $params[':institution'] = '%' . $criteria['institution'] . '%';
        }
        
        if (!empty($criteria['position'])) {
            $sql .= " AND position LIKE :position";
            $params[':position'] = '%' . $criteria['position'] . '%';
        }
        
        $sql .= " ORDER BY full_name";
        
        return $this->db->fetchAll($sql, $params);
    }
}