<?php
/**
 * Контроллер для работы с тестами
 * 
 * @package App\Controllers
 */

namespace App\Controllers;

use Core\Core;
use Core\Router;
use Exception;

class TestsController
{
    /**
     * @var Core Экземпляр ядра системы
     */
    private $core;
    
    /**
     * @var Router Экземпляр маршрутизатора
     */
    private $router;
    
    /**
     * Конструктор контроллера
     * 
     * @param Core $core Экземпляр ядра системы
     */
    public function __construct(Core $core)
    {
        $this->core = $core;
        $this->router = new Router();
        
        // Проверка аутентификации для всех методов
        $this->requireAuthentication();
    }
    
    /**
     * Проверка аутентификации пользователя
     */
    private function requireAuthentication(): void
    {
        if (!$this->core->isAuthenticated()) {
            $this->router->redirect('/login');
            exit;
        }
    }
    
    /**
     * Список доступных тестов (модулей)
     */
    public function index(): void
    {
        try {
            $psychologist = $this->core->getCurrentPsychologist();
            
            // Получение активных модулей
            $activeModules = $this->core->getActiveModules();
            
            // Группировка модулей по категориям
            $modulesByCategory = $this->groupModulesByCategory($activeModules);
            
            // Получение статистики по модулям
            $modulesStats = $this->getModulesStats($psychologist['psychologist_id']);
            
            // Получение категорий из конфигурации
            $categoriesConfig = $this->core->getConfig('modules')['categories'] ?? [];
            
            $data = [
                'modules_by_category' => $modulesByCategory,
                'modules_stats' => $modulesStats,
                'categories_config' => $categoriesConfig,
                'psychologist' => $psychologist,
                'total_modules' => count($activeModules)
            ];
            
            $content = $this->core->getTemplate()->render('tests/list.php', $data);
            echo $this->core->renderPage($content, 'Доступные тесты');
            
        } catch (Exception $e) {
            error_log("Ошибка при загрузке списка тестов: " . $e->getMessage());
            $this->showError('Произошла ошибка при загрузке списка тестов');
        }
    }
    
    /**
     * Группировка модулей по категориям
     * 
     * @param array $modules Массив модулей
     * @return array
     */
    private function groupModulesByCategory(array $modules): array
    {
        $grouped = [];
        
        foreach ($modules as $module) {
            $category = $module['category'] ?? 'общий';
            
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            
            $grouped[$category][] = $module;
        }
        
        // Сортировка категорий в заданном порядке
        $categoriesOrder = ['эмоциональная сфера', 'познавательная сфера', 'личностные особенности', 
                           'межличностные отношения', 'профориентация', 'общий'];
        
        $sorted = [];
        foreach ($categoriesOrder as $category) {
            if (isset($grouped[$category])) {
                $sorted[$category] = $grouped[$category];
            }
        }
        
        // Добавление остальных категорий
        foreach ($grouped as $category => $modules) {
            if (!isset($sorted[$category])) {
                $sorted[$category] = $modules;
            }
        }
        
        return $sorted;
    }
    
    /**
     * Получение статистики по модулям
     * 
     * @param int $psychologistId ID психолога
     * @return array
     */
    private function getModulesStats(int $psychologistId): array
    {
        $db = $this->core->getDB();
        $stats = [];
        $activeModules = $this->core->getActiveModules();
        
        foreach ($activeModules as $module) {
            $tableName = 'test_' . $module['module_key'] . '_results';
            
            if (!$db->tableExists($tableName)) {
                $stats[$module['module_key']] = [
                    'total' => 0,
                    'last_month' => 0,
                    'last_week' => 0,
                    'today' => 0
                ];
                continue;
            }
            
            // Общее количество тестов
            $total = $db->fetch(
                "SELECT COUNT(*) as count FROM {$tableName} WHERE psychologist_id = :psychologist_id",
                [':psychologist_id' => $psychologistId]
            )['count'] ?? 0;
            
            // Тесты за последний месяц
            $lastMonth = $db->fetch(
                "SELECT COUNT(*) as count FROM {$tableName} 
                 WHERE psychologist_id = :psychologist_id 
                 AND test_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
                [':psychologist_id' => $psychologistId]
            )['count'] ?? 0;
            
            // Тесты за последнюю неделю
            $lastWeek = $db->fetch(
                "SELECT COUNT(*) as count FROM {$tableName} 
                 WHERE psychologist_id = :psychologist_id 
                 AND test_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
                [':psychologist_id' => $psychologistId]
            )['count'] ?? 0;
            
            // Тесты за сегодня
            $today = $db->fetch(
                "SELECT COUNT(*) as count FROM {$tableName} 
                 WHERE psychologist_id = :psychologist_id 
                 AND test_date = CURDATE()",
                [':psychologist_id' => $psychologistId]
            )['count'] ?? 0;
            
            $stats[$module['module_key']] = [
                'total' => $total,
                'last_month' => $lastMonth,
                'last_week' => $lastWeek,
                'today' => $today
            ];
        }
        
        return $stats;
    }
    
