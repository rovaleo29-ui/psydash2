<?php
/**
 * Класс для загрузки и управления модулями тестов
 * 
 * @package Core
 */

namespace Core;

use Exception;
use JsonException;

class ModuleLoader
{
    /**
     * @var string Путь к директории модулей
     */
    private $modulesPath;
    
    /**
     * @var Database Объект базы данных
     */
    private $db;
    
    /**
     * @var Template Объект шаблонизатора
     */
    private $template;
    
    /**
     * @var array Загруженные модули
     */
    private $loadedModules = [];
    
    /**
     * @var array Информация о модулях из БД
     */
    private $modulesInfo = [];
    
    /**
     * Конструктор класса
     * 
     * @param string $modulesPath Путь к директории модулей
     * @param Database $db Объект базы данных
     * @param Template $template Объект шаблонизатора
     */
    public function __construct(string $modulesPath, Database $db, Template $template)
    {
        $this->modulesPath = rtrim($modulesPath, '/') . '/';
        $this->db = $db;
        $this->template = $template;
        
        if (!is_dir($this->modulesPath)) {
            throw new Exception("Директория модулей не найдена: {$modulesPath}");
        }
        
        // Загрузка информации о модулях из БД
        $this->loadModulesInfo();
    }
    
    /**
     * Загрузка информации о модулях из БД
     */
    private function loadModulesInfo(): void
    {
        try {
            $sql = "SELECT * FROM test_modules WHERE is_active = 1 ORDER BY name";
            $this->modulesInfo = $this->db->fetchAll($sql);
        } catch (Exception $e) {
            // Таблица может не существовать при первой установке
            $this->modulesInfo = [];
        }
    }
    
