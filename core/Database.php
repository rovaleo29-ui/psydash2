<?php
/**
 * Класс для работы с базой данных (обертка над PDO)
 * 
 * @package Core
 */

namespace Core;

use PDO;
use PDOException;
use PDOStatement;
use Exception;

class Database
{
    /**
     * @var PDO Экземпляр PDO
     */
    private $pdo;
    
    /**
     * @var array Конфигурация базы данных
     */
    private $config;
    
    /**
     * @var int Количество выполненных запросов
     */
    private $queryCount = 0;
    
    /**
     * Конструктор класса
     * 
     * @param string $host Хост базы данных
     * @param string $dbname Имя базы данных
     * @param string $username Имя пользователя
     * @param string $password Пароль
     * @param string $charset Кодировка
     */
    public function __construct(string $host, string $dbname, string $username, string $password, string $charset = 'utf8mb4')
    {
        $this->config = [
            'host' => $host,
            'dbname' => $dbname,
            'username' => $username,
            'password' => $password,
            'charset' => $charset
        ];
        
        $this->connect();
    }
    
    /**
     * Подключение к базе данных
     * 
     * @throws PDOException
     */
    private function connect(): void
    {
        try {
            $dsn = "mysql:host={$this->config['host']};dbname={$this->config['dbname']};charset={$this->config['charset']}";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->config['charset']}"
            ];
            
            $this->pdo = new PDO($dsn, $this->config['username'], $this->config['password'], $options);
            
        } catch (PDOException $e) {
            throw new PDOException("Ошибка подключения к базе данных: " . $e->getMessage());
        }
    }
    
    /**
     * Выполнение SQL запроса
     * 
     * @param string $sql SQL запрос
     * @param array $params Параметры запроса
     * @return PDOStatement
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            $this->queryCount++;
            
            return $stmt;
            
        } catch (PDOException $e) {
            error_log("SQL Error: " . $e->getMessage() . " | Query: " . $sql);
            throw new PDOException("Ошибка выполнения запроса: " . $e->getMessage());
        }
    }
    
    /**
     * Получение одной записи
     * 
     * @param string $sql SQL запрос
     * @param array $params Параметры запроса
     * @return array|null
     */
    public function fetch(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        
        return $result !== false ? $result : null;
    }
    
    /**
     * Получение всех записей
     * 
     * @param string $sql SQL запрос
     * @param array $params Параметры запроса
     * @return array
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Вставка данных в таблицу
     * 
     * @param string $table Имя таблицы
     * @param array $data Данные для вставки
     * @return int ID последней вставленной записи
     */
    public function insert(string $table, array $data): int
    {
        // Подготовка полей и значений
        $fields = array_keys($data);
        $placeholders = array_map(function($field) {
            return ':' . $field;
        }, $fields);
        
        // Создание SQL запроса
        $sql = "INSERT INTO {$table} (" . implode(', ', $fields) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
        
        // Выполнение запроса
        $this->query($sql, $data);
        
        return (int) $this->pdo->lastInsertId();
    }
    
    /**
     * Обновление данных в таблице
     * 
     * @param string $table Имя таблицы
     * @param array $data Данные для обновления
     * @param array $where Условия WHERE
     * @return int Количество обновленных строк
     */
    public function update(string $table, array $data, array $where): int
    {
        // Подготовка SET части
        $setParts = [];
        foreach (array_keys($data) as $field) {
            $setParts[] = "{$field} = :{$field}";
        }
        
        // Подготовка WHERE части
        $whereParts = [];
        $whereParams = [];
        
        foreach ($where as $field => $value) {
            $whereParts[] = "{$field} = :where_{$field}";
            $whereParams[":where_{$field}"] = $value;
        }
        
        // Создание SQL запроса
        $sql = "UPDATE {$table} 
                SET " . implode(', ', $setParts) . " 
                WHERE " . implode(' AND ', $whereParts);
        
        // Объединение параметров
        $params = array_merge($data, $whereParams);
        
        // Выполнение запроса
        $stmt = $this->query($sql, $params);
        
        return $stmt->rowCount();
    }
    
    /**
     * Удаление данных из таблицы
     * 
     * @param string $table Имя таблицы
     * @param array $where Условия WHERE
     * @return int Количество удаленных строк
     */
    public function delete(string $table, array $where): int
    {
        // Подготовка WHERE части
        $whereParts = [];
        $params = [];
        
        foreach ($where as $field => $value) {
            $whereParts[] = "{$field} = :{$field}";
            $params[":{$field}"] = $value;
        }
        
        // Создание SQL запроса
        $sql = "DELETE FROM {$table} 
                WHERE " . implode(' AND ', $whereParts);
        
        // Выполнение запроса
        $stmt = $this->query($sql, $params);
        
        return $stmt->rowCount();
    }
    
    /**
     * Получение детей психолога с фильтрами
     * 
     * @param int $psychologistId ID психолога
     * @param array $filters Фильтры
     * @return array
     */
    public function getChildren(int $psychologistId, array $filters = []): array
    {
        $sql = "SELECT * FROM children WHERE psychologist_id = :psychologist_id";
        $params = [':psychologist_id' => $psychologistId];
        
        // Добавление фильтров
        if (!empty($filters['class'])) {
            $sql .= " AND class = :class";
            $params[':class'] = $filters['class'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (first_name LIKE :search OR last_name LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        // Добавление сортировки
        $sql .= " ORDER BY last_name, first_name";
        
        // Добавление лимита
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT :limit";
            $params[':limit'] = (int)$filters['limit'];
        }
        
        return $this->fetchAll($sql, $params);
    }
    
    /**
     * Проверка существования таблицы
     * 
     * @param string $tableName Имя таблицы
     * @return bool
     */
    public function tableExists(string $tableName): bool
    {
        $sql = "SHOW TABLES LIKE :table_name";
        $params = [':table_name' => $tableName];
        
        $result = $this->fetch($sql, $params);
        
        return $result !== null;
    }
    
    /**
     * Выполнение SQL файла
     * 
     * @param string $sqlFile Путь к SQL файлу
     * @return bool
     */
    public function executeFile(string $sqlFile): bool
    {
        if (!file_exists($sqlFile)) {
            throw new Exception("SQL файл не найден: {$sqlFile}");
        }
        
        $sql = file_get_contents($sqlFile);
        
        // Разделение SQL запросов
        $queries = array_filter(array_map('trim', explode(';', $sql)));
        
        // Выполнение каждого запроса
        foreach ($queries as $query) {
            if (!empty($query)) {
                $this->query($query);
            }
        }
        
        return true;
    }
    
    /**
     * Начало транзакции
     * 
     * @return bool
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Подтверждение транзакции
     * 
     * @return bool
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }
    
    /**
     * Откат транзакции
     * 
     * @return bool
     */
    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }
    
    /**
     * Экранирование строки
     * 
     * @param string $string Строка для экранирования
     * @return string
     */
    public function quote(string $string): string
    {
        return $this->pdo->quote($string);
    }
    
    /**
     * Получение количества выполненных запросов
     * 
     * @return int
     */
    public function getQueryCount(): int
    {
        return $this->queryCount;
    }
    
    /**
     * Получение объекта PDO
     * 
     * @return PDO
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }
    
    /**
     * Проверка подключения к базе данных
     * 
     * @return bool
     */
    public function isConnected(): bool
    {
        try {
            $this->pdo->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Закрытие соединения
     */
    public function close(): void
    {
        $this->pdo = null;
    }
}