    /**
     * Просмотр информации о тесте (модуле)
     * 
     * @param string $moduleKey Ключ модуля
     */
    public function showModuleInfo(string $moduleKey): void
    {
        try {
            $psychologist = $this->core->getCurrentPsychologist();
            
            // Получение модуля
            $module = $this->core->getModule($moduleKey);
            
            if (!$module) {
                $this->showNotFound('Тест не найден');
                return;
            }
            
            // Получение конфигурации модуля
            $moduleConfig = $module->getConfig() ?? [];
            
            // Получение статистики по модулю
            $moduleStats = $this->getDetailedModuleStats($moduleKey, $psychologist['psychologist_id']);
            
            // Получение последних результатов
            $recentResults = $this->getRecentModuleResults($moduleKey, $psychologist['psychologist_id'], 10);
            
            // Получение детей без результатов этого теста
            $childrenWithoutTest = $this->getChildrenWithoutTest($moduleKey, $psychologist['psychologist_id']);
            
            $data = [
                'module' => $module,
                'module_config' => $moduleConfig,
                'module_stats' => $moduleStats,
                'recent_results' => $recentResults,
                'children_without_test' => $childrenWithoutTest,
                'psychologist' => $psychologist
            ];
            
            $content = $this->core->getTemplate()->render('tests/module-info.php', $data);
            echo $this->core->renderPage($content, 'Информация о тесте: ' . ($moduleConfig['name'] ?? $moduleKey));
            
        } catch (Exception $e) {
            error_log("Ошибка при загрузке информации о тесте: " . $e->getMessage());
            $this->showError('Произошла ошибка при загрузке информации о тесте');
        }
    }
    
    /**
     * Получение детальной статистики по модулю
     * 
     * @param string $moduleKey Ключ модуля
     * @param int $psychologistId ID психолога
     * @return array
     */
    private function getDetailedModuleStats(string $moduleKey, int $psychologistId): array
    {
        $db = $this->core->getDB();
        $tableName = 'test_' . $moduleKey . '_results';
        
        if (!$db->tableExists($tableName)) {
            return [
                'total' => 0,
                'by_class' => [],
                'by_month' => [],
                'avg_per_child' => 0,
                'unique_children' => 0
            ];
        }
        
        // Общее количество тестов
        $total = $db->fetch(
            "SELECT COUNT(*) as count FROM {$tableName} WHERE psychologist_id = :psychologist_id",
            [':psychologist_id' => $psychologistId]
        )['count'] ?? 0;
        
        // Количество уникальных детей
        $uniqueChildren = $db->fetch(
            "SELECT COUNT(DISTINCT child_id) as count FROM {$tableName} WHERE psychologist_id = :psychologist_id",
            [':psychologist_id' => $psychologistId]
        )['count'] ?? 0;
        
        // Среднее количество тестов на ребенка
        $avgPerChild = $uniqueChildren > 0 ? round($total / $uniqueChildren, 1) : 0;
        
        // Распределение по классам
        $byClass = $db->fetchAll(
            "SELECT c.class, COUNT(*) as count 
             FROM {$tableName} t
             JOIN children c ON t.child_id = c.id
             WHERE t.psychologist_id = :psychologist_id 
             GROUP BY c.class 
             ORDER BY c.class",
            [':psychologist_id' => $psychologistId]
        );
        
        // Распределение по месяцам (последние 12 месяцев)
        $byMonth = $db->fetchAll(
            "SELECT DATE_FORMAT(test_date, '%Y-%m') as month, COUNT(*) as count 
             FROM {$tableName} 
             WHERE psychologist_id = :psychologist_id 
             AND test_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
             GROUP BY DATE_FORMAT(test_date, '%Y-%m') 
             ORDER BY month DESC",
            [':psychologist_id' => $psychologistId]
        );
        
        // Самые частые результаты (если есть поле для результата)
        $commonResults = [];
        try {
            // Пробуем получить структуру таблицы
            $columns = $db->fetchAll("SHOW COLUMNS FROM {$tableName}");
            
            // Ищем поле с результатом (score, result, total и т.д.)
            $resultFields = ['total_score', 'score', 'result', 'total', 'final_score'];
            $resultField = null;
            
            foreach ($columns as $column) {
                if (in_array($column['Field'], $resultFields)) {
                    $resultField = $column['Field'];
                    break;
                }
            }
            
            if ($resultField) {
                $commonResults = $db->fetchAll(
                    "SELECT {$resultField} as value, COUNT(*) as count 
                     FROM {$tableName} 
                     WHERE psychologist_id = :psychologist_id 
                     AND {$resultField} IS NOT NULL 
                     GROUP BY {$resultField} 
                     ORDER BY count DESC 
                     LIMIT 10",
                    [':psychologist_id' => $psychologistId]
                );
            }
        } catch (Exception $e) {
            // Игнорируем ошибки при получении общих результатов
        }
        
        return [
            'total' => $total,
            'by_class' => $byClass,
            'by_month' => $byMonth,
            'avg_per_child' => $avgPerChild,
            'unique_children' => $uniqueChildren,
            'common_results' => $commonResults
        ];
    }
    
    /**
     * Получение последних результатов модуля
     * 
     * @param string $moduleKey Ключ модуля
     * @param int $psychologistId ID психолога
     * @param int $limit Количество записей
     * @return array
     */
    private function getRecentModuleResults(string $moduleKey, int $psychologistId, int $limit = 10): array
    {
        $db = $this->core->getDB();
        $tableName = 'test_' . $moduleKey . '_results';
        
        if (!$db->tableExists($tableName)) {
            return [];
        }
        
        return $db->fetchAll(
            "SELECT t.*, c.first_name, c.last_name, c.class 
             FROM {$tableName} t
             LEFT JOIN children c ON t.child_id = c.id
             WHERE t.psychologist_id = :psychologist_id 
             ORDER BY t.created_at DESC 
             LIMIT :limit",
            [
                ':psychologist_id' => $psychologistId,
                ':limit' => $limit
            ]
        );
    }
    
