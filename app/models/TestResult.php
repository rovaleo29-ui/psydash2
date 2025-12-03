<?php
/**
 * Модель результатов тестов (базовый класс для всех модулей тестов)
 * 
 * @package App\Models
 */

namespace App\Models;

use Core\Database;
use PDOException;
use Exception;

class TestResult
{
    /**
     * @var Database Объект базы данных
     */
    protected $db;
    
    /**
     * @var string Имя таблицы результатов
     */
    protected $tableName;
    
    /**
     * @var array Конфигурация теста
     */
    protected $config;
    
    /**
     * Конструктор модели
     * 
     * @param Database $db Объект базы данных
     * @param string $tableName Имя таблицы результатов
     * @param array $config Конфигурация теста
     */
    public function __construct(Database $db, string $tableName, array $config = [])
    {
        $this->db = $db;
        $this->tableName = $tableName;
        $this->config = $config;
        
        // Проверка существования таблицы
        if (!$this->tableExists()) {
            $this->createTable();
        }
    }
    
    /**
     * Проверка существования таблицы
     * 
     * @return bool
     */
    public function tableExists(): bool
    {
        return $this->db->tableExists($this->tableName);
    }
    
    /**
     * Создание таблицы результатов (базовая структура)
     * Должен быть переопределен в конкретных модулях
     */
    protected function createTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (
            id INT PRIMARY KEY AUTO_INCREMENT,
            child_id INT NOT NULL,
            psychologist_id INT NOT NULL,
            test_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_child (child_id),
            INDEX idx_psychologist (psychologist_id),
            INDEX idx_date (test_date),
            FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE
        )";
        
