<?php
/**
 * Контроллер для работы с детьми
 * 
 * @package App\Controllers
 */

namespace App\Controllers;

use Core\Core;
use Core\Router;
use Exception;

class ChildrenController
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
     * Список детей
     */
    public function index(): void
    {
        try {
            $psychologist = $this->core->getCurrentPsychologist();
            $psychologistId = $psychologist['psychologist_id'] ?? null;
            
            if (!$psychologistId) {
                throw new Exception('Не удалось определить ID психолога');
            }
            
            // Параметры фильтрации и пагинации
            $page = max(1, intval($_GET['page'] ?? 1));
            $perPage = 20;
            $offset = ($page - 1) * $perPage;
            
            $filters = [];
            
            // Фильтр по классу
            if (!empty($_GET['class'])) {
                $filters['class'] = $_GET['class'];
            }
            
            // Поиск по имени/фамилии
            if (!empty($_GET['search'])) {
                $filters['search'] = $_GET['search'];
            }
            
            // Получение детей с фильтрами
            $children = $this->core->getChildren($psychologistId, $filters);
            
            // Пагинация
            $totalChildren = count($children);
            $totalPages = ceil($totalChildren / $perPage);
            $children = array_slice($children, $offset, $perPage);
            
            // Получение уникальных классов для фильтра
            $uniqueClasses = $this->getUniqueClasses($psychologistId);
            
            // Статистика по детям
            $childrenStats = $this->getChildrenStats($psychologistId);
            
            $data = [
                'children' => $children,
                'total_children' => $totalChildren,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'per_page' => $perPage,
                'filters' => $filters,
                'unique_classes' => $uniqueClasses,
                'children_stats' => $childrenStats,
                'psychologist' => $psychologist
            ];
            
            $content = $this->core->getTemplate()->render('children/list.php', $data);
            echo $this->core->renderPage($content, 'Список детей');
            
        } catch (Exception $e) {
            error_log("Ошибка при загрузке списка детей: " . $e->getMessage());
            $this->showError('Произошла ошибка при загрузке списка детей');
        }
    }
    
    /**
     * Получение уникальных классов
     * 
     * @param int $psychologistId ID психолога
     * @return array
     */
    private function getUniqueClasses(int $psychologistId): array
    {
        $db = $this->core->getDB();
        
        return $db->fetchAll(
            "SELECT DISTINCT class 
             FROM children 
             WHERE psychologist_id = :psychologist_id 
             ORDER BY class",
            [':psychologist_id' => $psychologistId]
        );
    }
    
    /**
     * Получение статистики по детям
     * 
     * @param int $psychologistId ID психолога
     * @return array
     */
    private function getChildrenStats(int $psychologistId): array
    {
        $db = $this->core->getDB();
        
        // Общее количество детей
        $totalChildren = $db->fetch(
            "SELECT COUNT(*) as count FROM children WHERE psychologist_id = :psychologist_id",
            [':psychologist_id' => $psychologistId]
        )['count'] ?? 0;
        
        // Количество детей по классам
        $byClass = $db->fetchAll(
            "SELECT class, COUNT(*) as count 
             FROM children 
             WHERE psychologist_id = :psychologist_id 
             GROUP BY class 
             ORDER BY class",
            [':psychologist_id' => $psychologistId]
        );
        
        // Количество детей с датой рождения
        $withBirthDate = $db->fetch(
            "SELECT COUNT(*) as count 
             FROM children 
             WHERE psychologist_id = :psychologist_id 
             AND birth_date IS NOT NULL",
            [':psychologist_id' => $psychologistId]
        )['count'] ?? 0;
        
        // Средний возраст детей
        $avgAge = 'Не определен';
        if ($withBirthDate > 0) {
            $avgAgeResult = $db->fetch(
                "SELECT AVG(TIMESTAMPDIFF(YEAR, birth_date, CURDATE())) as avg_age 
                 FROM children 
                 WHERE psychologist_id = :psychologist_id 
                 AND birth_date IS NOT NULL",
                [':psychologist_id' => $psychologistId]
            );
            
            if ($avgAgeResult && $avgAgeResult['avg_age']) {
                $avgAge = round($avgAgeResult['avg_age'], 1) . ' лет';
            }
        }
        
        return [
            'total' => $totalChildren,
            'by_class' => $byClass,
            'with_birth_date' => $withBirthDate,
            'without_birth_date' => $totalChildren - $withBirthDate,
            'avg_age' => $avgAge
        ];
    }
    
    /**
     * Просмотр информации о ребенке
     * 
     * @param int $id ID ребенка
     */
    public function show(int $id): void
    {
        try {
            $psychologist = $this->core->getCurrentPsychologist();
            $psychologistId = $psychologist['psychologist_id'] ?? null;
            
            if (!$psychologistId) {
                throw new Exception('Не удалось определить ID психолога');
            }
            
            // Получение информации о ребенке
            $child = $this->core->getChild($id, $psychologistId);
            
            if (!$child) {
                $this->showNotFound('Ребенок не найден');
                return;
            }
            
            // Получение результатов тестов ребенка
            $testResults = $this->getChildTestResults($id, $psychologistId);
            
            // Статистика тестов
            $testStats = $this->getChildTestStats($testResults);
            
            // Получение истории изменений
            $changeHistory = $this->getChildChangeHistory($id);
            
            $data = [
                'child' => $child,
                'test_results' => $testResults,
                'test_stats' => $testStats,
                'change_history' => $changeHistory,
                'psychologist' => $psychologist
            ];
            
            $content = $this->core->getTemplate()->render('children/view.php', $data);
            echo $this->core->renderPage($content, 'Просмотр ребенка: ' . $child['first_name'] . ' ' . $child['last_name']);
            
        } catch (Exception $e) {
            error_log("Ошибка при загрузке информации о ребенке: " . $e->getMessage());
            $this->showError('Произошла ошибка при загрузке информации о ребенке');
        }
    }
    
    /**
     * Получение результатов тестов ребенка
     * 
     * @param int $childId ID ребенка
     * @param int $psychologistId ID психолога
     * @return array
     */
    private function getChildTestResults(int $childId, int $psychologistId): array
    {
        $db = $this->core->getDB();
        $testResults = [];
        $activeModules = $this->core->getActiveModules();
        
        foreach ($activeModules as $module) {
            $tableName = 'test_' . $module['module_key'] . '_results';
            
            if (!$db->tableExists($tableName)) {
                continue;
            }
            
            $results = $db->fetchAll(
                "SELECT * FROM {$tableName} 
                 WHERE child_id = :child_id 
                 AND psychologist_id = :psychologist_id 
                 ORDER BY test_date DESC, created_at DESC",
                [
                    ':child_id' => $childId,
                    ':psychologist_id' => $psychologistId
                ]
            );
            
            if (!empty($results)) {
                $testResults[$module['module_key']] = [
                    'module_name' => $module['name'],
                    'module_key' => $module['module_key'],
                    'category' => $module['category'] ?? 'общий',
                    'results' => $results,
                    'count' => count($results)
                ];
            }
        }
        
        return $testResults;
    }
    
    /**
     * Получение статистики тестов ребенка
     * 
     * @param array $testResults Результаты тестов
     * @return array
     */
    private function getChildTestStats(array $testResults): array
    {
        $totalTests = 0;
        $firstTestDate = null;
        $lastTestDate = null;
        $testsByModule = [];
        
        foreach ($testResults as $moduleKey => $moduleData) {
            $moduleTests = $moduleData['count'];
            $totalTests += $moduleTests;
            
            $testsByModule[] = [
                'name' => $moduleData['module_name'],
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
     * Получение истории изменений ребенка
     * 
     * @param int $childId ID ребенка
     * @return array
     */
    private function getChildChangeHistory(int $childId): array
    {
        $db = $this->core->getDB();
        
        // Проверка существования таблицы истории
        if (!$db->tableExists('child_history')) {
            return [];
        }
        
        return $db->fetchAll(
            "SELECT * FROM child_history 
             WHERE child_id = :child_id 
             ORDER BY created_at DESC 
             LIMIT 20",
            [':child_id' => $childId]
        );
    }
    
    /**
     * Отображение формы добавления ребенка
     */
    public function showAddForm(): void
    {
        try {
            $psychologist = $this->core->getCurrentPsychologist();
            
            // Получение списка классов для автодополнения
            $uniqueClasses = $this->getUniqueClasses($psychologist['psychologist_id']);
            
            $data = [
                'psychologist' => $psychologist,
                'unique_classes' => $uniqueClasses,
                'current_year' => date('Y')
            ];
            
            $content = $this->core->getTemplate()->render('children/add.php', $data);
            echo $this->core->renderPage($content, 'Добавление ребенка');
            
        } catch (Exception $e) {
            error_log("Ошибка при загрузке формы добавления ребенка: " . $e->getMessage());
            $this->showError('Произошла ошибка при загрузке формы');
        }
    }
    
    /**
     * Обработка добавления ребенка
     */
    public function add(): void
    {
        try {
            // Проверка CSRF токена
            if (!$this->validateCsrfToken()) {
                $this->showAddFormWithError('Недействительный токен безопасности');
                return;
            }
            
            $psychologist = $this->core->getCurrentPsychologist();
            $psychologistId = $psychologist['psychologist_id'] ?? null;
            
            if (!$psychologistId) {
                throw new Exception('Не удалось определить ID психолога');
            }
            
            // Валидация данных
            $errors = $this->validateChildData($_POST);
            
            if (!empty($errors)) {
                $this->showAddFormWithErrors($errors, $_POST);
                return;
            }
            
            // Подготовка данных
            $childData = [
                'first_name' => trim($_POST['first_name']),
                'last_name' => trim($_POST['last_name']),
                'class' => trim($_POST['class']),
                'birth_date' => !empty($_POST['birth_date']) ? $_POST['birth_date'] : null,
                'notes' => !empty($_POST['notes']) ? trim($_POST['notes']) : null
            ];
            
            // Добавление ребенка
            $childId = $this->core->addChild($childData, $psychologistId);
            
            // Запись в историю
            $this->logChildChange($childId, $psychologistId, 'create', 'Добавлен новый ребенок');
            
            // Перенаправление на страницу ребенка
            $this->router->redirect('/children/' . $childId . '?success=1');
            
        } catch (Exception $e) {
            error_log("Ошибка при добавлении ребенка: " . $e->getMessage());
            $this->showAddFormWithError('Произошла ошибка при добавлении ребенка: ' . $e->getMessage());
        }
    }
    
    /**
     * Отображение формы редактирования ребенка
     * 
     * @param int $id ID ребенка
     */
    public function showEditForm(int $id): void
    {
        try {
            $psychologist = $this->core->getCurrentPsychologist();
            $psychologistId = $psychologist['psychologist_id'] ?? null;
            
            if (!$psychologistId) {
                throw new Exception('Не удалось определить ID психолога');
            }
            
            // Получение информации о ребенке
            $child = $this->core->getChild($id, $psychologistId);
            
            if (!$child) {
                $this->showNotFound('Ребенок не найден');
                return;
            }
            
            // Получение списка классов для автодополнения
            $uniqueClasses = $this->getUniqueClasses($psychologistId);
            
            $data = [
                'child' => $child,
                'psychologist' => $psychologist,
                'unique_classes' => $uniqueClasses,
                'current_year' => date('Y')
            ];
            
            $content = $this->core->getTemplate()->render('children/edit.php', $data);
            echo $this->core->renderPage($content, 'Редактирование ребенка: ' . $child['first_name'] . ' ' . $child['last_name']);
            
        } catch (Exception $e) {
            error_log("Ошибка при загрузке формы редактирования: " . $e->getMessage());
            $this->showError('Произошла ошибка при загрузке формы');
        }
    }
    
    /**
     * Обработка редактирования ребенка
     * 
     * @param int $id ID ребенка
     */
    public function edit(int $id): void
    {
        try {
            // Проверка CSRF токена
            if (!$this->validateCsrfToken()) {
                $this->showEditFormWithError($id, 'Недействительный токен безопасности');
                return;
            }
            
            $psychologist = $this->core->getCurrentPsychologist();
            $psychologistId = $psychologist['psychologist_id'] ?? null;
            
            if (!$psychologistId) {
                throw new Exception('Не удалось определить ID психолога');
            }
            
            // Проверка существования ребенка
            $child = $this->core->getChild($id, $psychologistId);
            
            if (!$child) {
                $this->showNotFound('Ребенок не найден');
                return;
            }
            
            // Валидация данных
            $errors = $this->validateChildData($_POST, $id);
            
            if (!empty($errors)) {
                $this->showEditFormWithErrors($id, $errors, $_POST);
                return;
            }
            
            // Подготовка данных для обновления
            $updateData = [
                'first_name' => trim($_POST['first_name']),
                'last_name' => trim($_POST['last_name']),
                'class' => trim($_POST['class']),
                'birth_date' => !empty($_POST['birth_date']) ? $_POST['birth_date'] : null,
                'notes' => !empty($_POST['notes']) ? trim($_POST['notes']) : null,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Определение измененных полей
            $changes = [];
            foreach ($updateData as $field => $newValue) {
                $oldValue = $child[$field] ?? null;
                if ($newValue != $oldValue) {
                    $changes[] = [
                        'field' => $field,
                        'old_value' => $oldValue,
                        'new_value' => $newValue
                    ];
                }
            }
            
            // Обновление данных
            $db = $this->core->getDB();
            $result = $db->update('children', $updateData, ['id' => $id, 'psychologist_id' => $psychologistId]);
            
            if ($result > 0 && !empty($changes)) {
                // Запись в историю
                $changeDescription = 'Изменены поля: ' . implode(', ', array_column($changes, 'field'));
                $this->logChildChange($id, $psychologistId, 'update', $changeDescription, json_encode($changes));
            }
            
            // Перенаправление на страницу ребенка
            $this->router->redirect('/children/' . $id . '?success=1');
            
        } catch (Exception $e) {
            error_log("Ошибка при редактировании ребенка: " . $e->getMessage());
            $this->showEditFormWithError($id, 'Произошла ошибка при редактировании ребенка: ' . $e->getMessage());
        }
    }
    
    /**
     * Удаление ребенка
     * 
     * @param int $id ID ребенка
     */
    public function delete(int $id): void
    {
        try {
            // Проверка CSRF токена
            if (!$this->validateCsrfToken()) {
                $this->showError('Недействительный токен безопасности');
                return;
            }
            
            $psychologist = $this->core->getCurrentPsychologist();
            $psychologistId = $psychologist['psychologist_id'] ?? null;
            
            if (!$psychologistId) {
                throw new Exception('Не удалось определить ID психолога');
            }
            
            // Проверка существования ребенка
            $child = $this->core->getChild($id, $psychologistId);
            
            if (!$child) {
                $this->showNotFound('Ребенок не найден');
                return;
            }
            
            // Проверка наличия связанных тестов
            $hasTests = $this->childHasTests($id, $psychologistId);
            
            if ($hasTests && empty($_POST['force_delete'])) {
                // Показать предупреждение о наличии тестов
                $this->showDeleteConfirmation($id, $child);
                return;
            }
            
            // Удаление ребенка
            $db = $this->core->getDB();
            
            // Начало транзакции
            $db->beginTransaction();
            
            try {
                // Запись в историю перед удалением
                $this->logChildChange($id, $psychologistId, 'delete', 'Удален ребенок', json_encode($child));
                
                // Удаление ребенка (каскадное удаление тестов через foreign key)
                $result = $db->delete('children', ['id' => $id, 'psychologist_id' => $psychologistId]);
                
                if ($result > 0) {
                    $db->commit();
                    
                    // Перенаправление на список детей
                    $this->router->redirect('/children?deleted=1');
                } else {
                    $db->rollback();
                    throw new Exception('Не удалось удалить ребенка');
                }
                
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            error_log("Ошибка при удалении ребенка: " . $e->getMessage());
            $this->showError('Произошла ошибка при удалении ребенка: ' . $e->getMessage());
        }
    }
    
    /**
     * Проверка наличия тестов у ребенка
     * 
     * @param int $childId ID ребенка
     * @param int $psychologistId ID психолога
     * @return bool
     */
    private function childHasTests(int $childId, int $psychologistId): bool
    {
        $db = $this->core->getDB();
        $activeModules = $this->core->getActiveModules();
        
        foreach ($activeModules as $module) {
            $tableName = 'test_' . $module['module_key'] . '_results';
            
            if (!$db->tableExists($tableName)) {
                continue;
            }
            
            $testCount = $db->fetch(
                "SELECT COUNT(*) as count FROM {$tableName} 
                 WHERE child_id = :child_id 
                 AND psychologist_id = :psychologist_id",
                [
                    ':child_id' => $childId,
                    ':psychologist_id' => $psychologistId
                ]
            )['count'] ?? 0;
            
            if ($testCount > 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Отображение подтверждения удаления
     * 
     * @param int $id ID ребенка
     * @param array $child Данные ребенка
     */
    private function showDeleteConfirmation(int $id, array $child): void
    {
        try {
            // Получение информации о тестах
            $testInfo = $this->getChildTestsInfo($id, $child['psychologist_id']);
            
            $data = [
                'child' => $child,
                'test_info' => $testInfo,
                'psychologist' => $this->core->getCurrentPsychologist()
            ];
            
            $content = $this->core->getTemplate()->render('children/delete-confirm.php', $data);
            echo $this->core->renderPage($content, 'Подтверждение удаления: ' . $child['first_name'] . ' ' . $child['last_name']);
            
        } catch (Exception $e) {
            error_log("Ошибка при загрузке подтверждения удаления: " . $e->getMessage());
            $this->showError('Произошла ошибка');
        }
    }
    
    /**
     * Получение информации о тестах ребенка
     * 
     * @param int $childId ID ребенка
     * @param int $psychologistId ID психолога
     * @return array
     */
    private function getChildTestsInfo(int $childId, int $psychologistId): array
    {
        $db = $this->core->getDB();
        $testInfo = [];
        $activeModules = $this->core->getActiveModules();
        
        foreach ($activeModules as $module) {
            $tableName = 'test_' . $module['module_key'] . '_results';
            
            if (!$db->tableExists($tableName)) {
                continue;
            }
            
            $tests = $db->fetchAll(
                "SELECT COUNT(*) as count, 
                        MIN(test_date) as first_test, 
                        MAX(test_date) as last_test 
                 FROM {$tableName} 
                 WHERE child_id = :child_id 
                 AND psychologist_id = :psychologist_id",
                [
                    ':child_id' => $childId,
                    ':psychologist_id' => $psychologistId
                ]
            );
            
            if ($tests && $tests[0]['count'] > 0) {
                $testInfo[] = [
                    'module_name' => $module['name'],
                    'count' => $tests[0]['count'],
                    'first_test' => $tests[0]['first_test'],
                    'last_test' => $tests[0]['last_test']
                ];
            }
        }
        
        return $testInfo;
    }
    
    /**
     * Экспорт данных о детях
     */
    public function export(): void
    {
        try {
            $psychologist = $this->core->getCurrentPsychologist();
            $psychologistId = $psychologist['psychologist_id'] ?? null;
            
            if (!$psychologistId) {
                throw new Exception('Не удалось определить ID психолога');
            }
            
            $format = $_GET['format'] ?? 'excel';
            $classFilter = $_GET['class'] ?? null;
            
            // Получение детей
            $filters = [];
            if ($classFilter) {
                $filters['class'] = $classFilter;
            }
            
            $children = $this->core->getChildren($psychologistId, $filters);
            
            // Получение результатов тестов для каждого ребенка
            foreach ($children as &$child) {
                $child['test_results'] = $this->getChildTestResults($child['id'], $psychologistId);
                $child['age'] = $this->calculateAge($child['birth_date']);
            }
            
            $exportData = [
                'children' => $children,
                'psychologist' => $psychologist,
                'class_filter' => $classFilter,
                'export_date' => date('d.m.Y H:i'),
                'total_count' => count($children)
            ];
            
            // Генерация экспорта
            switch ($format) {
                case 'excel':
                    $this->generateChildrenExcel($exportData);
                    break;
                case 'pdf':
                    $this->generateChildrenPdf($exportData);
                    break;
                case 'csv':
                    $this->generateChildrenCsv($exportData);
                    break;
                default:
                    throw new Exception('Неподдерживаемый формат экспорта');
            }
            
        } catch (Exception $e) {
            error_log("Ошибка при экспорте данных: " . $e->getMessage());
            $this->showError('Произошла ошибка при экспорте данных');
        }
    }
    
    /**
     * Генерация Excel с данными о детях
     * 
     * @param array $data Данные для экспорта
     */
    private function generateChildrenExcel(array $data): void
    {
        try {
            if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
                throw new Exception('Библиотека PhpSpreadsheet не установлена');
            }
            
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Заголовок
            $sheet->setCellValue('A1', 'Экспорт данных о детях');
            $sheet->mergeCells('A1:F1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');
            
            // Информация об экспорте
            $row = 3;
            $sheet->setCellValue('A' . $row, 'Психолог:');
            $sheet->setCellValue('B' . $row, $data['psychologist']['full_name']);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            
            $row++;
            $sheet->setCellValue('A' . $row, 'Дата экспорта:');
            $sheet->setCellValue('B' . $row, $data['export_date']);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            
            if ($data['class_filter']) {
                $row++;
                $sheet->setCellValue('A' . $row, 'Класс:');
                $sheet->setCellValue('B' . $row, $data['class_filter']);
                $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            }
            
            $row++;
            $sheet->setCellValue('A' . $row, 'Всего детей:');
            $sheet->setCellValue('B' . $row, $data['total_count']);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            
            $row += 2;
            
            // Заголовки таблицы
            $headers = ['ID', 'Фамилия', 'Имя', 'Класс', 'Дата рождения', 'Возраст', 'Тестов всего'];
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
            
            // Данные детей
            foreach ($data['children'] as $child) {
                $totalTests = 0;
                foreach ($child['test_results'] as $moduleResults) {
                    $totalTests += $moduleResults['count'];
                }
                
                $sheet->setCellValue('A' . $row, $child['id']);
                $sheet->setCellValue('B' . $row, $child['last_name']);
                $sheet->setCellValue('C' . $row, $child['first_name']);
                $sheet->setCellValue('D' . $row, $child['class']);
                $sheet->setCellValue('E' . $row, $child['birth_date'] ? date('d.m.Y', strtotime($child['birth_date'])) : '');
                $sheet->setCellValue('F' . $row, $child['age'] ?? '');
                $sheet->setCellValue('G' . $row, $totalTests);
                $row++;
            }
            
            // Автонастройка ширины
            foreach (range('A', 'G') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
            
            // Отправка файла
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="children_export_' . date('Y-m-d') . '.xlsx"');
            header('Cache-Control: max-age=0');
            
            $writer->save('php://output');
            
        } catch (Exception $e) {
            throw new Exception("Ошибка генерации Excel: " . $e->getMessage());
        }
    }
    
    /**
     * Генерация PDF с данными о детях
     * 
     * @param array $data Данные для экспорта
     */
    private function generateChildrenPdf(array $data): void
    {
        try {
            if (!class_exists('TCPDF')) {
                throw new Exception('Библиотека TCPDF не установлена');
            }
            
            // Аналогично DashboardController::generatePdfReport
            // Создание структурированного PDF отчета
            // Из-за ограничения длины пока оставляем заглушку
            
            throw new Exception('PDF экспорт детей в разработке');
            
        } catch (Exception $e) {
            throw new Exception("Ошибка генерации PDF: " . $e->getMessage());
        }
    }
    
    /**
     * Генерация CSV с данными о детях
     * 
     * @param array $data Данные для экспорта
     */
    private function generateChildrenCsv(array $data): void
    {
        try {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment;filename="children_export_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            
            // BOM для корректного отображения кириллицы в Excel
            fwrite($output, "\xEF\xBB\xBF");
            
            // Заголовки
            $headers = ['ID', 'Фамилия', 'Имя', 'Класс', 'Дата рождения', 'Возраст', 'Тестов всего'];
            fputcsv($output, $headers, ';');
            
            // Данные
            foreach ($data['children'] as $child) {
                $totalTests = 0;
                foreach ($child['test_results'] as $moduleResults) {
                    $totalTests += $moduleResults['count'];
                }
                
                $row = [
                    $child['id'],
                    $child['last_name'],
                    $child['first_name'],
                    $child['class'],
                    $child['birth_date'] ? date('d.m.Y', strtotime($child['birth_date'])) : '',
                    $child['age'] ?? '',
                    $totalTests
                ];
                
                fputcsv($output, $row, ';');
            }
            
            fclose($output);
            
        } catch (Exception $e) {
            throw new Exception("Ошибка генерации CSV: " . $e->getMessage());
        }
    }
    
    /**
     * Расчет возраста
     * 
     * @param string|null $birthDate Дата рождения
     * @return string
     */
    private function calculateAge(?string $birthDate): string
    {
        if (!$birthDate) {
            return '';
        }
        
        $birth = new \DateTime($birthDate);
        $now = new \DateTime();
        $interval = $now->diff($birth);
        
        return $interval->y . ' лет';
    }
    
    /**
     * Валидация данных ребенка
     * 
     * @param array $data Данные для валидации
     * @param int|null $childId ID ребенка (для проверки уникальности)
     * @return array
     */
    private function validateChildData(array $data, ?int $childId = null): array
    {
        $errors = [];
        
        // Проверка имени
        if (empty($data['first_name'])) {
            $errors['first_name'] = 'Имя обязательно для заполнения';
        } elseif (strlen($data['first_name']) < 2) {
            $errors['first_name'] = 'Имя должно содержать не менее 2 символов';
        } elseif (strlen($data['first_name']) > 50) {
            $errors['first_name'] = 'Имя должно содержать не более 50 символов';
        } elseif (!preg_match('/^[а-яА-ЯёЁ\- ]+$/u', $data['first_name'])) {
            $errors['first_name'] = 'Имя может содержать только русские буквы, дефисы и пробелы';
        }
        
        // Проверка фамилии
        if (empty($data['last_name'])) {
            $errors['last_name'] = 'Фамилия обязательна для заполнения';
        } elseif (strlen($data['last_name']) < 2) {
            $errors['last_name'] = 'Фамилия должна содержать не менее 2 символов';
        } elseif (strlen($data['last_name']) > 50) {
            $errors['last_name'] = 'Фамилия должна содержать не более 50 символов';
        } elseif (!preg_match('/^[а-яА-ЯёЁ\- ]+$/u', $data['last_name'])) {
            $errors['last_name'] = 'Фамилия может содержать только русские буквы, дефисы и пробелы';
        }
        
        // Проверка класса
        if (empty($data['class'])) {
            $errors['class'] = 'Класс обязателен для заполнения';
        } elseif (!preg_match('/^\d{1,2}[А-ЯA-Z]$/u', $data['class'])) {
            $errors['class'] = 'Класс должен быть в формате: цифра(цифры) и буква (например: 5А, 10Б)';
        }
        
        // Проверка даты рождения
        if (!empty($data['birth_date'])) {
            $date = \DateTime::createFromFormat('Y-m-d', $data['birth_date']);
            if (!$date || $date->format('Y-m-d') !== $data['birth_date']) {
                $errors['birth_date'] = 'Неверный формат даты рождения';
            } else {
                $minDate = new \DateTime('-25 years');
                $maxDate = new \DateTime('-3 years');
                
                if ($date < $minDate) {
                    $errors['birth_date'] = 'Дата рождения не может быть более 25 лет назад';
                } elseif ($date > $maxDate) {
                    $errors['birth_date'] = 'Дата рождения не может быть менее 3 лет назад';
                }
            }
        }
        
        // Проверка уникальности (если указан childId - исключаем его из проверки)
        if (empty($errors)) {
            $psychologistId = $this->core->getCurrentPsychologist()['psychologist_id'];
            $db = $this->core->getDB();
            
            $sql = "SELECT id FROM children 
                    WHERE psychologist_id = :psychologist_id 
                    AND first_name = :first_name 
                    AND last_name = :last_name 
                    AND class = :class";
            
            $params = [
                ':psychologist_id' => $psychologistId,
                ':first_name' => trim($data['first_name']),
                ':last_name' => trim($data['last_name']),
                ':class' => trim($data['class'])
            ];
            
            if ($childId) {
                $sql .= " AND id != :child_id";
                $params[':child_id'] = $childId;
            }
            
            $existing = $db->fetch($sql, $params);
            
            if ($existing) {
                $errors['general'] = 'Ребенок с такими именем, фамилией и классом уже существует';
            }
        }
        
        return $errors;
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
     * Логирование изменений ребенка
     * 
     * @param int $childId ID ребенка
     * @param int $psychologistId ID психолога
     * @param string $action Действие (create, update, delete)
     * @param string $description Описание
     * @param string|null $details Детали
     */
    private function logChildChange(int $childId, int $psychologistId, string $action, string $description, ?string $details = null): void
    {
        try {
            $db = $this->core->getDB();
            
            // Проверка существования таблицы истории
            if (!$db->tableExists('child_history')) {
                $this->createChildHistoryTable();
            }
            
            $db->insert('child_history', [
                'child_id' => $childId,
                'psychologist_id' => $psychologistId,
                'action' => $action,
                'description' => $description,
                'details' => $details,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            error_log("Ошибка при логировании изменений ребенка: " . $e->getMessage());
        }
    }
    
    /**
     * Создание таблицы истории изменений
     */
    private function createChildHistoryTable(): void
    {
        $db = $this->core->getDB();
        
        $sql = "CREATE TABLE IF NOT EXISTS child_history (
            id INT PRIMARY KEY AUTO_INCREMENT,
            child_id INT NOT NULL,
            psychologist_id INT NOT NULL,
            action VARCHAR(20) NOT NULL,
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
        
        $db->query($sql);
    }
    
    /**
     * Отображение формы добавления с ошибкой
     * 
     * @param string $errorMessage Сообщение об ошибке
     */
    private function showAddFormWithError(string $errorMessage): void
    {
        $this->showAddFormWithErrors(['general' => [$errorMessage]], $_POST);
    }
    
    /**
     * Отображение формы добавления с ошибками
     * 
     * @param array $errors Массив ошибок
     * @param array $data Данные формы
     */
    private function showAddFormWithErrors(array $errors, array $data): void
    {
        try {
            $psychologist = $this->core->getCurrentPsychologist();
            $uniqueClasses = $this->getUniqueClasses($psychologist['psychologist_id']);
            
            $templateData = [
                'errors' => $errors,
                'form_data' => $data,
                'psychologist' => $psychologist,
                'unique_classes' => $uniqueClasses,
                'current_year' => date('Y')
            ];
            
            $content = $this->core->getTemplate()->render('children/add.php', $templateData);
            echo $this->core->renderPage($content, 'Добавление ребенка');
            
        } catch (Exception $e) {
            error_log("Ошибка при отображении формы с ошибками: " . $e->getMessage());
            $this->showError('Произошла ошибка');
        }
    }
    
    /**
     * Отображение формы редактирования с ошибкой
     * 
     * @param int $id ID ребенка
     * @param string $errorMessage Сообщение об ошибке
     */
    private function showEditFormWithError(int $id, string $errorMessage): void
    {
        $this->showEditFormWithErrors($id, ['general' => [$errorMessage]], $_POST);
    }
    
    /**
     * Отображение формы редактирования с ошибками
     * 
     * @param int $id ID ребенка
     * @param array $errors Массив ошибок
     * @param array $data Данные формы
     */
    private function showEditFormWithErrors(int $id, array $errors, array $data): void
    {
        try {
            $psychologist = $this->core->getCurrentPsychologist();
            $psychologistId = $psychologist['psychologist_id'];
            $uniqueClasses = $this->getUniqueClasses($psychologistId);
            
            // Получение текущих данных ребенка
            $child = $this->core->getChild($id, $psychologistId);
            
            if (!$child) {
                $this->showNotFound('Ребенок не найден');
                return;
            }
            
            // Объединение текущих данных с отправленными
            $formData = array_merge($child, $data);
            
            $templateData = [
                'child' => $formData,
                'errors' => $errors,
                'psychologist' => $psychologist,
                'unique_classes' => $uniqueClasses,
                'current_year' => date('Y')
            ];
            
            $content = $this->core->getTemplate()->render('children/edit.php', $templateData);
            echo $this->core->renderPage($content, 'Редактирование ребенка');
            
        } catch (Exception $e) {
            error_log("Ошибка при отображении формы редактирования с ошибками: " . $e->getMessage());
            $this->showError('Произошла ошибка');
        }
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