    /**
     * Получение детей без результатов данного теста
     * 
     * @param string $moduleKey Ключ модуля
     * @param int $psychologistId ID психолога
     * @return array
     */
    private function getChildrenWithoutTest(string $moduleKey, int $psychologistId): array
    {
        $db = $this->core->getDB();
        $tableName = 'test_' . $moduleKey . '_results';
        
        if (!$db->tableExists($tableName)) {
            // Если таблицы нет, возвращаем всех детей
            return $db->fetchAll(
                "SELECT id, first_name, last_name, class 
                 FROM children 
                 WHERE psychologist_id = :psychologist_id 
                 ORDER BY class, last_name, first_name",
                [':psychologist_id' => $psychologistId]
            );
        }
        
        return $db->fetchAll(
            "SELECT c.id, c.first_name, c.last_name, c.class 
             FROM children c
             LEFT JOIN {$tableName} t ON c.id = t.child_id AND t.psychologist_id = c.psychologist_id
             WHERE c.psychologist_id = :psychologist_id 
             AND t.id IS NULL
             ORDER BY c.class, c.last_name, c.first_name",
            [':psychologist_id' => $psychologistId]
        );
    }
    
    /**
     * Управление модулями (админ-панель)
     */
    public function manage(): void
    {
        try {
            $psychologist = $this->core->getCurrentPsychologist();
            
            // Проверка прав доступа (только для администраторов)
            if (!$this->isAdmin($psychologist)) {
                $this->showError('Доступ запрещен');
                return;
            }
            
            // Получение всех модулей (включая неактивные)
            $moduleLoader = $this->core->getModuleLoader();
            $allModules = $moduleLoader->getAllModules();
            
            // Получение установленных модулей из БД
            $db = $this->core->getDB();
            $installedModules = $db->fetchAll("SELECT * FROM test_modules ORDER BY name");
            
            // Создание полного списка модулей
            $modules = [];
            
            // Добавление установленных модулей
            foreach ($installedModules as $installed) {
                $modules[$installed['module_key']] = [
                    'db_info' => $installed,
                    'file_info' => null,
                    'status' => 'installed'
                ];
            }
            
            // Добавление модулей из файлов
            foreach ($allModules as $fileModule) {
                $moduleKey = $fileModule['module_key'];
                
                if (!isset($modules[$moduleKey])) {
                    $modules[$moduleKey] = [
                        'db_info' => null,
                        'file_info' => $fileModule,
                        'status' => 'not_installed'
                    ];
                } else {
                    $modules[$moduleKey]['file_info'] = $fileModule;
                    $modules[$moduleKey]['status'] = 'installed';
                }
            }
            
            // Получение системной информации
            $systemInfo = $this->getSystemInfo();
            
            $data = [
                'modules' => $modules,
                'system_info' => $systemInfo,
                'psychologist' => $psychologist
            ];
            
            $content = $this->core->getTemplate()->render('tests/manage.php', $data);
            echo $this->core->renderPage($content, 'Управление модулями тестов');
            
        } catch (Exception $e) {
            error_log("Ошибка при загрузке управления модулями: " . $e->getMessage());
            $this->showError('Произошла ошибка при загрузке управления модулями');
        }
    }
    
    /**
     * Проверка прав администратора
     * 
     * @param array $psychologist Данные психолога
     * @return bool
     */
    private function isAdmin(array $psychologist): bool
    {
        // Простая проверка - если psychologist_id равен 1 или 999
        // В реальной системе здесь должна быть более сложная логика
        $adminIds = [1, 999, 1000];
        return in_array($psychologist['psychologist_id'], $adminIds);
    }
    
