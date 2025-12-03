<?php
/**
 * Модель ребенка
 * 
 * @package App\Models
 */

namespace App\Models;

use Core\Database;
use PDOException;
use Exception;

class Child
{
    /**
     * @var Database Объект базы данных
     */
    private $db;
    
    /**
     * @var string Имя таблицы в БД
     */
    private $table = 'children';
    
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
     * Создание нового ребенка
     * 
     * @param array $data Данные ребенка
     * @return int ID созданного ребенка
     * @throws Exception
     */
    public function create(array $data): int
    {
        // Валидация обязательных полей
        $required = ['psychologist_id', 'first_name', 'last_name', 'class'];
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
        
        // Проверка уникальности (ребенок с таким именем, фамилией и классом у этого психолога)
        if ($this->exists($data['psychologist_id'], $data['first_name'], $data['last_name'], $data['class'])) {
            throw new Exception("Ребенок с таким именем, фамилией и классом уже существует");
        }
        
        // Подготовка данных для вставки
        $childData = [
            'psychologist_id' => (int)$data['psychologist_id'],
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'class' => trim($data['class']),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Дополнительные поля
        if (!empty($data['birth_date'])) {
            $childData['birth_date'] = $data['birth_date'];
        }
        
        if (!empty($data['notes'])) {
            $childData['notes'] = trim($data['notes']);
        }
        
        if (!empty($data['gender'])) {
            $childData['gender'] = in_array($data['gender'], ['male', 'female']) ? $data['gender'] : null;
        }
        
        if (!empty($data['photo_path'])) {
            $childData['photo_path'] = trim($data['photo_path']);
        }
        
        if (!empty($data['parent_phone'])) {
            $childData['parent_phone'] = trim($data['parent_phone']);
        }
        
        if (!empty($data['parent_email'])) {
            if (!filter_var($data['parent_email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Некорректный формат email родителя");
            }
            $childData['parent_email'] = trim($data['parent_email']);
        }
        
        if (!empty($data['address'])) {
            $childData['address'] = trim($data['address']);
        }
        
        if (!empty($data['health_info'])) {
            $childData['health_info'] = trim($data['health_info']);
        }
        
        // Вставка в БД
        $childId = $this->db->insert($this->table, $childData);
        
        // Логирование создания
        $this->logChange($childId, $data['psychologist_id'], 'create', 'Создан новый ребенок');
        
        return $childId;
    }
    
    /**
     * Получение ребенка по ID
     * 
     * @param int $id ID ребенка
     * @param int $psychologistId ID психолога (для проверки прав)
     * @return array|null Данные ребенка или null
     */
    public function findById(int $id, int $psychologistId): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id AND psychologist_id = :psychologist_id";
        $child = $this->db->fetch($sql, [
            ':id' => $id,
            ':psychologist_id' => $psychologistId
        ]);
        
        if ($child) {
            $child['age'] = $this->calculateAge($child['birth_date']);
            $child['full_name'] = $child['last_name'] . ' ' . $child['first_name'];
        }
        
        return $child;
    }
    
    /**
     * Получение всех детей психолога
     * 
     * @param int $psychologistId ID психолога
     * @param array $filters Фильтры
     * @return array
     */
    public function findByPsychologist(int $psychologistId, array $filters = []): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE psychologist_id = :psychologist_id";
        $params = [':psychologist_id' => $psychologistId];
        
        // Применение фильтров
        if (!empty($filters['class'])) {
            $sql .= " AND class = :class";
            $params[':class'] = $filters['class'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (first_name LIKE :search OR last_name LIKE :search OR class LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        if (!empty($filters['gender'])) {
            $sql .= " AND gender = :gender";
            $params[':gender'] = $filters['gender'];
        }
        
        if (!empty($filters['has_birth_date'])) {
            if ($filters['has_birth_date'] === 'yes') {
                $sql .= " AND birth_date IS NOT NULL";
            } else {
                $sql .= " AND birth_date IS NULL";
            }
        }
        
        // Сортировка
        $orderBy = $filters['order_by'] ?? 'last_name, first_name';
        $orderDir = isset($filters['order_dir']) && strtoupper($filters['order_dir']) === 'DESC' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY {$orderBy} {$orderDir}";
        
        // Пагинация
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT :limit";
            $params[':limit'] = (int)$filters['limit'];
            
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET :offset";
                $params[':offset'] = (int)$filters['offset'];
            }
        }
        
        $children = $this->db->fetchAll($sql, $params);
        
        // Добавление возраста и полного имени
        foreach ($children as &$child) {
            $child['age'] = $this->calculateAge($child['birth_date']);
            $child['full_name'] = $child['last_name'] . ' ' . $child['first_name'];
        }
        
        return $children;
    }
    
    /**
     * Подсчет детей психолога
     * 
     * @param int $psychologistId ID психолога
     * @param array $filters Фильтры
     * @return int
     */
    public function countByPsychologist(int $psychologistId, array $filters = []): int
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE psychologist_id = :psychologist_id";
        $params = [':psychologist_id' => $psychologistId];
        
        if (!empty($filters['class'])) {
            $sql .= " AND class = :class";
            $params[':class'] = $filters['class'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (first_name LIKE :search OR last_name LIKE :search OR class LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        if (!empty($filters['gender'])) {
            $sql .= " AND gender = :gender";
            $params[':gender'] = $filters['gender'];
        }
        
        $result = $this->db->fetch($sql, $params);
        return $result ? (int)$result['count'] : 0;
    }
    
    /**
     * Обновление данных ребенка
     * 
     * @param int $id ID ребенка
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
            throw new Exception('Ребенок не найден');
        }
        
        // Валидация данных (если переданы для изменения)
        $validationData = array_merge($current, $data);
        $errors = $this->validate($validationData, $id);
        if (!empty($errors)) {
            throw new Exception(implode(', ', $errors));
        }
        
        // Подготовка данных для обновления
        $updateData = [];
        
        if (isset($data['first_name']) && $data['first_name'] !== $current['first_name']) {
            $updateData['first_name'] = trim($data['first_name']);
        }
        
        if (isset($data['last_name']) && $data['last_name'] !== $current['last_name']) {
            $updateData['last_name'] = trim($data['last_name']);
        }
        
        if (isset($data['class']) && $data['class'] !== $current['class']) {
            $updateData['class'] = trim($data['class']);
        }
        
        if (isset($data['birth_date'])) {
            $newBirthDate = !empty($data['birth_date']) ? $data['birth_date'] : null;
            if ($newBirthDate != $current['birth_date']) {
                $updateData['birth_date'] = $newBirthDate;
            }
        }
        
        if (isset($data['notes']) && $data['notes'] !== $current['notes']) {
            $updateData['notes'] = trim($data['notes']);
        }
        
        if (isset($data['gender']) && $data['gender'] !== $current['gender']) {
            $updateData['gender'] = in_array($data['gender'], ['male', 'female']) ? $data['gender'] : null;
        }
        
        if (isset($data['photo_path']) && $data['photo_path'] !== $current['photo_path']) {
            $updateData['photo_path'] = trim($data['photo_path']);
        }
        
        if (isset($data['parent_phone']) && $data['parent_phone'] !== $current['parent_phone']) {
            $updateData['parent_phone'] = trim($data['parent_phone']);
        }
        
        if (isset($data['parent_email'])) {
            $newParentEmail = !empty($data['parent_email']) ? trim($data['parent_email']) : null;
            if ($newParentEmail != $current['parent_email']) {
                if ($newParentEmail && !filter_var($newParentEmail, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Некорректный формат email родителя");
                }
                $updateData['parent_email'] = $newParentEmail;
            }
        }
        
        if (isset($data['address']) && $data['address'] !== $current['address']) {
            $updateData['address'] = trim($data['address']);
        }
        
        if (isset($data['health_info']) && $data['health_info'] !== $current['health_info']) {
            $updateData['health_info'] = trim($data['health_info']);
        }
        
        if (empty($updateData)) {
            return false;
        }
        
        $updateData['updated_at'] = date('Y-m-d H:i:s');
        
        // Проверка уникальности (если изменены имя, фамилия или класс)
        if (isset($updateData['first_name']) || isset($updateData['last_name']) || isset($updateData['class'])) {
            $firstName = $updateData['first_name'] ?? $current['first_name'];
            $lastName = $updateData['last_name'] ?? $current['last_name'];
            $class = $updateData['class'] ?? $current['class'];
            
            if ($this->exists($psychologistId, $firstName, $lastName, $class, $id)) {
                throw new Exception("Ребенок с таким именем, фамилией и классом уже существует");
            }
        }
        
        // Обновление в БД
        $result = $this->db->update(
            $this->table,
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
     * Удаление ребенка
     * 
     * @param int $id ID ребенка
     * @param int $psychologistId ID психолога
     * @param bool $force Принудительное удаление (без проверки связанных данных)
     * @return bool
     * @throws Exception
     */
    public function delete(int $id, int $psychologistId, bool $force = false): bool
    {
        // Проверка существования ребенка
        $child = $this->findById($id, $psychologistId);
        if (!$child) {
            throw new Exception('Ребенок не найден');
        }
        
        // Проверка наличия связанных тестов (если не принудительное удаление)
        if (!$force) {
            $hasTests = $this->hasTestResults($id);
            if ($hasTests) {
                throw new Exception('Невозможно удалить ребенка, у которого есть результаты тестов');
            }
        }
        
        // Логирование перед удалением
        $this->logChange($id, $psychologistId, 'delete', 'Удален ребенок: ' . $child['last_name'] . ' ' . $child['first_name']);
        
        // Удаление из БД (каскадное удаление тестов через foreign key)
        $result = $this->db->delete(
            $this->table,
            ['id' => $id, 'psychologist_id' => $psychologistId]
        );
        
        return $result > 0;
    }
    
    /**
     * Проверка существования ребенка
     * 
     * @param int $psychologistId ID психолога
     * @param string $firstName Имя
     * @param string $lastName Фамилия
     * @param string $class Класс
     * @param int|null $excludeId ID ребенка для исключения (при обновлении)
     * @return bool
     */
    public function exists(int $psychologistId, string $firstName, string $lastName, string $class, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                WHERE psychologist_id = :psychologist_id 
                AND first_name = :first_name 
                AND last_name = :last_name 
                AND class = :class";
        
        $params = [
            ':psychologist_id' => $psychologistId,
            ':first_name' => trim($firstName),
            ':last_name' => trim($lastName),
            ':class' => trim($class)
        ];
        
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }
        
        $result = $this->db->fetch($sql, $params);
        return $result && $result['count'] > 0;
    }
    
    /**
     * Проверка наличия результатов тестов у ребенка
     * 
     * @param int $childId ID ребенка
     * @return bool
     */
    public function hasTestResults(int $childId): bool
    {
        // Получение активных модулей
        $modules = $this->db->fetchAll("SELECT module_key FROM test_modules WHERE is_active = 1");
        
        foreach ($modules as $module) {
            $tableName = 'test_' . $module['module_key'] . '_results';
            
            if ($this->db->tableExists($tableName)) {
                $result = $this->db->fetch(
                    "SELECT COUNT(*) as count FROM {$tableName} WHERE child_id = :child_id",
                    [':child_id' => $childId]
                );
                
                if ($result && $result['count'] > 0) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Получение статистики по детям
     * 
     * @param int $psychologistId ID психолога
     * @return array
     */
    public function getStats(int $psychologistId): array
    {
        // Общее количество детей
        $total = $this->countByPsychologist($psychologistId);
        
        // Распределение по классам
        $byClass = $this->db->fetchAll(
            "SELECT class, COUNT(*) as count 
             FROM {$this->table} 
             WHERE psychologist_id = :psychologist_id 
             GROUP BY class 
             ORDER BY class",
            [':psychologist_id' => $psychologistId]
        );
        
        // Распределение по полу
        $byGender = $this->db->fetchAll(
            "SELECT gender, COUNT(*) as count 
             FROM {$this->table} 
             WHERE psychologist_id = :psychologist_id 
             AND gender IS NOT NULL 
             GROUP BY gender",
            [':psychologist_id' => $psychologistId]
        );
        
        // Дети с датой рождения
        $withBirthDate = $this->db->fetch(
            "SELECT COUNT(*) as count 
             FROM {$this->table} 
             WHERE psychologist_id = :psychologist_id 
             AND birth_date IS NOT NULL",
            [':psychologist_id' => $psychologistId]
        )['count'] ?? 0;
        
        // Средний возраст
        $avgAge = null;
        if ($withBirthDate > 0) {
            $ageResult = $this->db->fetch(
                "SELECT AVG(TIMESTAMPDIFF(YEAR, birth_date, CURDATE())) as avg_age 
                 FROM {$this->table} 
                 WHERE psychologist_id = :psychologist_id 
                 AND birth_date IS NOT NULL",
                [':psychologist_id' => $psychologistId]
            );
            
            if ($ageResult && $ageResult['avg_age']) {
                $avgAge = round($ageResult['avg_age'], 1);
            }
        }
        
        // Дети добавленные за последние 30 дней
        $recent = $this->db->fetch(
            "SELECT COUNT(*) as count 
             FROM {$this->table} 
             WHERE psychologist_id = :psychologist_id 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [':psychologist_id' => $psychologistId]
        )['count'] ?? 0;
        
        return [
            'total' => $total,
            'by_class' => $byClass,
            'by_gender' => $byGender,
            'with_birth_date' => $withBirthDate,
            'without_birth_date' => $total - $withBirthDate,
            'avg_age' => $avgAge,
            'recent' => $recent
        ];
    }
    
    /**
     * Получение уникальных классов
     * 
     * @param int $psychologistId ID психолога
     * @return array
     */
    public function getUniqueClasses(int $psychologistId): array
    {
        return $this->db->fetchAll(
            "SELECT DISTINCT class 
             FROM {$this->table} 
             WHERE psychologist_id = :psychologist_id 
             ORDER BY class",
            [':psychologist_id' => $psychologistId]
        );
    }
    
    /**
     * Получение истории изменений ребенка
     * 
     * @param int $childId ID ребенка
     * @param int $limit Количество записей
     * @return array
     */
    public function getChangeHistory(int $childId, int $limit = 20): array
    {
        try {
            // Проверка существования таблицы истории
            if (!$this->db->tableExists('child_history')) {
                return [];
            }
            
            return $this->db->fetchAll(
                "SELECT * FROM child_history 
                 WHERE child_id = :child_id 
                 ORDER BY created_at DESC 
                 LIMIT :limit",
                [
                    ':child_id' => $childId,
                    ':limit' => $limit
                ]
            );
            
        } catch (PDOException $e) {
            error_log("Ошибка при получении истории изменений: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Получение результатов тестов ребенка
     * 
     * @param int $childId ID ребенка
     * @param int $psychologistId ID психолога
     * @return array
     */
    public function getTestResults(int $childId, int $psychologistId): array
    {
        $results = [];
        $modules = $this->db->fetchAll("SELECT * FROM test_modules WHERE is_active = 1");
        
        foreach ($modules as $module) {
            $tableName = 'test_' . $module['module_key'] . '_results';
            
            if (!$this->db->tableExists($tableName)) {
                continue;
            }
            
            $moduleResults = $this->db->fetchAll(
                "SELECT * FROM {$tableName} 
                 WHERE child_id = :child_id 
                 AND psychologist_id = :psychologist_id 
                 ORDER BY test_date DESC",
                [
                    ':child_id' => $childId,
                    ':psychologist_id' => $psychologistId
                ]
            );
            
            if (!empty($moduleResults)) {
                $results[$module['module_key']] = [
                    'module_name' => $module['name'],
                    'module_key' => $module['module_key'],
                    'category' => $module['category'] ?? 'общий',
                    'results' => $moduleResults,
                    'count' => count($moduleResults)
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Получение статистики тестов ребенка
     * 
     * @param int $childId ID ребенка
     * @param int $psychologistId ID психолога
     * @return array
     */
    public function getTestStats(int $childId, int $psychologistId): array
    {
        $testResults = $this->getTestResults($childId, $psychologistId);
        
        $totalTests = 0;
        $firstTestDate = null;
        $lastTestDate = null;
        $testsByModule = [];
        
        foreach ($testResults as $moduleKey => $moduleData) {
            $moduleTests = $moduleData['count'];
            $totalTests += $moduleTests;
            
            $testsByModule[] = [
                'name' => $moduleData['module_name'],
                'key' => $moduleKey,
                'count' => $moduleTests
            ];
            
            foreach ($moduleData['results'] as $result) {
                $testDate = $result['test_date'];
                
                if (!$firstTestDate || $testDate < $firstTestDate) {
                    $firstTestDate = $testDate;
                }
                
                if (!$lastTestDate || $testDate > $lastTestDate) {
                    $lastTestDate = $testDate;
                }
            }
        }
        
        return [
            'total_tests' => $totalTests,
            'first_test_date' => $firstTestDate,
            'last_test_date' => $lastTestDate,
            'tests_by_module' => $testsByModule
        ];
    }
    
    /**
     * Поиск детей
     * 
     * @param int $psychologistId ID психолога
     * @param array $criteria Критерии поиска
     * @return array
     */
    public function search(int $psychologistId, array $criteria): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE psychologist_id = :psychologist_id";
        $params = [':psychologist_id' => $psychologistId];
        
        if (!empty($criteria['first_name'])) {
            $sql .= " AND first_name LIKE :first_name";
            $params[':first_name'] = '%' . $criteria['first_name'] . '%';
        }
        
        if (!empty($criteria['last_name'])) {
            $sql .= " AND last_name LIKE :last_name";
            $params[':last_name'] = '%' . $criteria['last_name'] . '%';
        }
        
        if (!empty($criteria['class'])) {
            $sql .= " AND class = :class";
            $params[':class'] = $criteria['class'];
        }
        
        if (!empty($criteria['min_age'])) {
            $minBirthDate = date('Y-m-d', strtotime('-' . $criteria['min_age'] . ' years'));
            $sql .= " AND birth_date <= :min_birth_date";
            $params[':min_birth_date'] = $minBirthDate;
        }
        
        if (!empty($criteria['max_age'])) {
            $maxBirthDate = date('Y-m-d', strtotime('-' . $criteria['max_age'] . ' years'));
            $sql .= " AND birth_date >= :max_birth_date";
            $params[':max_birth_date'] = $maxBirthDate;
        }
        
        if (!empty($criteria['gender'])) {
            $sql .= " AND gender = :gender";
            $params[':gender'] = $criteria['gender'];
        }
        
        if (!empty($criteria['has_tests'])) {
            if ($criteria['has_tests'] === 'yes') {
                // Дети с хотя бы одним тестом
                $sql .= " AND id IN (SELECT DISTINCT child_id FROM test_results)";
            } else {
                // Дети без тестов
                $sql .= " AND id NOT IN (SELECT DISTINCT child_id FROM test_results)";
            }
        }
        
        $sql .= " ORDER BY last_name, first_name";
        
        if (!empty($criteria['limit'])) {
            $sql .= " LIMIT :limit";
            $params[':limit'] = (int)$criteria['limit'];
        }
        
        $children = $this->db->fetchAll($sql, $params);
        
        // Добавление возраста и полного имени
        foreach ($children as &$child) {
            $child['age'] = $this->calculateAge($child['birth_date']);
            $child['full_name'] = $child['last_name'] . ' ' . $child['first_name'];
        }
        
        return $children;
    }
    
    /**
     * Экспорт данных детей
     * 
     * @param int $psychologistId ID психолога
     * @param array $filters Фильтры
     * @return array
     */
    public function exportData(int $psychologistId, array $filters = []): array
    {
        $children = $this->findByPsychologist($psychologistId, $filters);
        
        // Добавление дополнительной информации
        foreach ($children as &$child) {
            // Статистика тестов
            $testStats = $this->getTestStats($child['id'], $psychologistId);
            $child['total_tests'] = $testStats['total_tests'];
            $child['first_test_date'] = $testStats['first_test_date'];
            $child['last_test_date'] = $testStats['last_test_date'];
            
            // Форматирование дат
            if (!empty($child['birth_date'])) {
                $child['birth_date_formatted'] = date('d.m.Y', strtotime($child['birth_date']));
            }
            
            if (!empty($child['created_at'])) {
                $child['created_at_formatted'] = date('d.m.Y H:i', strtotime($child['created_at']));
            }
            
            if (!empty($child['updated_at'])) {
                $child['updated_at_formatted'] = date('d.m.Y H:i', strtotime($child['updated_at']));
            }
            
            // Информация о возрасте
            $child['age_years'] = $this->calculateAgeYears($child['birth_date']);
            
            // Полное имя
            $child['full_name'] = $child['last_name'] . ' ' . $child['first_name'];
        }
        
        return $children;
    }
    
    /**
     * Получение детей без определенного теста
     * 
     * @param string $moduleKey Ключ модуля теста
     * @param int $psychologistId ID психолога
     * @return array
     */
    public function getChildrenWithoutTest(string $moduleKey, int $psychologistId): array
    {
        $tableName = 'test_' . $moduleKey . '_results';
        
        if (!$this->db->tableExists($tableName)) {
            // Если таблицы нет, возвращаем всех детей
            return $this->findByPsychologist($psychologistId);
        }
        
        $sql = "SELECT c.* 
                FROM {$this->table} c
                LEFT JOIN {$tableName} t ON c.id = t.child_id AND t.psychologist_id = c.psychologist_id
                WHERE c.psychologist_id = :psychologist_id 
                AND t.id IS NULL
                ORDER BY c.class, c.last_name, c.first_name";
        
        $children = $this->db->fetchAll($sql, [':psychologist_id' => $psychologistId]);
        
        // Добавление возраста и полного имени
        foreach ($children as &$child) {
            $child['age'] = $this->calculateAge($child['birth_date']);
            $child['full_name'] = $child['last_name'] . ' ' . $child['first_name'];
        }
        
        return $children;
    }
    
    /**
     * Расчет возраста
     * 
     * @param string|null $birthDate Дата рождения
     * @return string
     */
    public function calculateAge(?string $birthDate): string
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
            return $months . ' ' . $this->pluralize($months, 'месяц', 'месяца', 'месяцев');
        }
        
        return $years . ' ' . $this->pluralize($years, 'год', 'года', 'лет');
    }
    
    /**
     * Расчет возраста в годах
     * 
     * @param string|null $birthDate Дата рождения
     * @return int|null
     */
    public function calculateAgeYears(?string $birthDate): ?int
    {
        if (!$birthDate) {
            return null;
        }
        
        $birth = new \DateTime($birthDate);
        $now = new \DateTime();
        
        if ($birth > $now) {
            return null;
        }
        
        return $now->diff($birth)->y;
    }
    
    /**
     * Склонение слов
     * 
     * @param int $number Число
     * @param string $one Форма для 1
     * @param string $two Форма для 2-4
     * @param string $many Форма для 5+
     * @return string
     */
    private function pluralize(int $number, string $one, string $two, string $many): string
    {
        $mod10 = $number % 10;
        $mod100 = $number % 100;
        
        if ($mod10 == 1 && $mod100 != 11) {
            return $one;
        } elseif ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 10 || $mod100 >= 20)) {
            return $two;
        } else {
            return $many;
        }
    }
    
    /**
     * Валидация данных ребенка
     * 
     * @param array $data Данные для валидации
     * @param int|null $childId ID ребенка (для исключения при проверке уникальности)
     * @return array Массив ошибок
     */
    private function validate(array $data, ?int $childId = null): array
    {
        $errors = [];
        
        // Проверка имени
        if (empty($data['first_name'])) {
            $errors[] = 'Имя обязательно для заполнения';
        } else {
            $firstName = trim($data['first_name']);
            if (strlen($firstName) < 2) {
                $errors[] = 'Имя должно содержать не менее 2 символов';
            }
            if (strlen($firstName) > 50) {
                $errors[] = 'Имя должно содержать не более 50 символов';
            }
            if (!preg_match('/^[а-яА-ЯёЁ\- ]+$/u', $firstName)) {
                $errors[] = 'Имя может содержать только русские буквы, дефисы и пробелы';
            }
        }
        
        // Проверка фамилии
        if (empty($data['last_name'])) {
            $errors[] = 'Фамилия обязательна для заполнения';
        } else {
            $lastName = trim($data['last_name']);
            if (strlen($lastName) < 2) {
                $errors[] = 'Фамилия должна содержать не менее 2 символов';
            }
            if (strlen($lastName) > 50) {
                $errors[] = 'Фамилия должна содержать не более 50 символов';
            }
            if (!preg_match('/^[а-яА-ЯёЁ\- ]+$/u', $lastName)) {
                $errors[] = 'Фамилия может содержать только русские буквы, дефисы и пробелы';
            }
        }
        
        // Проверка класса
        if (empty($data['class'])) {
            $errors[] = 'Класс обязателен для заполнения';
        } else {
            $class = trim($data['class']);
            if (!preg_match('/^\d{1,2}[А-ЯA-Z]$/u', $class)) {
                $errors[] = 'Класс должен быть в формате: цифра(цифры) и буква (например: 5А, 10Б)';
            }
        }
        
        // Проверка даты рождения
        if (!empty($data['birth_date'])) {
            $date = \DateTime::createFromFormat('Y-m-d', $data['birth_date']);
            if (!$date || $date->format('Y-m-d') !== $data['birth_date']) {
                $errors[] = 'Неверный формат даты рождения';
            } else {
                $minDate = new \DateTime('-25 years');
                $maxDate = new \DateTime('-3 years');
                
                if ($date < $minDate) {
                    $errors[] = 'Дата рождения не может быть более 25 лет назад';
                } elseif ($date > $maxDate) {
                    $errors[] = 'Дата рождения не может быть менее 3 лет назад';
                }
            }
        }
        
        // Проверка номера телефона родителя
        if (!empty($data['parent_phone'])) {
            $phone = preg_replace('/[^0-9]/', '', $data['parent_phone']);
            if (strlen($phone) < 10) {
                $errors[] = 'Некорректный номер телефона родителя';
            }
        }
        
        return $errors;
    }
    
    /**
     * Логирование изменений ребенка
     * 
     * @param int $childId ID ребенка
     * @param int $psychologistId ID психолога
     * @param string $action Действие
     * @param string $description Описание
     * @param array $details Детали
     * @return bool
     */
    private function logChange(int $childId, int $psychologistId, string $action, string $description, array $details = []): bool
    {
        try {
            // Проверка существования таблицы истории
            if (!$this->db->tableExists('child_history')) {
                $this->createHistoryTable();
            }
            
            $result = $this->db->insert('child_history', [
                'child_id' => $childId,
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
            error_log("Ошибка при логировании изменений ребенка: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Создание таблицы истории изменений
     */
    private function createHistoryTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS child_history (
            id INT PRIMARY KEY AUTO_INCREMENT,
            child_id INT NOT NULL,
            psychologist_id INT NOT NULL,
            action VARCHAR(50) NOT NULL,
            description TEXT,
            details TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_child (child_id),
            INDEX idx_psychologist (psychologist_id),
            INDEX idx_action (action),
            INDEX idx_created (created_at),
            FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE
        )";
        
        $this->db->query($sql);
    }
}
