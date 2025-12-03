<?php
/**
 * Ядро системы управления психологическими данными
 * 
 * @package Core
 */

namespace Core;

use Core\Database;
use Core\Auth;
use Core\Template;
use Core\ModuleLoader;

class Core
{
    /**
     * @var Core|null Экземпляр синглтона
     */
    private static $instance = null;
    
    /**
     * @var Database Объект базы данных
     */
    private $db;
    
    /**
     * @var Auth Объект аутентификации
     */
    private $auth;
    
    /**
     * @var Template Объект шаблонизатора
     */
    private $template;
    
    /**
     * @var ModuleLoader Объект загрузчика модулей
     */
    private $moduleLoader;
    
    /**
     * @var array Конфигурация приложения
     */
    private $config;
    
    /**
     * Закрытый конструктор
     */
    private function __construct()
    {
        // Загрузка конфигурации
        $this->loadConfig();
        
        // Инициализация компонентов
        $this->initDatabase();
        $this->initAuth();
        $this->initTemplate();
        $this->initModuleLoader();
    }
    
    /**
     * Получение экземпляра ядра (синглтон)
     * 
     * @return Core
     */
    public static function getInstance(): Core
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Загрузка конфигурации
     */
    private function loadConfig(): void
    {
        $configPath = ROOT_PATH . '/config/app.php';
        
        if (file_exists($configPath)) {
            $this->config = require $configPath;
        } else {
            $this->config = [
                'app_name' => 'Система управления психологическими данными',
                'app_version' => '1.0.0',
                'debug' => false,
                'timezone' => 'Europe/Moscow',
                'uploads_path' => ROOT_PATH . '/storage/uploads',
                'max_upload_size' => 5 * 1024 * 1024 // 5MB
            ];
        }
    }
    
    /**
     * Инициализация базы данных
     */
    private function initDatabase(): void
    {
        $dbConfig = require ROOT_PATH . '/config/database.php';
        $this->db = new Database(
            $dbConfig['host'],
            $dbConfig['database'],
            $dbConfig['username'],
            $dbConfig['password'],
            $dbConfig['charset'] ?? 'utf8mb4'
        );
    }
    
    /**
     * Инициализация аутентификации
     */
    private function initAuth(): void
    {
        $this->auth = new Auth($this->db);
    }
    
    /**
     * Инициализация шаблонизатора
     */
    private function initTemplate(): void
    {
        $this->template = new Template(ROOT_PATH . '/templates');
        
        // Передача глобальных переменных в шаблоны
        $this->template->addGlobal('app_name', $this->config['app_name']);
        $this->template->addGlobal('app_version', $this->config['app_version']);
        $this->template->addGlobal('current_year', date('Y'));
    }
    
    /**
     * Инициализация загрузчика модулей
     */
    private function initModuleLoader(): void
    {
        $this->moduleLoader = new ModuleLoader(
            ROOT_PATH . '/modules',
            $this->db,
            $this->template
        );
    }
    
    /**
     * Получение объекта базы данных
     * 
     * @return Database
     */
    public function getDB(): Database
    {
        return $this->db;
    }
    
    /**
     * Получение объекта аутентификации
     * 
     * @return Auth
     */
    public function getAuth(): Auth
    {
        return $this->auth;
    }
    
    /**
     * Получение объекта шаблонизатора
     * 
     * @return Template
     */
    public function getTemplate(): Template
    {
        return $this->template;
    }
    
    /**
     * Получение объекта загрузчика модулей
     * 
     * @return ModuleLoader
     */
    public function getModuleLoader(): ModuleLoader
    {
        return $this->moduleLoader;
    }
    
    /**
     * Рендеринг страницы с общим шаблоном
     * 
     * @param string $content Содержимое страницы
     * @param string $title Заголовок страницы
     * @param array $data Дополнительные данные
     * @return string
     */
    public function renderPage(string $content, string $title = '', array $data = []): string
    {
        $data['page_title'] = $title;
        $data['page_content'] = $content;
        $data['current_user'] = $this->getCurrentPsychologist();
        
        return $this->template->render('layout.php', $data);
    }
    
    /**
     * Получение списка детей психолога
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
        
        $sql .= " ORDER BY last_name, first_name";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Получение информации о ребенке
     * 
     * @param int $id ID ребенка
     * @param int $psychologistId ID психолога (для проверки прав)
     * @return array|null
     */
    public function getChild(int $id, int $psychologistId): ?array
    {
        $sql = "SELECT * FROM children WHERE id = :id AND psychologist_id = :psychologist_id";
        $params = [
            ':id' => $id,
            ':psychologist_id' => $psychologistId
        ];
        
        return $this->db->fetch($sql, $params);
    }
    
    /**
     * Добавление ребенка
     * 
     * @param array $data Данные ребенка
     * @param int $psychologistId ID психолога
     * @return int ID нового ребенка
     */
    public function addChild(array $data, int $psychologistId): int
    {
        // Проверка обязательных полей
        $required = ['first_name', 'last_name', 'class'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Поле '$field' обязательно для заполнения");
            }
        }
        
        // Подготовка данных
        $childData = [
            'psychologist_id' => $psychologistId,
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'class' => trim($data['class']),
            'birth_date' => !empty($data['birth_date']) ? $data['birth_date'] : null,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->db->insert('children', $childData);
    }
    
    /**
     * Получение модуля теста
     * 
     * @param string $moduleKey Ключ модуля
     * @return object|null
     */
    public function getModule(string $moduleKey): ?object
    {
        return $this->moduleLoader->getModule($moduleKey);
    }
    
    /**
     * Получение списка активных модулей
     * 
     * @return array
     */
    public function getActiveModules(): array
    {
        return $this->moduleLoader->getActiveModules();
    }
    
    /**
     * Установка модуля
     * 
     * @param string $moduleKey Ключ модуля
     * @return bool
     */
    public function installModule(string $moduleKey): bool
    {
        return $this->moduleLoader->installModule($moduleKey);
    }
    
    /**
     * Вход в систему
     * 
     * @param string $username Имя пользователя (email или psychologist_id)
     * @param string $password Пароль
     * @return bool
     */
    public function login(string $username, string $password): bool
    {
        return $this->auth->login($username, $password);
    }
    
    /**
     * Выход из системы
     */
    public function logout(): void
    {
        $this->auth->logout();
    }
    
    /**
     * Получение текущего психолога
     * 
     * @return array|null
     */
    public function getCurrentPsychologist(): ?array
    {
        return $this->auth->getCurrentUser();
    }
    
    /**
     * Проверка аутентификации
     * 
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return $this->auth->isAuthenticated();
    }
    
    /**
     * Получение конфигурации
     * 
     * @param string|null $key Ключ конфигурации
     * @return mixed
     */
    public function getConfig(?string $key = null)
    {
        if ($key === null) {
            return $this->config;
        }
        
        return $this->config[$key] ?? null;
    }
    
    /**
     * Запрет клонирования
     */
    private function __clone() {}
    
    /**
     * Запрет десериализации
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}