        $this->db->query($sql);
    }
    
    /**
     * Создание записи результата теста
     * 
     * @param array $data Данные результата
     * @return int ID созданной записи
     * @throws Exception
     */
    public function create(array $data): int
    {
        // Валидация обязательных полей
        $required = ['child_id', 'psychologist_id', 'test_date'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Поле '$field' обязательно для заполнения");
            }
        }
        
        // Валидация данных
        $errors = $this->validate($data);
        if (!empty($errors)) {
            throw new Exception(implode(', ', $errors));
        }
        
        // Проверка существования ребенка и прав доступа
        if (!$this->canAccessChild($data['child_id'], $data['psychologist_id'])) {
            throw new Exception('Доступ к ребенку запрещен');
        }
        
        // Подготовка данных для вставки
        $testData = [
            'child_id' => (int)$data['child_id'],
            'psychologist_id' => (int)$data['psychologist_id'],
            'test_date' => $data['test_date'],
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Добавление специфичных полей теста
        $specificFields = $this->getSpecificFields();
        foreach ($specificFields as $field) {
            if (isset($data[$field])) {
                $testData[$field] = $data[$field];
            }
        }
        
        // Расчет итоговых показателей (если есть метод calculate)
        if (method_exists($this, 'calculate')) {
            $calculated = $this->calculate($testData);
            if (is_array($calculated)) {
                $testData = array_merge($testData, $calculated);
            }
        }
        
        // Вставка в БД
        $resultId = $this->db->insert($this->tableName, $testData);
        
        // Логирование
        $this->logChange($resultId, $data['psychologist_id'], 'create', 'Создан новый результат теста');
        
        return $resultId;
    }
    
    /**
     * Получение результата по ID
     * 
     * @param int $id ID результата
     * @param int $psychologistId ID психолога (для проверки прав)
     * @return array|null Данные результата или null
     */
    public function findById(int $id, int $psychologistId): ?array
    {
        $sql = "SELECT t.*, c.first_name, c.last_name, c.class, c.birth_date 
                FROM {$this->tableName} t
                LEFT JOIN children c ON t.child_id = c.id
                WHERE t.id = :id AND t.psychologist_id = :psychologist_id";
        
        $result = $this->db->fetch($sql, [
            ':id' => $id,
            ':psychologist_id' => $psychologistId
        ]);
        
        if ($result) {
            // Добавление информации о ребенке
            $result['child_name'] = $result['last_name'] . ' ' . $result['first_name'];
            $result['child_age'] = $this->calculateAge($result['birth_date']);
            
            // Интерпретация результата (если есть метод interpret)
            if (method_exists($this, 'interpret')) {
                $interpretation = $this->interpret($result);
                if (is_array($interpretation)) {
                    $result = array_merge($result, $interpretation);
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Получение всех результатов психолога
     * 
     * @param int $psychologistId ID психолога
     * @param array $filters Фильтры
     * @return array
     */
    public function findByPsychologist(int $psychologistId, array $filters = []): array
    {
        $sql = "SELECT t.*, c.first_name, c.last_name, c.class 
                FROM {$this->tableName} t
                LEFT JOIN children c ON t.child_id = c.id
                WHERE t.psychologist_id = :psychologist_id";
        
        $params = [':psychologist_id' => $psychologistId];
        
        // Применение фильтров
        if (!empty($filters['child_id'])) {
            $sql .= " AND t.child_id = :child_id";
            $params[':child_id'] = $filters['child_id'];
        }
        
        if (!empty($filters['class'])) {
            $sql .= " AND c.class = :class";
            $params[':class'] = $filters['class'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND t.test_date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND t.test_date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (c.first_name LIKE :search OR c.last_name LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        // Сортировка
        $orderBy = $filters['order_by'] ?? 't.test_date DESC, t.created_at DESC';
        $sql .= " ORDER BY {$orderBy}";
        
        // Пагинация
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT :limit";
            $params[':limit'] = (int)$filters['limit'];
            
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET :offset";
                $params[':offset'] = (int)$filters['offset'];
            }
        }
        
        $results = $this->db->fetchAll($sql, $params);
        
        // Добавление дополнительной информации
        foreach ($results as &$result) {
            $result['child_name'] = $result['last_name'] . ' ' . $result['first_name'];
            
            // Интерпретация результата
            if (method_exists($this, 'interpret')) {
                $interpretation = $this->interpret($result);
                if (is_array($interpretation)) {
                    $result = array_merge($result, $interpretation);
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Подсчет результатов психолога
     * 
     * @param int $psychologistId ID психолога
     * @param array $filters Фильтры
     * @return int
     */
    public function countByPsychologist(int $psychologistId, array $filters = []): int
    {
        $sql = "SELECT COUNT(*) as count 
                FROM {$this->tableName} t
                LEFT JOIN children c ON t.child_id = c.id
                WHERE t.psychologist_id = :psychologist_id";
        
        $params = [':psychologist_id' => $psychologistId];
        
        if (!empty($filters['child_id'])) {
            $sql .= " AND t.child_id = :child_id";
            $params[':child_id'] = $filters['child_id'];
        }
        
        if (!empty($filters['class'])) {
            $sql .= " AND c.class = :class";
            $params[':class'] = $filters['class'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND t.test_date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND t.test_date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
        
        $result = $this->db->fetch($sql, $params);
        return $result ? (int)$result['count'] : 0;
    }
    
    /**
     * Получение результатов ребенка
     * 
     * @param int $childId ID ребенка
     * @param int $psychologistId ID психолога
     * @param array $filters Фильтры
     * @return array
     */
    public function findByChild(int $childId, int $psychologistId, array $filters = []): array
    {
        $sql = "SELECT * FROM {$this->tableName} 
                WHERE child_id = :child_id AND psychologist_id = :psychologist_id";
        
        $params = [
            ':child_id' => $childId,
            ':psychologist_id' => $psychologistId
        ];
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND test_date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND test_date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
        
        $sql .= " ORDER BY test_date DESC";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT :limit";
            $params[':limit'] = (int)$filters['limit'];
        }
        
        $results = $this->db->fetchAll($sql, $params);
        
        // Добавление интерпретации
        foreach ($results as &$result) {
            if (method_exists($this, 'interpret')) {
                $interpretation = $this->interpret($result);
                if (is_array($interpretation)) {
                    $result = array_merge($result, $interpretation);
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Обновление результата теста
     * 
     * @param int $id ID результата
     * @param int $psychologistId ID психолога
     * @param array $data Данные для обновления
     * @return bool
     * @throws Exception
     */
    public function update(int $id, int $psychologistId, array $data): bool
    {
        // Получение текущих данных
        $current = $this->findById($id, $psychologistId);
        if (!$current) {
            throw new Exception('Результат теста не найден');
        }
        
        // Валидация данных
        $validationData = array_merge($current, $data);
        $errors = $this->validate($validationData, $id);
        if (!empty($errors)) {
            throw new Exception(implode(', ', $errors));
        }
        
        // Подготовка данных для обновления
        $updateData = [];
        
        if (isset($data['test_date']) && $data['test_date'] !== $current['test_date']) {
            $updateData['test_date'] = $data['test_date'];
        }
        
        // Обновление специфичных полей
        $specificFields = $this->getSpecificFields();
        foreach ($specificFields as $field) {
            if (isset($data[$field]) && $data[$field] != ($current[$field] ?? null)) {
                $updateData[$field] = $data[$field];
            }
        }
        
        if (empty($updateData)) {
            return false;
        }
        
        // Пересчет итоговых показателей
        if (method_exists($this, 'calculate')) {
            $calculated = $this->calculate(array_merge($current, $updateData));
            if (is_array($calculated)) {
                $updateData = array_merge($updateData, $calculated);
            }
        }
        
        // Обновление в БД
        $result = $this->db->update(
            $this->tableName,
            $updateData,
            ['id' => $id, 'psychologist_id' => $psychologistId]
        );
        
        if ($result > 0) {
            // Логирование изменений
            $changes = [];
            foreach ($updateData as $field => $newValue) {
                $oldValue = $current[$field] ?? null;
                if ($newValue != $oldValue) {
                    $changes[] = "$field: '$oldValue' → '$newValue'";
                }
            }
            
            $changeDescription = 'Изменены поля: ' . implode(', ', array_keys($updateData));
            if (!empty($changes)) {
                $changeDescription .= ' (' . implode('; ', $changes) . ')';
            }
            
            $this->logChange($id, $psychologistId, 'update', $changeDescription);
        }
        
        return $result > 0;
    }
    
    /**
     * Удаление результата теста
     * 
     * @param int $id ID результата
     * @param int $psychologistId ID психолога
     * @return bool
     * @throws Exception
     */
    public function delete(int $id, int $psychologistId): bool
    {
        // Проверка существования результата
        $result = $this->findById($id, $psychologistId);
        if (!$result) {
            throw new Exception('Результат теста не найден');
        }
        
        // Логирование перед удалением
        $childName = $result['last_name'] . ' ' . $result['first_name'];
        $this->logChange($id, $psychologistId, 'delete', "Удален результат теста для ребенка: $childName");
        
        // Удаление из БД
        $result = $this->db->delete(
            $this->tableName,
            ['id' => $id, 'psychologist_id' => $psychologistId]
        );
        
        return $result > 0;
    }
    
    /**
     * Получение статистики по результатам
     * 
     * @param int $psychologistId ID психолога
     * @return array
     */
    public function getStats(int $psychologistId): array
    {
        // Общее количество тестов
        $total = $this->countByPsychologist($psychologistId);
        
        // Тесты по месяцам (последние 12 месяцев)
        $byMonth = $this->db->fetchAll(
            "SELECT DATE_FORMAT(test_date, '%Y-%m') as month, COUNT(*) as count 
             FROM {$this->tableName} 
             WHERE psychologist_id = :psychologist_id 
             AND test_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
             GROUP BY DATE_FORMAT(test_date, '%Y-%m') 
             ORDER BY month DESC",
            [':psychologist_id' => $psychologistId]
        );
        
        // Тесты по классам
        $byClass = $this->db->fetchAll(
            "SELECT c.class, COUNT(*) as count 
             FROM {$this->tableName} t
             JOIN children c ON t.child_id = c.id
             WHERE t.psychologist_id = :psychologist_id 
             GROUP BY c.class 
             ORDER BY c.class",
            [':psychologist_id' => $psychologistId]
        );
        
        // Среднее количество тестов на ребенка
        $uniqueChildren = $this->db->fetch(
            "SELECT COUNT(DISTINCT child_id) as count 
             FROM {$this->tableName} 
             WHERE psychologist_id = :psychologist_id",
            [':psychologist_id' => $psychologistId]
        )['count'] ?? 0;
        
        $avgPerChild = $uniqueChildren > 0 ? round($total / $uniqueChildren, 1) : 0;
        
        // Последний тест
        $lastTest = $this->db->fetch(
            "SELECT MAX(test_date) as last_date FROM {$this->tableName} WHERE psychologist_id = :psychologist_id",
            [':psychologist_id' => $psychologistId]
        );
        
        // Первый тест
        $firstTest = $this->db->fetch(
            "SELECT MIN(test_date) as first_date FROM {$this->tableName} WHERE psychologist_id = :psychologist_id",
            [':psychologist_id' => $psychologistId]
        );
        
        return [
            'total' => $total,
            'by_month' => $byMonth,
            'by_class' => $byClass,
            'unique_children' => $uniqueChildren,
            'avg_per_child' => $avgPerChild,
            'last_test_date' => $lastTest['last_date'] ?? null,
            'first_test_date' => $firstTest['first_date'] ?? null
        ];
    }
    
    /**
     * Получение распределения результатов (для тестов с числовыми результатами)
     * 
     * @param int $psychologistId ID психолога
     * @param string $fieldName Имя поля с результатом
     * @return array
     */
    public function getResultsDistribution(int $psychologistId, string $fieldName = 'total_score'): array
    {
        // Проверка существования поля
        if (!$this->fieldExists($fieldName)) {
            return [];
        }
        
        return $this->db->fetchAll(
            "SELECT {$fieldName}, COUNT(*) as count 
             FROM {$this->tableName} 
             WHERE psychologist_id = :psychologist_id 
             AND {$fieldName} IS NOT NULL 
             GROUP BY {$fieldName} 
             ORDER BY {$fieldName}",
            [':psychologist_id' => $psychologistId]
        );
    }
    
    /**
     * Получение средних значений (для тестов с числовыми результатами)
     * 
     * @param int $psychologistId ID психолога
     * @param array $fieldNames Имена полей
     * @return array
     */
    public function getAverageValues(int $psychologistId, array $fieldNames = ['total_score']): array
    {
        $averages = [];
        
        foreach ($fieldNames as $fieldName) {
            if ($this->fieldExists($fieldName)) {
                $result = $this->db->fetch(
                    "SELECT AVG({$fieldName}) as avg_value 
                     FROM {$this->tableName} 
                     WHERE psychologist_id = :psychologist_id 
                     AND {$fieldName} IS NOT NULL",
                    [':psychologist_id' => $psychologistId]
                );
                
                $averages[$fieldName] = $result ? round($result['avg_value'], 2) : null;
            }
        }
        
        return $averages;
    }
    
    /**
     * Экспорт данных результатов
     * 
     * @param int $psychologistId ID психолога
     * @param array $filters Фильтры
     * @return array
     */
    public function exportData(int $psychologistId, array $filters = []): array
    {
        $results = $this->findByPsychologist($psychologistId, $filters);
        
        // Добавление дополнительной информации
        foreach ($results as &$result) {
            // Интерпретация результата
            if (method_exists($this, 'interpret')) {
                $interpretation = $this->interpret($result);
                if (is_array($interpretation)) {
                    $result = array_merge($result, $interpretation);
                }
            }
            
            // Форматирование дат
            if (!empty($result['test_date'])) {
                $result['test_date_formatted'] = date('d.m.Y', strtotime($result['test_date']));
            }
            
            if (!empty($result['created_at'])) {
                $result['created_at_formatted'] = date('d.m.Y H:i', strtotime($result['created_at']));
            }
            
            // Полное имя ребенка
            $result['child_full_name'] = $result['last_name'] . ' ' . $result['first_name'];
        }
        
        return $results;
    }
    
    /**
     * Проверка существования поля в таблице
     * 
     * @param string $fieldName Имя поля
     * @return bool
     */
    public function fieldExists(string $fieldName): bool
    {
        try {
            $columns = $this->db->fetchAll("SHOW COLUMNS FROM {$this->tableName}");
            
            foreach ($columns as $column) {
                if ($column['Field'] === $fieldName) {
                    return true;
                }
            }
            
            return false;
            
        } catch (PDOException $e) {
            error_log("Ошибка при проверке поля: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Получение списка специфичных полей теста
     * 
     * @return array
     */
    protected function getSpecificFields(): array
    {
        // Должен быть переопределен в конкретных модулях
        return [];
    }
    
    /**
     * Валидация данных результата теста
     * 
     * @param array $data Данные для валидации
     * @param int|null $resultId ID результата (для исключения при проверке)
     * @return array Массив ошибок
     */
    protected function validate(array $data, ?int $resultId = null): array
    {
        $errors = [];
        
        // Проверка даты теста
        if (empty($data['test_date'])) {
            $errors[] = 'Дата теста обязательна';
        } else {
            $date = \DateTime::createFromFormat('Y-m-d', $data['test_date']);
            if (!$date || $date->format('Y-m-d') !== $data['test_date']) {
                $errors[] = 'Неверный формат даты теста';
            } else {
                // Дата не может быть в будущем
                $today = new \DateTime();
                if ($date > $today) {
                    $errors[] = 'Дата теста не может быть в будущем';
                }
                
                // Дата не может быть слишком старой (более 10 лет)
                $minDate = new \DateTime('-10 years');
                if ($date < $minDate) {
                    $errors[] = 'Дата теста не может быть более 10 лет назад';
                }
            }
        }
        
        // Проверка уникальности (тест у ребенка в эту дату)
        if (!empty($data['child_id']) && !empty($data['test_date'])) {
            $sql = "SELECT COUNT(*) as count FROM {$this->tableName} 
                    WHERE child_id = :child_id 
                    AND test_date = :test_date 
                    AND psychologist_id = :psychologist_id";
            
            $params = [
                ':child_id' => $data['child_id'],
                ':test_date' => $data['test_date'],
                ':psychologist_id' => $data['psychologist_id']
            ];
            
            if ($resultId) {
                $sql .= " AND id != :result_id";
                $params[':result_id'] = $resultId;
            }
            
            $existing = $this->db->fetch($sql, $params);
            
            if ($existing && $existing['count'] > 0) {
                $errors[] = 'Тест у этого ребенка в эту дату уже существует';
            }
        }
        
        return $errors;
    }
    
    /**
     * Расчет итоговых показателей теста
     * Должен быть переопределен в конкретных модулях
     * 
     * @param array $data Данные теста
     * @return array Расчетные показатели
     */
    protected function calculate(array $data): array
    {
        // Должен быть переопределен в конкретных модулях
        return [];
    }
    
    /**
     * Интерпретация результата теста
     * Должен быть переопределен в конкретных модулях
     * 
     * @param array $data Данные результата
     * @return array Интерпретация
     */
    protected function interpret(array $data): array
    {
        // Должен быть переопределен в конкретных модулях
        return [];
    }
    
    /**
     * Проверка доступа к ребенку
     * 
     * @param int $childId ID ребенка
     * @param int $psychologistId ID психолога
     * @return bool
     */
    protected function canAccessChild(int $childId, int $psychologistId): bool
    {
        $result = $this->db->fetch(
            "SELECT COUNT(*) as count FROM children 
             WHERE id = :child_id AND psychologist_id = :psychologist_id",
            [
                ':child_id' => $childId,
                ':psychologist_id' => $psychologistId
            ]
        );
        
        return $result && $result['count'] > 0;
    }
    
    /**
     * Расчет возраста
     * 
     * @param string|null $birthDate Дата рождения
     * @return string
     */
    protected function calculateAge(?string $birthDate): string
    {
        if (!$birthDate) {
            return 'не указано';
        }
        
        $birth = new \DateTime($birthDate);
        $now = new \DateTime();
        
        if ($birth > $now) {
            return 'некорректная дата';
        }
        
        $interval = $now->diff($birth);
        $years = $interval->y;
        
        if ($years == 0) {
            $months = $interval->m;
            if ($months == 0) {
                return 'менее месяца';
            }
            return $months . ' мес.';
        }
        
        return $years . ' лет';
    }
    
    /**
     * Логирование изменений результата теста
     * 
     * @param int $resultId ID результата
     * @param int $psychologistId ID психолога
     * @param string $action Действие
     * @param string $description Описание
     * @param array $details Детали
     * @return bool
     */
    protected function logChange(int $resultId, int $psychologistId, string $action, string $description, array $details = []): bool
    {
        try {
            // Проверка существования таблицы логов
            if (!$this->db->tableExists('test_result_history')) {
                $this->createHistoryTable();
            }
            
            $result = $this->db->insert('test_result_history', [
                'result_id' => $resultId,
                'table_name' => $this->tableName,
                'psychologist_id' => $psychologistId,
                'action' => $action,
                'description' => $description,
                'details' => !empty($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            return $result > 0;
            
        } catch (PDOException $e) {
            error_log("Ошибка при логировании изменений результата теста: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Создание таблицы истории изменений результатов тестов
     */
    private function createHistoryTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS test_result_history (
            id INT PRIMARY KEY AUTO_INCREMENT,
            result_id INT NOT NULL,
            table_name VARCHAR(100) NOT NULL,
            psychologist_id INT NOT NULL,
            action VARCHAR(50) NOT NULL,
            description TEXT,
            details TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_result (result_id),
            INDEX idx_table (table_name),
            INDEX idx_psychologist (psychologist_id),
            INDEX idx_action (action),
            INDEX idx_created (created_at)
        )";
        
        $this->db->query($sql);
    }
    
    /**
     * Получение истории изменений результата теста
     * 
     * @param int $resultId ID результата
     * @param int $limit Количество записей
     * @return array
     */
    public function getChangeHistory(int $resultId, int $limit = 20): array
    {
        try {
            // Проверка существования таблицы истории
            if (!$this->db->tableExists('test_result_history')) {
                return [];
            }
            
            return $this->db->fetchAll(
                "SELECT * FROM test_result_history 
                 WHERE result_id = :result_id AND table_name = :table_name 
                 ORDER BY created_at DESC 
                 LIMIT :limit",
                [
                    ':result_id' => $resultId,
                    ':table_name' => $this->tableName,
                    ':limit' => $limit
                ]
            );
            
        } catch (PDOException $e) {
            error_log("Ошибка при получении истории изменений: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Поиск результатов
     * 
     * @param int $psychologistId ID психолога
     * @param array $criteria Критерии поиска
     * @return array
     */
    public function search(int $psychologistId, array $criteria): array
    {
        $sql = "SELECT t.*, c.first_name, c.last_name, c.class 
                FROM {$this->tableName} t
                LEFT JOIN children c ON t.child_id = c.id
                WHERE t.psychologist_id = :psychologist_id";
        
        $params = [':psychologist_id' => $psychologistId];
        
        if (!empty($criteria['child_name'])) {
            $sql .= " AND (c.first_name LIKE :child_name OR c.last_name LIKE :child_name)";
            $params[':child_name'] = '%' . $criteria['child_name'] . '%';
        }
        
        if (!empty($criteria['class'])) {
            $sql .= " AND c.class = :class";
            $params[':class'] = $criteria['class'];
        }
        
        if (!empty($criteria['date_from'])) {
            $sql .= " AND t.test_date >= :date_from";
            $params[':date_from'] = $criteria['date_from'];
        }
        
        if (!empty($criteria['date_to'])) {
            $sql .= " AND t.test_date <= :date_to";
            $params[':date_to'] = $criteria['date_to'];
        }
        
        // Поиск по специфичным полям
        $specificFields = $this->getSpecificFields();
        foreach ($specificFields as $field) {
            if (!empty($criteria[$field])) {
                if (strpos($criteria[$field], '-') !== false) {
                    // Диапазон значений
                    list($min, $max) = explode('-', $criteria[$field]);
                    $sql .= " AND t.{$field} BETWEEN :{$field}_min AND :{$field}_max";
                    $params[":{$field}_min"] = $min;
                    $params[":{$field}_max"] = $max;
                } else {
                    // Точное значение
                    $sql .= " AND t.{$field} = :{$field}";
                    $params[":{$field}"] = $criteria[$field];
                }
            }
        }
        
        $sql .= " ORDER BY t.test_date DESC";
        
        if (!empty($criteria['limit'])) {
            $sql .= " LIMIT :limit";
            $params[':limit'] = (int)$criteria['limit'];
        }
        
        $results = $this->db->fetchAll($sql, $params);
        
        // Добавление интерпретации
        foreach ($results as &$result) {
            if (method_exists($this, 'interpret')) {
                $interpretation = $this->interpret($result);
                if (is_array($interpretation)) {
                    $result = array_merge($result, $interpretation);
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Получение структуры таблицы результатов
     * 
     * @return array
     */
    public function getTableStructure(): array
    {
        try {
            return $this->db->fetchAll("DESCRIBE {$this->tableName}");
        } catch (PDOException $e) {
            error_log("Ошибка при получении структуры таблицы: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Получение сводной статистики по результатам
     * 
     * @param int $psychologistId ID психолога
     * @return array
     */
    public function getSummaryStats(int $psychologistId): array
    {
        $stats = $this->getStats($psychologistId);
        
        // Добавление специфичной статистики, если есть метод
        if (method_exists($this, 'getSpecificStats')) {
            $specificStats = $this->getSpecificStats($psychologistId);
            if (is_array($specificStats)) {
                $stats = array_merge($stats, $specificStats);
            }
        }
        
        return $stats;
    }
    
    /**
     * Получение последних результатов
     * 
     * @param int $psychologistId ID психолога
     * @param int $limit Количество записей
     * @return array
     */
    public function getRecentResults(int $psychologistId, int $limit = 10): array
    {
        return $this->findByPsychologist($psychologistId, [
            'limit' => $limit,
            'order_by' => 't.created_at DESC'
        ]);
    }
    
    /**
     * Проверка существования результата
     * 
     * @param int $id ID результата
     * @param int $psychologistId ID психолога
     * @return bool
     */
    public function exists(int $id, int $psychologistId): bool
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->tableName} 
                WHERE id = :id AND psychologist_id = :psychologist_id";
        
        $result = $this->db->fetch($sql, [
            ':id' => $id,
            ':psychologist_id' => $psychologistId
        ]);
        
        return $result && $result['count'] > 0;
    }
}