    /**
     * Получение модуля по ключу
     * 
     * @param string $moduleKey Ключ модуля (имя папки)
     * @return object|null
     */
    public function getModule(string $moduleKey): ?object
    {
        // Проверка кэша
        if (isset($this->loadedModules[$moduleKey])) {
            return $this->loadedModules[$moduleKey];
        }
        
        // Проверка существования модуля
        $moduleDir = $this->modulesPath . $moduleKey;
        if (!is_dir($moduleDir)) {
            return null;
        }
        
        // Проверка активности модуля
        $moduleInfo = $this->getModuleInfo($moduleKey);
        if (!$moduleInfo || !$moduleInfo['is_active']) {
            return null;
        }
        
        // Загрузка конфигурации модуля
        $configFile = $moduleDir . '/module.json';
        if (!file_exists($configFile)) {
            throw new Exception("Файл конфигурации модуля не найден: {$configFile}");
        }
        
        try {
            $configJson = file_get_contents($configFile);
            $config = json_decode($configJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new Exception("Ошибка парсинга конфигурации модуля {$moduleKey}: " . $e->getMessage());
        }
        
        // Проверка обязательных полей конфигурации
        $requiredFields = ['module_key', 'name', 'version'];
        foreach ($requiredFields as $field) {
            if (!isset($config[$field])) {
                throw new Exception("В конфигурации модуля {$moduleKey} отсутствует обязательное поле: {$field}");
            }
        }
        
        // Проверка совпадения ключа модуля
        if ($config['module_key'] !== $moduleKey) {
            throw new Exception("Ключ модуля в конфигурации не соответствует имени папки: {$config['module_key']} !== {$moduleKey}");
        }
        
        // Загрузка основного класса модуля
        $classFile = $moduleDir . '/Test.php';
        if (!file_exists($classFile)) {
            throw new Exception("Основной класс модуля не найден: {$classFile}");
        }
        
        require_once $classFile;
        
        // Определение имени класса
        $namespace = 'Modules\\' . $this->camelCase($moduleKey);
        $className = $namespace . '\\Test';
        
        if (!class_exists($className)) {
            throw new Exception("Класс модуля не найден: {$className}");
        }
        
        // Создание экземпляра модуля
        try {
            $module = new $className($this->db, $this->template, $config);
            $this->loadedModules[$moduleKey] = $module;
            
            return $module;
            
        } catch (Exception $e) {
            throw new Exception("Ошибка при создании экземпляра модуля {$moduleKey}: " . $e->getMessage());
        }
    }
    
    /**
     * Получение списка активных модулей
     * 
     * @return array
     */
    public function getActiveModules(): array
    {
        $activeModules = [];
        
        foreach ($this->modulesInfo as $moduleInfo) {
            if ($moduleInfo['is_active']) {
                $activeModules[] = $moduleInfo;
            }
        }
        
        return $activeModules;
    }
    
    /**
     * Получение информации о модуле из БД
     * 
     * @param string $moduleKey Ключ модуля
     * @return array|null
     */
    public function getModuleInfo(string $moduleKey): ?array
    {
        foreach ($this->modulesInfo as $module) {
            if ($module['module_key'] === $moduleKey) {
                return $module;
            }
        }
        
        return null;
    }
    
    /**
     * Сканирование директории модулей и регистрация новых модулей
     * 
     * @return array Список зарегистрированных модулей
     */
    public function scanAndRegisterModules(): array
    {
        $registered = [];
        
        // Получение списка директорий в папке модулей
        $directories = glob($this->modulesPath . '*', GLOB_ONLYDIR);
        
        foreach ($directories as $directory) {
            $moduleKey = basename($directory);
            $configFile = $directory . '/module.json';
            
            if (!file_exists($configFile)) {
                continue;
            }
            
            try {
                $configJson = file_get_contents($configFile);
                $config = json_decode($configJson, true, 512, JSON_THROW_ON_ERROR);
                
                // Проверка обязательных полей
                if (!isset($config['module_key'], $config['name'], $config['version'])) {
                    error_log("Модуль {$moduleKey} имеет неполную конфигурацию");
                    continue;
                }
                
                // Регистрация модуля в БД
                if ($this->registerModule($config)) {
                    $registered[] = $config['module_key'];
                }
                
            } catch (JsonException $e) {
                error_log("Ошибка парсинга конфигурации модуля {$moduleKey}: " . $e->getMessage());
                continue;
            } catch (Exception $e) {
                error_log("Ошибка регистрации модуля {$moduleKey}: " . $e->getMessage());
                continue;
            }
        }
        
        // Обновление информации о модулях
        $this->loadModulesInfo();
        
        return $registered;
    }
    
    /**
     * Регистрация модуля в БД
     * 
     * @param array $config Конфигурация модуля
     * @return bool
     */
    private function registerModule(array $config): bool
    {
        $moduleKey = $config['module_key'];
        
        // Проверка существования модуля в БД
        $existing = $this->db->fetch(
            "SELECT id FROM test_modules WHERE module_key = :module_key",
            [':module_key' => $moduleKey]
        );
        
        $moduleData = [
            'module_key' => $moduleKey,
            'name' => $config['name'],
            'description' => $config['description'] ?? '',
            'version' => $config['version'],
            'author' => $config['author'] ?? '',
            'category' => $config['category'] ?? 'общий',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        if ($existing) {
            // Обновление существующего модуля
            $result = $this->db->update(
                'test_modules',
                $moduleData,
                ['id' => $existing['id']]
            );
        } else {
            // Вставка нового модуля
            $result = $this->db->insert('test_modules', $moduleData);
        }
        
        return $result > 0;
    }
    
    /**
     * Установка модуля
     * 
     * @param string $moduleKey Ключ модуля
     * @return bool
     */
    public function installModule(string $moduleKey): bool
    {
        // Получение модуля
        $module = $this->getModule($moduleKey);
        if (!$module) {
            throw new Exception("Модуль {$moduleKey} не найден");
        }
        
        // Получение конфигурации модуля
        $config = $module->getConfig();
        
        // Создание таблицы для модуля, если указан SQL файл
        if (isset($config['database']['create_sql'])) {
            $sqlFile = $this->modulesPath . $moduleKey . '/' . $config['database']['create_sql'];
            
            if (file_exists($sqlFile)) {
                try {
                    $this->db->executeFile($sqlFile);
                } catch (Exception $e) {
                    throw new Exception("Ошибка при создании таблицы модуля {$moduleKey}: " . $e->getMessage());
                }
            }
        }
        
        // Активация модуля в БД
        $result = $this->db->update(
            'test_modules',
            ['is_active' => 1],
            ['module_key' => $moduleKey]
        );
        
        if ($result > 0) {
            $this->loadModulesInfo();
            return true;
        }
        
        return false;
    }
    
    /**
     * Деактивация модуля
     * 
     * @param string $moduleKey Ключ модуля
     * @return bool
     */
    public function deactivateModule(string $moduleKey): bool
    {
        $result = $this->db->update(
            'test_modules',
            ['is_active' => 0],
            ['module_key' => $moduleKey]
        );
        
        if ($result > 0) {
            // Удаление модуля из кэша
            unset($this->loadedModules[$moduleKey]);
            $this->loadModulesInfo();
            return true;
        }
        
        return false;
    }
    
    /**
     * Получение списка всех доступных модулей (даже неактивных)
     * 
     * @return array
     */
    public function getAllModules(): array
    {
        $modules = [];
        $directories = glob($this->modulesPath . '*', GLOB_ONLYDIR);
        
        foreach ($directories as $directory) {
            $moduleKey = basename($directory);
            $configFile = $directory . '/module.json';
            
            if (!file_exists($configFile)) {
                continue;
            }
            
            try {
                $configJson = file_get_contents($configFile);
                $config = json_decode($configJson, true, 512, JSON_THROW_ON_ERROR);
                
                // Добавление информации о статусе
                $moduleInfo = $this->getModuleInfo($moduleKey);
                $config['is_installed'] = $moduleInfo !== null;
                $config['is_active'] = $moduleInfo['is_active'] ?? false;
                
                $modules[] = $config;
                
            } catch (JsonException $e) {
                error_log("Ошибка чтения конфигурации модуля {$moduleKey}: " . $e->getMessage());
                continue;
            }
        }
        
        return $modules;
    }
    
    /**
     * Проверка зависимостей модуля
     * 
     * @param array $config Конфигурация модуля
     * @return bool
     */
    public function checkDependencies(array $config): bool
    {
        if (!isset($config['dependencies'])) {
            return true;
        }
        
        $dependencies = $config['dependencies'];
        
        // Проверка версии PHP
        if (isset($dependencies['php'])) {
            if (version_compare(PHP_VERSION, $dependencies['php'], '<')) {
                throw new Exception(
                    "Модуль требует PHP версии {$dependencies['php']} или выше. " .
                    "Текущая версия: " . PHP_VERSION
                );
            }
        }
        
        // Проверка версии ядра
        if (isset($dependencies['core'])) {
            // Здесь можно добавить проверку версии ядра системы
            // Пока просто возвращаем true
        }
        
        // Проверка расширений PHP
        if (isset($dependencies['extensions'])) {
            foreach ($dependencies['extensions'] as $extension) {
                if (!extension_loaded($extension)) {
                    throw new Exception("Требуется расширение PHP: {$extension}");
                }
            }
        }
        
        return true;
    }
    
    /**
     * Получение конфигурации модуля из файла
     * 
     * @param string $moduleKey Ключ модуля
     * @return array|null
     */
    public function getModuleConfig(string $moduleKey): ?array
    {
        $configFile = $this->modulesPath . $moduleKey . '/module.json';
        
        if (!file_exists($configFile)) {
            return null;
        }
        
        try {
            $configJson = file_get_contents($configFile);
            return json_decode($configJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            error_log("Ошибка чтения конфигурации модуля {$moduleKey}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Преобразование строки в CamelCase
     * 
     * @param string $string Исходная строка
     * @return string
     */
    private function camelCase(string $string): string
    {
        $string = str_replace(['-', '_'], ' ', $string);
        $string = ucwords($string);
        $string = str_replace(' ', '', $string);
        
        return $string;
    }
    
    /**
     * Выполнение метода модуля
     * 
     * @param string $moduleKey Ключ модуля
     * @param string $method Имя метода
     * @param array $params Параметры метода
     * @return mixed
     */
    public function callModuleMethod(string $moduleKey, string $method, array $params = [])
    {
        $module = $this->getModule($moduleKey);
        
        if (!$module) {
            throw new Exception("Модуль {$moduleKey} не найден");
        }
        
        if (!method_exists($module, $method)) {
            throw new Exception("Метод {$method} не существует в модуле {$moduleKey}");
        }
        
        return call_user_func_array([$module, $method], $params);
    }
    
    /**
     * Получение пути к директории модуля
     * 
     * @param string $moduleKey Ключ модуля
     * @return string
     */
    public function getModulePath(string $moduleKey): string
    {
        return $this->modulesPath . $moduleKey . '/';
    }
    
    /**
     * Получение пути к views модуля
     * 
     * @param string $moduleKey Ключ модуля
     * @return string
     */
    public function getModuleViewsPath(string $moduleKey): string
    {
        return $this->getModulePath($moduleKey) . 'views/';
    }
}