    /**
     * Получение системной информации
     * 
     * @return array
     */
    private function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Неизвестно',
            'mysql_version' => $this->getMysqlVersion(),
            'modules_count' => count($this->core->getActiveModules()),
            'disk_space' => $this->getDiskSpaceInfo(),
            'memory_usage' => memory_get_usage(true),
            'memory_limit' => ini_get('memory_limit')
        ];
    }
    
    /**
     * Получение версии MySQL
     * 
     * @return string
     */
    private function getMysqlVersion(): string
    {
        try {
            $db = $this->core->getDB();
            $result = $db->fetch("SELECT VERSION() as version");
            return $result['version'] ?? 'Неизвестно';
        } catch (Exception $e) {
            return 'Неизвестно';
        }
    }
    
    /**
     * Получение информации о дисковом пространстве
     * 
     * @return array
     */
    private function getDiskSpaceInfo(): array
    {
        $total = disk_total_space(ROOT_PATH);
        $free = disk_free_space(ROOT_PATH);
        $used = $total - $free;
        
        return [
            'total' => $this->formatBytes($total),
            'used' => $this->formatBytes($used),
            'free' => $this->formatBytes($free),
            'percent_used' => $total > 0 ? round(($used / $total) * 100, 1) : 0
        ];
    }
    
    /**
     * Форматирование байтов в читаемый вид
     * 
     * @param int $bytes Байты
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Установка модуля
     * 
     * @param string $moduleKey Ключ модуля
     */
    public function installModule(string $moduleKey): void
    {
        try {
            $psychologist = $this->core->getCurrentPsychologist();
            
            // Проверка прав
            if (!$this->isAdmin($psychologist)) {
                $this->showError('Доступ запрещен');
                return;
            }
            
            // Проверка CSRF токена
            if (!$this->validateCsrfToken()) {
                $this->showError('Недействительный токен безопасности');
                return;
            }
            
            // Установка модуля
            $result = $this->core->installModule($moduleKey);
            
            if ($result) {
                // Логирование
                $this->logModuleAction($moduleKey, 'install', 'Модуль успешно установлен');
                
                // Перенаправление с сообщением об успехе
                $this->router->redirect('/tests/manage?success=1&action=install&module=' . urlencode($moduleKey));
            } else {
                throw new Exception('Не удалось установить модуль');
            }
            
        } catch (Exception $e) {
            error_log("Ошибка при установке модуля: " . $e->getMessage());
            
            // Логирование ошибки
            $this->logModuleAction($moduleKey, 'install_error', 'Ошибка установки: ' . $e->getMessage());
            
            // Перенаправление с сообщением об ошибке
            $this->router->redirect('/tests/manage?error=1&message=' . urlencode($e->getMessage()));
        }
    }
    
    /**
     * Деактивация модуля
     * 
     * @param string $moduleKey Ключ модуля
     */
    public function deactivateModule(string $moduleKey): void
    {
        try {
            $psychologist = $this->core->getCurrentPsychologist();
            
            // Проверка прав
            if (!$this->isAdmin($psychologist)) {
                $this->showError('Доступ запрещен');
                return;
            }
            
            // Проверка CSRF токена
            if (!$this->validateCsrfToken()) {
                $this->showError('Недействительный токен безопасности');
                return;
            }
            
            // Деактивация модуля
            $moduleLoader = $this->core->getModuleLoader();
            $result = $moduleLoader->deactivateModule($moduleKey);
            
            if ($result) {
                // Логирование
                $this->logModuleAction($moduleKey, 'deactivate', 'Модуль деактивирован');
                
                $this->router->redirect('/tests/manage?success=1&action=deactivate&module=' . urlencode($moduleKey));
            } else {
                throw new Exception('Не удалось деактивировать модуль');
            }
            
        } catch (Exception $e) {
            error_log("Ошибка при деактивации модуля: " . $e->getMessage());
            $this->logModuleAction($moduleKey, 'deactivate_error', 'Ошибка деактивации: ' . $e->getMessage());
            $this->router->redirect('/tests/manage?error=1&message=' . urlencode($e->getMessage()));
        }
    }
    
    /**
     * Активация модуля
     * 
     * @param string $moduleKey Ключ модуля
     */
    public function activateModule(string $moduleKey): void
    {
        try {
            $psychologist = $this->core->getCurrentPsychologist();
            
            // Проверка прав
            if (!$this->isAdmin($psychologist)) {
                $this->showError('Доступ запрещен');
                return;
            }
            
            // Проверка CSRF токена
            if (!$this->validateCsrfToken()) {
                $this->showError('Недействительный токен безопасности');
                return;
            }
            
            // Активация модуля в БД
            $db = $this->core->getDB();
            $result = $db->update(
                'test_modules',
                ['is_active' => 1],
                ['module_key' => $moduleKey]
            );
            
            if ($result > 0) {
                // Обновление информации о модулях
                $moduleLoader = $this->core->getModuleLoader();
                $moduleLoader->loadModulesInfo();
                
                // Логирование
                $this->logModuleAction($moduleKey, 'activate', 'Модуль активирован');
                
                $this->router->redirect('/tests/manage?success=1&action=activate&module=' . urlencode($moduleKey));
            } else {
                throw new Exception('Не удалось активировать модуль');
            }
            
        } catch (Exception $e) {
            error_log("Ошибка при активации модуля: " . $e->getMessage());
            $this->logModuleAction($moduleKey, 'activate_error', 'Ошибка активации: ' . $e->getMessage());
            $this->router->redirect('/tests/manage?error=1&message=' . urlencode($e->getMessage()));
        }
    }
    
    /**
     * Удаление модуля
     * 
     * @param string $moduleKey Ключ модуля
     */
    public function uninstallModule(string $moduleKey): void
    {
        try {
            $psychologist = $this->core->getCurrentPsychologist();
            
            // Проверка прав
            if (!$this->isAdmin($psychologist)) {
                $this->showError('Доступ запрещен');
                return;
            }
            
            // Проверка CSRF токена
            if (!$this->validateCsrfToken()) {
                $this->showError('Недействительный токен безопасности');
                return;
            }
            
            // Проверка подтверждения
            if (empty($_POST['confirm'])) {
                $this->showUninstallConfirmation($moduleKey);
                return;
            }
            
            $db = $this->core->getDB();
            
            // Начало транзакции
            $db->beginTransaction();
            
            try {
                // 1. Удаление таблицы результатов (если выбрано)
                $deleteData = !empty($_POST['delete_data']);
                
                if ($deleteData) {
                    $tableName = 'test_' . $moduleKey . '_results';
                    if ($db->tableExists($tableName)) {
                        $db->query("DROP TABLE IF EXISTS {$tableName}");
                    }
                }
                
                // 2. Удаление записи о модуле из БД
                $db->delete('test_modules', ['module_key' => $moduleKey]);
                
                // 3. Создание резервной копии файлов модуля (если выбрано)
                $backupFiles = !empty($_POST['backup_files']);
                
                if ($backupFiles) {
                    $this->backupModuleFiles($moduleKey);
                }
                
                // 4. Удаление файлов модуля (если выбрано)
                $deleteFiles = !empty($_POST['delete_files']);
                
                if ($deleteFiles) {
                    $this->deleteModuleFiles($moduleKey);
                }
                
                $db->commit();
                
                // Логирование
                $this->logModuleAction($moduleKey, 'uninstall', 
                    'Модуль удален. Удаление данных: ' . ($deleteData ? 'да' : 'нет') . 
                    ', Резервная копия: ' . ($backupFiles ? 'да' : 'нет') . 
                    ', Удаление файлов: ' . ($deleteFiles ? 'да' : 'нет'));
                
                $this->router->redirect('/tests/manage?success=1&action=uninstall&module=' . urlencode($moduleKey));
                
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            error_log("Ошибка при удалении модуля: " . $e->getMessage());
            $this->logModuleAction($moduleKey, 'uninstall_error', 'Ошибка удаления: ' . $e->getMessage());
            $this->router->redirect('/tests/manage?error=1&message=' . urlencode($e->getMessage()));
        }
    }
    
    /**
     * Отображение подтверждения удаления модуля
     * 
     * @param string $moduleKey Ключ модуля
     */
    private function showUninstallConfirmation(string $moduleKey): void
    {
        try {
            $psychologist = $this->core->getCurrentPsychologist();
            
            // Получение информации о модуле
            $moduleLoader = $this->core->getModuleLoader();
            $moduleInfo = $moduleLoader->getModuleInfo($moduleKey);
            $moduleConfig = $moduleLoader->getModuleConfig($moduleKey);
            
            if (!$moduleInfo && !$moduleConfig) {
                $this->showNotFound('Модуль не найден');
                return;
            }
            
            // Получение информации о данных модуля
            $db = $this->core->getDB();
            $tableName = 'test_' . $moduleKey . '_results';
            $hasData = $db->tableExists($tableName);
            $dataCount = 0;
            
            if ($hasData) {
                $result = $db->fetch("SELECT COUNT(*) as count FROM {$tableName}");
                $dataCount = $result['count'] ?? 0;
            }
            
            // Проверка размера файлов модуля
            $modulePath = $moduleLoader->getModulePath($moduleKey);
            $fileSize = 0;
            
            if (is_dir($modulePath)) {
                $fileSize = $this->getDirectorySize($modulePath);
            }
            
            $data = [
                'module_key' => $moduleKey,
                'module_info' => $moduleInfo,
                'module_config' => $moduleConfig,
                'has_data' => $hasData,
                'data_count' => $dataCount,
                'file_size' => $this->formatBytes($fileSize),
                'psychologist' => $psychologist
            ];
            
            $content = $this->core->getTemplate()->render('tests/uninstall-confirm.php', $data);
            echo $this->core->renderPage($content, 'Подтверждение удаления модуля');
            
        } catch (Exception $e) {
            error_log("Ошибка при загрузке подтверждения удаления: " . $e->getMessage());
            $this->showError('Произошла ошибка');
        }
    }
    
    /**
     * Создание резервной копии файлов модуля
     * 
     * @param string $moduleKey Ключ модуля
     */
    private function backupModuleFiles(string $moduleKey): void
    {
        $moduleLoader = $this->core->getModuleLoader();
        $modulePath = $moduleLoader->getModulePath($moduleKey);
        $backupPath = ROOT_PATH . '/storage/backups/modules/' . $moduleKey . '_' . date('Y-m-d_H-i-s');
        
        if (!is_dir($modulePath)) {
            return;
        }
        
        // Создание директории для бэкапа
        if (!is_dir(dirname($backupPath))) {
            mkdir(dirname($backupPath), 0755, true);
        }
        
        // Копирование файлов
        $this->copyDirectory($modulePath, $backupPath);
    }
    
    /**
     * Удаление файлов модуля
     * 
     * @param string $moduleKey Ключ модуля
     */
    private function deleteModuleFiles(string $moduleKey): void
    {
        $moduleLoader = $this->core->getModuleLoader();
        $modulePath = $moduleLoader->getModulePath($moduleKey);
        
        if (is_dir($modulePath)) {
            $this->deleteDirectory($modulePath);
        }
    }
    
    /**
     * Копирование директории
     * 
     * @param string $source Исходная директория
     * @param string $dest Целевая директория
     */
    private function copyDirectory(string $source, string $dest): void
    {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }
        
        $dir = opendir($source);
        
        while (($file = readdir($dir)) !== false) {
            if ($file != '.' && $file != '..') {
                $sourceFile = $source . '/' . $file;
                $destFile = $dest . '/' . $file;
                
                if (is_dir($sourceFile)) {
                    $this->copyDirectory($sourceFile, $destFile);
                } else {
                    copy($sourceFile, $destFile);
                }
            }
        }
        
        closedir($dir);
    }
    
    /**
     * Удаление директории
     * 
     * @param string $dir Директория
     */
    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
    }
    
    /**
     * Получение размера директории
     * 
     * @param string $dir Директория
     * @return int Размер в байтах
     */
    private function getDirectorySize(string $dir): int
    {
        $size = 0;
        
        if (!is_dir($dir)) {
            return 0;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                $size += $this->getDirectorySize($path);
            } else {
                $size += filesize($path);
            }
        }
        
        return $size;
    }
    
    /**
     * Логирование действий с модулями
     * 
     * @param string $moduleKey Ключ модуля
     * @param string $action Действие
     * @param string $description Описание
     */
    private function logModuleAction(string $moduleKey, string $action, string $description): void
    {
        try {
            $db = $this->core->getDB();
            $psychologist = $this->core->getCurrentPsychologist();
            
            // Проверка существования таблицы логов
            if (!$db->tableExists('module_logs')) {
                $this->createModuleLogsTable();
            }
            
            $db->insert('module_logs', [
                'module_key' => $moduleKey,
                'psychologist_id' => $psychologist['psychologist_id'],
                'action' => $action,
                'description' => $description,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            error_log("Ошибка при логировании действий с модулем: " . $e->getMessage());
        }
    }
    
    /**
     * Создание таблицы логов модулей
     */
    private function createModuleLogsTable(): void
    {
        $db = $this->core->getDB();
        
        $sql = "CREATE TABLE IF NOT EXISTS module_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            module_key VARCHAR(50) NOT NULL,
            psychologist_id INT NOT NULL,
            action VARCHAR(50) NOT NULL,
            description TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_module (module_key),
            INDEX idx_psychologist (psychologist_id),
            INDEX idx_action (action),
            INDEX idx_created (created_at)
        )";
        
        $db->query($sql);
    }
    
    /**
     * Сканирование и регистрация новых модулей
     */
    public function scanModules(): void
    {
        try {
            $psychologist = $this->core->getCurrentPsychologist();
            
            // Проверка прав
            if (!$this->isAdmin($psychologist)) {
                $this->showError('Доступ запрещен');
                return;
            }
            
            // Проверка CSRF токена
            if (!$this->validateCsrfToken()) {
                $this->showError('Недействительный токен безопасности');
                return;
            }
            
            // Сканирование модулей
            $moduleLoader = $this->core->getModuleLoader();
            $registered = $moduleLoader->scanAndRegisterModules();
            
            // Логирование
            $this->logModuleAction('system', 'scan', 'Сканирование модулей. Найдено: ' . count($registered));
            
            $this->router->redirect('/tests/manage?success=1&action=scan&count=' . count($registered));
            
        } catch (Exception $e) {
            error_log("Ошибка при сканировании модулей: " . $e->getMessage());
            $this->logModuleAction('system', 'scan_error', 'Ошибка сканирования: ' . $e->getMessage());
            $this->router->redirect('/tests/manage?error=1&message=' . urlencode($e->getMessage()));
        }
    }
    
    /**
     * Экспорт данных по всем тестам
     */
    public function exportAll(): void
    {
        try {
            $psychologist = $this->core->getCurrentPsychologist();
            $psychologistId = $psychologist['psychologist_id'] ?? null;
            
            if (!$psychologistId) {
                throw new Exception('Не удалось определить ID психолога');
            }
            
            $format = $_GET['format'] ?? 'excel';
            $moduleKey = $_GET['module'] ?? null;
            
            if ($moduleKey) {
                // Экспорт конкретного модуля
                $this->exportModuleData($moduleKey, $psychologistId, $format);
            } else {
                // Экспорт всех модулей
                $this->exportAllModulesData($psychologistId, $format);
            }
            
        } catch (Exception $e) {
            error_log("Ошибка при экспорте данных тестов: " . $e->getMessage());
            $this->showError('Произошла ошибка при экспорте данных');
        }
    }
    
    /**
     * Экспорт данных конкретного модуля
     * 
     * @param string $moduleKey Ключ модуля
     * @param int $psychologistId ID психолога
     * @param string $format Формат экспорта
     */
    private function exportModuleData(string $moduleKey, int $psychologistId, string $format): void
    {
        $db = $this->core->getDB();
        $tableName = 'test_' . $moduleKey . '_results';
        
        if (!$db->tableExists($tableName)) {
            throw new Exception('Данные теста не найдены');
        }
        
        // Получение данных
        $results = $db->fetchAll(
            "SELECT t.*, c.first_name, c.last_name, c.class, c.birth_date 
             FROM {$tableName} t
             LEFT JOIN children c ON t.child_id = c.id
             WHERE t.psychologist_id = :psychologist_id 
             ORDER BY c.class, c.last_name, c.first_name, t.test_date",
            [':psychologist_id' => $psychologistId]
        );
        
        // Получение информации о модуле
        $module = $this->core->getModule($moduleKey);
        $moduleName = $module ? $module->getName() : $moduleKey;
        
        $exportData = [
            'module_name' => $moduleName,
            'module_key' => $moduleKey,
            'results' => $results,
            'psychologist' => $this->core->getCurrentPsychologist(),
            'export_date' => date('d.m.Y H:i'),
            'total_count' => count($results)
        ];
        
        switch ($format) {
            case 'excel':
                $this->generateModuleExcel($exportData);
                break;
            case 'csv':
                $this->generateModuleCsv($exportData);
                break;
            default:
                throw new Exception('Неподдерживаемый формат экспорта');
        }
    }
    
    /**
     * Экспорт данных всех модулей
     * 
     * @param int $psychologistId ID психолога
     * @param string $format Формат экспорта
     */
    private function exportAllModulesData(int $psychologistId, string $format): void
    {
        $exportData = [
            'modules' => [],
            'psychologist' => $this->core->getCurrentPsychologist(),
            'export_date' => date('d.m.Y H:i')
        ];
        
        $activeModules = $this->core->getActiveModules();
        
        foreach ($activeModules as $module) {
            $moduleData = $this->getModuleExportData($module['module_key'], $psychologistId);
            if ($moduleData) {
                $exportData['modules'][] = $moduleData;
            }
        }
        
        switch ($format) {
            case 'excel':
                $this->generateAllModulesExcel($exportData);
                break;
            default:
                throw new Exception('Неподдерживаемый формат для экспорта всех модулей');
        }
    }
    
    /**
     * Получение данных модуля для экспорта
     * 
     * @param string $moduleKey Ключ модуля
     * @param int $psychologistId ID психолога
     * @return array|null
     */
    private function getModuleExportData(string $moduleKey, int $psychologistId): ?array
    {
        $db = $this->core->getDB();
        $tableName = 'test_' . $moduleKey . '_results';
        
        if (!$db->tableExists($tableName)) {
            return null;
        }
        
        $results = $db->fetchAll(
            "SELECT t.*, c.first_name, c.last_name, c.class 
             FROM {$tableName} t
             LEFT JOIN children c ON t.child_id = c.id
             WHERE t.psychologist_id = :psychologist_id 
             ORDER BY t.test_date DESC 
             LIMIT 1000", // Ограничение на количество записей
            [':psychologist_id' => $psychologistId]
        );
        
        if (empty($results)) {
            return null;
        }
        
        $module = $this->core->getModule($moduleKey);
        
        return [
            'name' => $module ? $module->getName() : $moduleKey,
            'key' => $moduleKey,
            'results' => $results,
            'count' => count($results)
        ];
    }
    
    /**
     * Генерация Excel для модуля
     * 
     * @param array $data Данные для экспорта
     */
    private function generateModuleExcel(array $data): void
    {
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            throw new Exception('Библиотека PhpSpreadsheet не установлена');
        }
        
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Заголовок
        $sheet->setCellValue('A1', 'Экспорт данных теста: ' . $data['module_name']);
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');
        
        // Информация
        $row = 3;
        $sheet->setCellValue('A' . $row, 'Психолог:');
        $sheet->setCellValue('B' . $row, $data['psychologist']['full_name']);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        
        $row++;
        $sheet->setCellValue('A' . $row, 'Дата экспорта:');
        $sheet->setCellValue('B' . $row, $data['export_date']);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        
        $row++;
        $sheet->setCellValue('A' . $row, 'Всего записей:');
        $sheet->setCellValue('B' . $row, $data['total_count']);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        
        $row += 2;
        
        // Заголовки таблицы (динамически на основе первого результата)
        if (!empty($data['results'])) {
            $firstResult = $data['results'][0];
            $headers = ['ID', 'Фамилия', 'Имя', 'Класс', 'Дата теста'];
            
            // Добавление специфичных полей теста
            foreach ($firstResult as $key => $value) {
                if (!in_array($key, ['id', 'child_id', 'psychologist_id', 'test_date', 'created_at', 
                                     'first_name', 'last_name', 'class', 'birth_date'])) {
                    $headers[] = $this->formatFieldName($key);
                }
            }
            
            $headers[] = 'Дата создания';
            
            // Запись заголовков
            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col . $row, $header);
                $sheet->getStyle($col . $row)->getFont()->setBold(true);
                $sheet->getStyle($col . $row)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFE0E0E0');
                $col++;
            }
            
            $row++;
            
            // Данные
            foreach ($data['results'] as $result) {
                $col = 'A';
                
                $sheet->setCellValue($col++ . $row, $result['id']);
                $sheet->setCellValue($col++ . $row, $result['last_name'] ?? '');
                $sheet->setCellValue($col++ . $row, $result['first_name'] ?? '');
                $sheet->setCellValue($col++ . $row, $result['class'] ?? '');
                $sheet->setCellValue($col++ . $row, $result['test_date'] ? date('d.m.Y', strtotime($result['test_date'])) : '');
                
                // Специфичные поля
                foreach ($firstResult as $key => $value) {
                    if (!in_array($key, ['id', 'child_id', 'psychologist_id', 'test_date', 'created_at', 
                                         'first_name', 'last_name', 'class', 'birth_date'])) {
                        $sheet->setCellValue($col++ . $row, $result[$key] ?? '');
                    }
                }
                
                $sheet->setCellValue($col . $row, $result['created_at'] ? date('d.m.Y H:i', strtotime($result['created_at'])) : '');
                $row++;
            }
            
            // Автонастройка ширины
            $lastColumn = $sheet->getHighestColumn();
            for ($col = 'A'; $col <= $lastColumn; $col++) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }
        
        // Отправка файла
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        $filename = 'test_' . $data['module_key'] . '_export_' . date('Y-m-d') . '.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer->save('php://output');
    }
    
    /**
     * Форматирование имени поля
     * 
     * @param string $fieldName Имя поля
     * @return string
     */
    private function formatFieldName(string $fieldName): string
    {
        $replacements = [
            'q_' => 'Вопрос ',
            'score' => 'Балл',
            'total' => 'Всего',
            'result' => 'Результат',
            'comment' => 'Комментарий',
            'situational' => 'Ситуативная',
            'personal' => 'Личностная',
            '_' => ' '
        ];
        
        $formatted = $fieldName;
        foreach ($replacements as $search => $replace) {
            $formatted = str_replace($search, $replace, $formatted);
        }
        
        return ucfirst($formatted);
    }
    
    /**
     * Генерация CSV для модуля
     * 
     * @param array $data Данные для экспорта
     */
    private function generateModuleCsv(array $data): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment;filename="test_' . $data['module_key'] . '_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // BOM для корректного отображения кириллицы в Excel
        fwrite($output, "\xEF\xBB\xBF");
        
        if (!empty($data['results'])) {
            $firstResult = $data['results'][0];
            $headers = ['ID', 'Фамилия', 'Имя', 'Класс', 'Дата теста'];
            
            foreach ($firstResult as $key => $value) {
                if (!in_array($key, ['id', 'child_id', 'psychologist_id', 'test_date', 'created_at', 
                                     'first_name', 'last_name', 'class', 'birth_date'])) {
                    $headers[] = $this->formatFieldName($key);
                }
            }
            
            $headers[] = 'Дата создания';
            
            fputcsv($output, $headers, ';');
            
            foreach ($data['results'] as $result) {
                $row = [
                    $result['id'],
                    $result['last_name'] ?? '',
                    $result['first_name'] ?? '',
                    $result['class'] ?? '',
                    $result['test_date'] ? date('d.m.Y', strtotime($result['test_date'])) : ''
                ];
                
                foreach ($firstResult as $key => $value) {
                    if (!in_array($key, ['id', 'child_id', 'psychologist_id', 'test_date', 'created_at', 
                                         'first_name', 'last_name', 'class', 'birth_date'])) {
                        $row[] = $result[$key] ?? '';
                    }
                }
                
                $row[] = $result['created_at'] ? date('d.m.Y H:i', strtotime($result['created_at'])) : '';
                
                fputcsv($output, $row, ';');
            }
        }
        
        fclose($output);
    }
    
    /**
     * Генерация Excel для всех модулей
     * 
     * @param array $data Данные для экспорта
     */
    private function generateAllModulesExcel(array $data): void
    {
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            throw new Exception('Библиотека PhpSpreadsheet не установлена');
        }
        
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        
        // Удаление листа по умолчанию
        $spreadsheet->removeSheetByIndex(0);
        
        // Создание листов для каждого модуля
        foreach ($data['modules'] as $moduleData) {
            $worksheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, substr($moduleData['name'], 0, 31));
            $spreadsheet->addSheet($worksheet);
            
            $sheet = $spreadsheet->getSheetByName($worksheet->getTitle());
            $sheet->setTitle(substr($moduleData['name'], 0, 31));
            
            // Заголовок
            $sheet->setCellValue('A1', 'Тест: ' . $moduleData['name']);
            $sheet->mergeCells('A1:G1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            
            $row = 3;
            
            if (!empty($moduleData['results'])) {
                $firstResult = $moduleData['results'][0];
                $headers = ['ID записи', 'Фамилия', 'Имя', 'Класс', 'Дата теста'];
                
                foreach ($firstResult as $key => $value) {
                    if (!in_array($key, ['id', 'child_id', 'psychologist_id', 'test_date', 'created_at', 
                                         'first_name', 'last_name', 'class'])) {
                        $headers[] = $this->formatFieldName($key);
                    }
                }
                
                $headers[] = 'Дата создания';
                
                // Заголовки таблицы
                $col = 'A';
                foreach ($headers as $header) {
                    $sheet->setCellValue($col . $row, $header);
                    $sheet->getStyle($col . $row)->getFont()->setBold(true);
                    $col++;
                }
                
                $row++;
                
                // Данные
                foreach ($moduleData['results'] as $result) {
                    $col = 'A';
                    
                    $sheet->setCellValue($col++ . $row, $result['id']);
                    $sheet->setCellValue($col++ . $row, $result['last_name'] ?? '');
                    $sheet->setCellValue($col++ . $row, $result['first_name'] ?? '');
                    $sheet->setCellValue($col++ . $row, $result['class'] ?? '');
                    $sheet->setCellValue($col++ . $row, $result['test_date'] ? date('d.m.Y', strtotime($result['test_date'])) : '');
                    
                    foreach ($firstResult as $key => $value) {
                        if (!in_array($key, ['id', 'child_id', 'psychologist_id', 'test_date', 'created_at', 
                                             'first_name', 'last_name', 'class'])) {
                            $sheet->setCellValue($col++ . $row, $result[$key] ?? '');
                        }
                    }
                    
                    $sheet->setCellValue($col . $row, $result['created_at'] ? date('d.m.Y H:i', strtotime($result['created_at'])) : '');
                    $row++;
                }
                
                // Автонастройка ширины
                $lastColumn = $sheet->getHighestColumn();
                for ($col = 'A'; $col <= $lastColumn; $col++) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            }
        }
        
        // Создание сводного листа
        $summarySheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Сводка');
        $spreadsheet->addSheet($summarySheet, 0);
        $summarySheet = $spreadsheet->getSheetByName('Сводка');
        
        // Заполнение сводки
        $summarySheet->setCellValue('A1', 'Сводный отчет по тестам');
        $summarySheet->mergeCells('A1:D1');
        $summarySheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        
        $row = 3;
        $summarySheet->setCellValue('A' . $row, 'Психолог:');
        $summarySheet->setCellValue('B' . $row, $data['psychologist']['full_name']);
        $summarySheet->getStyle('A' . $row)->getFont()->setBold(true);
        
        $row++;
        $summarySheet->setCellValue('A' . $row, 'Дата экспорта:');
        $summarySheet->setCellValue('B' . $row, $data['export_date']);
        $summarySheet->getStyle('A' . $row)->getFont()->setBold(true);
        
        $row += 2;
        
        $summarySheet->setCellValue('A' . $row, 'Тест');
        $summarySheet->setCellValue('B' . $row, 'Количество записей');
        $summarySheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);
        
        $row++;
        
        $total = 0;
        foreach ($data['modules'] as $moduleData) {
            $summarySheet->setCellValue('A' . $row, $moduleData['name']);
            $summarySheet->setCellValue('B' . $row, $moduleData['count']);
            $total += $moduleData['count'];
            $row++;
        }
        
        $row++;
        $summarySheet->setCellValue('A' . $row, 'Всего:');
        $summarySheet->setCellValue('B' . $row, $total);
        $summarySheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);
        
        // Автонастройка ширины для сводки
        $summarySheet->getColumnDimension('A')->setAutoSize(true);
        $summarySheet->getColumnDimension('B')->setAutoSize(true);
        
        // Отправка файла
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        $filename = 'all_tests_export_' . date('Y-m-d') . '.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer->save('php://output');
    }
    
    /**
     * Проверка CSRF токена
     * 
     * @return bool
     */
    private function validateCsrfToken(): bool
    {
        $token = $_POST['csrf_token'] ?? '';
        return $this->core->getTemplate()->verifyCsrfToken($token);
    }
    
    /**
     * Отображение страницы 404
     * 
     * @param string $message Сообщение
     */
    private function showNotFound(string $message): void
    {
        http_response_code(404);
        $content = $this->core->getTemplate()->render('errors/404.php', ['message' => $message]);
        echo $this->core->renderPage($content, 'Не найдено');
    }
    
    /**
     * Отображение страницы ошибки
     * 
     * @param string $message Сообщение об ошибке
     */
    private function showError(string $message): void
    {
        $content = $this->core->getTemplate()->render('errors/500.php', ['message' => $message]);
        echo $this->core->renderPage($content, 'Ошибка');
    }
}