<?php
/**
 * Контроллер дашборда системы
 * 
 * @package App\Controllers
 */

namespace App\Controllers;

use Core\Core;
use Core\Router;
use Exception;

class DashboardController
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
     * 
     * @throws Exception Если пользователь не авторизован
     */
    private function requireAuthentication(): void
    {
        if (!$this->core->isAuthenticated()) {
            $this->router->redirect('/login');
            exit;
        }
    }
    
    /**
     * Главная страница дашборда
     */
    public function index(): void
    {
        try {
            $psychologist = $this->core->getCurrentPsychologist();
            $psychologistId = $psychologist['psychologist_id'] ?? null;
            
            if (!$psychologistId) {
                throw new Exception('Не удалось определить ID психолога');
            }
            
            // Получение статистики
            $stats = $this->getDashboardStats($psychologistId);
            
            // Получение последних добавленных детей
            $recentChildren = $this->getRecentChildren($psychologistId, 5);
            
            // Получение последних результатов тестов
            $recentTests = $this->getRecentTests($psychologistId, 5);
            
            // Получение активных модулей
            $activeModules = $this->core->getActiveModules();
            
            // Подготовка данных для шаблона
            $data = [
                'stats' => $stats,
                'recent_children' => $recentChildren,
                'recent_tests' => $recentTests,
                'active_modules' => $activeModules,
                'psychologist' => $psychologist
            ];
            
            $content = $this->core->getTemplate()->render('dashboard/index.php', $data);
            echo $this->core->renderPage($content, 'Дашборд');
            
        } catch (Exception $e) {
            error_log("Ошибка при загрузке дашборда: " . $e->getMessage());
            $this->showError('Произошла ошибка при загрузке дашборда');
        }
    }
    
    /**
     * Получение статистики для дашборда
     * 
     * @param int $psychologistId ID психолога
     * @return array
     */
    private function getDashboardStats(int $psychologistId): array
    {
        $db = $this->core->getDB();
        
        // Количество детей
        $childrenCount = $db->fetch(
            "SELECT COUNT(*) as count FROM children WHERE psychologist_id = :psychologist_id",
            [':psychologist_id' => $psychologistId]
        )['count'] ?? 0;
        
        // Количество уникальных классов
        $classesCount = $db->fetch(
            "SELECT COUNT(DISTINCT class) as count FROM children WHERE psychologist_id = :psychologist_id",
            [':psychologist_id' => $psychologistId]
        )['count'] ?? 0;
        
        // Общее количество тестов
        $totalTests = 0;
        $activeModules = $this->core->getActiveModules();
        
        foreach ($activeModules as $module) {
            $tableName = 'test_' . $module['module_key'] . '_results';
            
            if ($db->tableExists($tableName)) {
                $moduleTests = $db->fetch(
                    "SELECT COUNT(*) as count FROM {$tableName} WHERE psychologist_id = :psychologist_id",
                    [':psychologist_id' => $psychologistId]
                )['count'] ?? 0;
                
                $totalTests += $moduleTests;
            }
        }
        
        // Тесты за последние 30 дней
        $recentTests = 0;
        $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
        
        foreach ($activeModules as $module) {
            $tableName = 'test_' . $module['module_key'] . '_results';
            
            if ($db->tableExists($tableName)) {
                $moduleRecentTests = $db->fetch(
                    "SELECT COUNT(*) as count FROM {$tableName} 
                     WHERE psychologist_id = :psychologist_id 
                     AND test_date >= :start_date",
                    [
                        ':psychologist_id' => $psychologistId,
                        ':start_date' => $thirtyDaysAgo
                    ]
                )['count'] ?? 0;
                
                $recentTests += $moduleRecentTests;
            }
        }
        
        // Среднее количество тестов на ребенка
        $avgTestsPerChild = $childrenCount > 0 ? round($totalTests / $childrenCount, 1) : 0;
        
        return [
            'children_count' => $childrenCount,
            'classes_count' => $classesCount,
            'total_tests' => $totalTests,
            'recent_tests' => $recentTests,
            'avg_tests_per_child' => $avgTestsPerChild
        ];
    }
    
    /**
     * Получение последних добавленных детей
     * 
     * @param int $psychologistId ID психолога
     * @param int $limit Количество записей
     * @return array
     */
    private function getRecentChildren(int $psychologistId, int $limit = 5): array
    {
        $db = $this->core->getDB();
        
        return $db->fetchAll(
            "SELECT id, first_name, last_name, class, birth_date, created_at 
             FROM children 
             WHERE psychologist_id = :psychologist_id 
             ORDER BY created_at DESC 
             LIMIT :limit",
            [
                ':psychologist_id' => $psychologistId,
                ':limit' => $limit
            ]
        );
    }
    
    /**
     * Получение последних результатов тестов
     * 
     * @param int $psychologistId ID психолога
     * @param int $limit Количество записей
     * @return array
     */
    private function getRecentTests(int $psychologistId, int $limit = 5): array
    {
        $db = $this->core->getDB();
        $recentTests = [];
        $activeModules = $this->core->getActiveModules();
        
        foreach ($activeModules as $module) {
            $tableName = 'test_' . $module['module_key'] . '_results';
            
            if (!$db->tableExists($tableName)) {
                continue;
            }
            
            $moduleTests = $db->fetchAll(
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
            
            foreach ($moduleTests as $test) {
                $test['module_name'] = $module['name'];
                $test['module_key'] = $module['module_key'];
                $recentTests[] = $test;
            }
        }
        
        // Сортировка по дате создания (новые сверху)
        usort($recentTests, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        // Ограничение количества результатов
        return array_slice($recentTests, 0, $limit);
    }
    
    /**
     * Отображение статистики по классам
     */
    public function classStats(): void
    {
        try {
            $psychologist = $this->core->getCurrentPsychologist();
            $psychologistId = $psychologist['psychologist_id'] ?? null;
            
            if (!$psychologistId) {
                throw new Exception('Не удалось определить ID психолога');
            }
            
            // Получение списка классов
            $classes = $this->getClassesWithStats($psychologistId);
            
            // Получение общего распределения по классам
            $classDistribution = $this->getClassDistribution($psychologistId);
            
            $data = [
                'classes' => $classes,
                'class_distribution' => $classDistribution,
                'psychologist' => $psychologist
            ];
            
            $content = $this->core->getTemplate()->render('dashboard/class-stats.php', $data);
            echo $this->core->renderPage($content, 'Статистика по классам');
            
        } catch (Exception $e) {
            error_log("Ошибка при загрузке статистики по классам: " . $e->getMessage());
            $this->showError('Произошла ошибка при загрузке статистики');
        }
    }
    
    /**
     * Получение списка классов со статистикой
     * 
     * @param int $psychologistId ID психолога
     * @return array
     */
    private function getClassesWithStats(int $psychologistId): array
    {
        $db = $this->core->getDB();
        
        // Получение всех классов
        $classes = $db->fetchAll(
            "SELECT DISTINCT class 
             FROM children 
             WHERE psychologist_id = :psychologist_id 
             ORDER BY class",
            [':psychologist_id' => $psychologistId]
        );
        
        $result = [];
        $activeModules = $this->core->getActiveModules();
        
        foreach ($classes as $class) {
            $className = $class['class'];
            
            // Количество детей в классе
            $childrenCount = $db->fetch(
                "SELECT COUNT(*) as count 
                 FROM children 
                 WHERE psychologist_id = :psychologist_id 
                 AND class = :class",
                [
                    ':psychologist_id' => $psychologistId,
                    ':class' => $className
                ]
            )['count'] ?? 0;
            
            // Количество тестов по классу
            $totalTests = 0;
            
            foreach ($activeModules as $module) {
                $tableName = 'test_' . $module['module_key'] . '_results';
                
                if ($db->tableExists($tableName)) {
                    $classTests = $db->fetch(
                        "SELECT COUNT(*) as count 
                         FROM {$tableName} t
                         JOIN children c ON t.child_id = c.id
                         WHERE t.psychologist_id = :psychologist_id 
                         AND c.class = :class",
                        [
                            ':psychologist_id' => $psychologistId,
                            ':class' => $className
                        ]
                    )['count'] ?? 0;
                    
                    $totalTests += $classTests;
                }
            }
            
            $result[] = [
                'class' => $className,
                'children_count' => $childrenCount,
                'total_tests' => $totalTests,
                'avg_tests_per_child' => $childrenCount > 0 ? round($totalTests / $childrenCount, 1) : 0
            ];
        }
        
        return $result;
    }
    
    /**
     * Получение распределения детей по классам
     * 
     * @param int $psychologistId ID психолога
     * @return array
     */
    private function getClassDistribution(int $psychologistId): array
    {
        $db = $this->core->getDB();
        
        return $db->fetchAll(
            "SELECT class, COUNT(*) as count 
             FROM children 
             WHERE psychologist_id = :psychologist_id 
             GROUP BY class 
             ORDER BY class",
            [':psychologist_id' => $psychologistId]
        );
    }
    
    /**
     * Отображение активности по месяцам
     */
    public function monthlyActivity(): void
    {
        try {
            $psychologist = $this->core->getCurrentPsychologist();
            $psychologistId = $psychologist['psychologist_id'] ?? null;
            
            if (!$psychologistId) {
                throw new Exception('Не удалось определить ID психолога');
            }
            
            // Получение активности за последние 12 месяцев
            $monthlyActivity = $this->getMonthlyActivity($psychologistId, 12);
            
            // Получение популярных тестов
            $popularTests = $this->getPopularTests($psychologistId);
            
            $data = [
                'monthly_activity' => $monthlyActivity,
                'popular_tests' => $popularTests,
                'psychologist' => $psychologist
            ];
            
            $content = $this->core->getTemplate()->render('dashboard/monthly-activity.php', $data);
            echo $this->core->renderPage($content, 'Активность по месяцам');
            
        } catch (Exception $e) {
            error_log("Ошибка при загрузке месячной активности: " . $e->getMessage());
            $this->showError('Произошла ошибка при загрузке статистики');
        }
    }
    
    /**
     * Получение месячной активности
     * 
     * @param int $psychologistId ID психолога
     * @param int $months Количество месяцев
     * @return array
     */
    private function getMonthlyActivity(int $psychologistId, int $months = 12): array
    {
        $db = $this->core->getDB();
        $activity = [];
        $activeModules = $this->core->getActiveModules();
        
        // Генерация списка месяцев
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $activity[$month] = 0;
        }
        
        // Подсчет тестов по месяцам
        foreach ($activeModules as $module) {
            $tableName = 'test_' . $module['module_key'] . '_results';
            
            if (!$db->tableExists($tableName)) {
                continue;
            }
            
            $monthlyData = $db->fetchAll(
                "SELECT DATE_FORMAT(test_date, '%Y-%m') as month, COUNT(*) as count 
                 FROM {$tableName} 
                 WHERE psychologist_id = :psychologist_id 
                 AND test_date >= DATE_SUB(CURDATE(), INTERVAL :months MONTH)
                 GROUP BY DATE_FORMAT(test_date, '%Y-%m') 
                 ORDER BY month",
                [
                    ':psychologist_id' => $psychologistId,
                    ':months' => $months
                ]
            );
            
            foreach ($monthlyData as $data) {
                if (isset($activity[$data['month']])) {
                    $activity[$data['month']] += $data['count'];
                }
            }
        }
        
        // Форматирование результата для графика
        $formattedActivity = [];
        foreach ($activity as $month => $count) {
            $formattedActivity[] = [
                'month' => $month,
                'count' => $count,
                'month_name' => $this->formatMonthName($month)
            ];
        }
        
        return $formattedActivity;
    }
    
    /**
     * Форматирование названия месяца
     * 
     * @param string $monthStr Строка в формате YYYY-MM
     * @return string
     */
    private function formatMonthName(string $monthStr): string
    {
        $monthNames = [
            '01' => 'Янв', '02' => 'Фев', '03' => 'Мар', '04' => 'Апр',
            '05' => 'Май', '06' => 'Июн', '07' => 'Июл', '08' => 'Авг',
            '09' => 'Сен', '10' => 'Окт', '11' => 'Ноя', '12' => 'Дек'
        ];
        
        list($year, $month) = explode('-', $monthStr);
        $monthName = $monthNames[$month] ?? $month;
        
        return $monthName . ' ' . $year;
    }
    
    /**
     * Получение популярных тестов
     * 
     * @param int $psychologistId ID психолога
     * @return array
     */
    private function getPopularTests(int $psychologistId): array
    {
        $db = $this->core->getDB();
        $popularTests = [];
        $activeModules = $this->core->getActiveModules();
        
        foreach ($activeModules as $module) {
            $tableName = 'test_' . $module['module_key'] . '_results';
            
            if (!$db->tableExists($tableName)) {
                continue;
            }
            
            $testCount = $db->fetch(
                "SELECT COUNT(*) as count 
                 FROM {$tableName} 
                 WHERE psychologist_id = :psychologist_id",
                [':psychologist_id' => $psychologistId]
            )['count'] ?? 0;
            
            if ($testCount > 0) {
                $popularTests[] = [
                    'name' => $module['name'],
                    'key' => $module['module_key'],
                    'count' => $testCount,
                    'category' => $module['category'] ?? 'общий'
                ];
            }
        }
        
        // Сортировка по количеству тестов (по убыванию)
        usort($popularTests, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        
        return array_slice($popularTests, 0, 10); // Топ-10
    }
    
    /**
     * Экспорт данных дашборда
     */
    public function export(): void
    {
        try {
            $psychologist = $this->core->getCurrentPsychologist();
            $psychologistId = $psychologist['psychologist_id'] ?? null;
            
            if (!$psychologistId) {
                throw new Exception('Не удалось определить ID психолога');
            }
            
            $format = $_GET['format'] ?? 'pdf';
            $type = $_GET['type'] ?? 'summary';
            
            // Определение типа отчета
            switch ($type) {
                case 'detailed':
                    $this->exportDetailedReport($psychologistId, $format, $psychologist);
                    break;
                case 'class_stats':
                    $this->exportClassStats($psychologistId, $format, $psychologist);
                    break;
                case 'monthly_activity':
                    $this->exportMonthlyActivity($psychologistId, $format, $psychologist);
                    break;
                default:
                    $this->exportSummaryReport($psychologistId, $format, $psychologist);
            }
            
        } catch (Exception $e) {
            error_log("Ошибка при экспорте дашборда: " . $e->getMessage());
            $this->showError('Произошла ошибка при экспорте данных');
        }
    }
    
    /**
     * Экспорт сводного отчета
     * 
     * @param int $psychologistId ID психолога
     * @param string $format Формат экспорта
     * @param array $psychologist Данные психолога
     */
    private function exportSummaryReport(int $psychologistId, string $format, array $psychologist): void
    {
        try {
            // Получение данных для отчета
            $stats = $this->getDashboardStats($psychologistId);
            $recentChildren = $this->getRecentChildren($psychologistId, 10);
            $recentTests = $this->getRecentTests($psychologistId, 10);
            $popularTests = $this->getPopularTests($psychologistId);
            
            $reportData = [
                'psychologist' => $psychologist,
                'stats' => $stats,
                'recent_children' => $recentChildren,
                'recent_tests' => $recentTests,
                'popular_tests' => $popularTests,
                'generated_date' => date('d.m.Y H:i'),
                'report_title' => 'Сводный отчет психолога'
            ];
            
            // Генерация отчета в выбранном формате
            switch ($format) {
                case 'pdf':
                    $this->generatePdfReport($reportData, 'summary_report.pdf');
                    break;
                case 'excel':
                    $this->generateExcelReport($reportData, 'summary_report.xlsx');
                    break;
                case 'html':
                    $this->generateHtmlReport($reportData);
                    break;
                default:
                    throw new Exception('Неподдерживаемый формат экспорта');
            }
            
        } catch (Exception $e) {
            throw new Exception("Ошибка при экспорте сводного отчета: " . $e->getMessage());
        }
    }
    
    /**
     * Экспорт детального отчета
     * 
     * @param int $psychologistId ID психолога
     * @param string $format Формат экспорта
     * @param array $psychologist Данные психолога
     */
    private function exportDetailedReport(int $psychologistId, string $format, array $psychologist): void
    {
        try {
            // Получение расширенных данных
            $stats = $this->getDashboardStats($psychologistId);
            $classes = $this->getClassesWithStats($psychologistId);
            $monthlyActivity = $this->getMonthlyActivity($psychologistId, 12);
            $popularTests = $this->getPopularTests($psychologistId);
            $allChildren = $this->getAllChildren($psychologistId);
            
            $reportData = [
                'psychologist' => $psychologist,
                'stats' => $stats,
                'classes' => $classes,
                'monthly_activity' => $monthlyActivity,
                'popular_tests' => $popularTests,
                'all_children' => $allChildren,
                'generated_date' => date('d.m.Y H:i'),
                'report_title' => 'Детальный отчет психолога'
            ];
            
            // Генерация отчета в выбранном формате
            switch ($format) {
                case 'pdf':
                    $this->generatePdfReport($reportData, 'detailed_report.pdf');
                    break;
                case 'excel':
                    $this->generateExcelReport($reportData, 'detailed_report.xlsx');
                    break;
                case 'html':
                    $this->generateHtmlReport($reportData);
                    break;
                default:
                    throw new Exception('Неподдерживаемый формат экспорта');
            }
            
        } catch (Exception $e) {
            throw new Exception("Ошибка при экспорте детального отчета: " . $e->getMessage());
        }
    }
    
    /**
     * Экспорт статистики по классам
     * 
     * @param int $psychologistId ID психолога
     * @param string $format Формат экспорта
     * @param array $psychologist Данные психолога
     */
    private function exportClassStats(int $psychologistId, string $format, array $psychologist): void
    {
        try {
            $classes = $this->getClassesWithStats($psychologistId);
            $classDistribution = $this->getClassDistribution($psychologistId);
            
            $reportData = [
                'psychologist' => $psychologist,
                'classes' => $classes,
                'class_distribution' => $classDistribution,
                'generated_date' => date('d.m.Y H:i'),
                'report_title' => 'Статистика по классам'
            ];
            
            switch ($format) {
                case 'pdf':
                    $this->generatePdfReport($reportData, 'class_stats_report.pdf');
                    break;
                case 'excel':
                    $this->generateExcelReport($reportData, 'class_stats_report.xlsx');
                    break;
                case 'html':
                    $this->generateHtmlReport($reportData);
                    break;
                default:
                    throw new Exception('Неподдерживаемый формат экспорта');
            }
            
        } catch (Exception $e) {
            throw new Exception("Ошибка при экспорте статистики по классам: " . $e->getMessage());
        }
    }
    
    /**
     * Экспорт месячной активности
     * 
     * @param int $psychologistId ID психолога
     * @param string $format Формат экспорта
     * @param array $psychologist Данные психолога
     */
    private function exportMonthlyActivity(int $psychologistId, string $format, array $psychologist): void
    {
        try {
            $monthlyActivity = $this->getMonthlyActivity($psychologistId, 12);
            $popularTests = $this->getPopularTests($psychologistId);
            
            $reportData = [
                'psychologist' => $psychologist,
                'monthly_activity' => $monthlyActivity,
                'popular_tests' => $popularTests,
                'generated_date' => date('d.m.Y H:i'),
                'report_title' => 'Активность по месяцам'
            ];
            
            switch ($format) {
                case 'pdf':
                    $this->generatePdfReport($reportData, 'monthly_activity_report.pdf');
                    break;
                case 'excel':
                    $this->generateExcelReport($reportData, 'monthly_activity_report.xlsx');
                    break;
                case 'html':
                    $this->generateHtmlReport($reportData);
                    break;
                default:
                    throw new Exception('Неподдерживаемый формат экспорта');
            }
            
        } catch (Exception $e) {
            throw new Exception("Ошибка при экспорте месячной активности: " . $e->getMessage());
        }
    }
    
    /**
     * Получение всех детей психолога
     * 
     * @param int $psychologistId ID психолога
     * @return array
     */
    private function getAllChildren(int $psychologistId): array
    {
        $db = $this->core->getDB();
        
        return $db->fetchAll(
            "SELECT id, first_name, last_name, class, birth_date, created_at 
             FROM children 
             WHERE psychologist_id = :psychologist_id 
             ORDER BY class, last_name, first_name",
            [':psychologist_id' => $psychologistId]
        );
    }
    
    /**
     * Генерация PDF отчета
     * 
     * @param array $data Данные для отчета
     * @param string $filename Имя файла
     */
    private function generatePdfReport(array $data, string $filename): void
    {
        try {
            // Проверка наличия библиотеки TCPDF
            if (!class_exists('TCPDF')) {
                throw new Exception('Библиотека TCPDF не установлена');
            }
            
            // Создание PDF документа
            $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Установка метаданных
            $pdf->SetCreator('Система управления психологическими данными');
            $pdf->SetAuthor($data['psychologist']['full_name'] ?? 'Психолог');
            $pdf->SetTitle($data['report_title']);
            $pdf->SetSubject('Отчет психолога');
            
            // Установка шрифта для поддержки русского языка
            $pdf->SetFont('dejavusans', '', 10);
            
            // Добавление страницы
            $pdf->AddPage();
            
            // Заголовок отчета
            $pdf->SetFont('dejavusans', 'B', 16);
            $pdf->Cell(0, 10, $data['report_title'], 0, 1, 'C');
            $pdf->Ln(5);
            
            // Информация о психологе
            $pdf->SetFont('dejavusans', 'B', 12);
            $pdf->Cell(0, 10, 'Психолог: ' . ($data['psychologist']['full_name'] ?? 'Не указано'), 0, 1);
            $pdf->Cell(0, 10, 'ID психолога: ' . ($data['psychologist']['psychologist_id'] ?? 'Не указано'), 0, 1);
            $pdf->Cell(0, 10, 'Дата формирования: ' . $data['generated_date'], 0, 1);
            $pdf->Ln(10);
            
            // Основная статистика
            if (isset($data['stats'])) {
                $pdf->SetFont('dejavusans', 'B', 14);
                $pdf->Cell(0, 10, 'Основная статистика', 0, 1);
                $pdf->SetFont('dejavusans', '', 12);
                
                $stats = $data['stats'];
                $pdf->Cell(0, 8, '• Количество детей: ' . $stats['children_count'], 0, 1);
                $pdf->Cell(0, 8, '• Количество классов: ' . $stats['classes_count'], 0, 1);
                $pdf->Cell(0, 8, '• Всего тестов: ' . $stats['total_tests'], 0, 1);
                $pdf->Cell(0, 8, '• Тестов за 30 дней: ' . $stats['recent_tests'], 0, 1);
                $pdf->Cell(0, 8, '• Среднее тестов на ребенка: ' . $stats['avg_tests_per_child'], 0, 1);
                $pdf->Ln(10);
            }
            
            // Статистика по классам
            if (isset($data['classes'])) {
                $pdf->SetFont('dejavusans', 'B', 14);
                $pdf->Cell(0, 10, 'Статистика по классам', 0, 1);
                $pdf->SetFont('dejavusans', '', 10);
                
                // Таблица классов
                $header = ['Класс', 'Детей', 'Тестов', 'Среднее на ребенка'];
                $w = [30, 30, 30, 40];
                
                // Заголовок таблицы
                for ($i = 0; $i < count($header); $i++) {
                    $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C');
                }
                $pdf->Ln();
                
                // Данные таблицы
                foreach ($data['classes'] as $class) {
                    $pdf->Cell($w[0], 6, $class['class'], 'LR', 0, 'L');
                    $pdf->Cell($w[1], 6, $class['children_count'], 'LR', 0, 'C');
                    $pdf->Cell($w[2], 6, $class['total_tests'], 'LR', 0, 'C');
                    $pdf->Cell($w[3], 6, $class['avg_tests_per_child'], 'LR', 0, 'C');
                    $pdf->Ln();
                }
                
                // Закрытие таблицы
                $pdf->Cell(array_sum($w), 0, '', 'T');
                $pdf->Ln(10);
            }
            
            // Месячная активность
            if (isset($data['monthly_activity'])) {
                $pdf->SetFont('dejavusans', 'B', 14);
                $pdf->Cell(0, 10, 'Активность по месяцам', 0, 1);
                $pdf->SetFont('dejavusans', '', 10);
                
                $header = ['Месяц', 'Количество тестов'];
                $w = [60, 50];
                
                // Заголовок таблицы
                for ($i = 0; $i < count($header); $i++) {
                    $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C');
                }
                $pdf->Ln();
                
                // Данные таблицы
                foreach ($data['monthly_activity'] as $activity) {
                    $pdf->Cell($w[0], 6, $activity['month_name'], 'LR', 0, 'L');
                    $pdf->Cell($w[1], 6, $activity['count'], 'LR', 0, 'C');
                    $pdf->Ln();
                }
                
                $pdf->Cell(array_sum($w), 0, '', 'T');
                $pdf->Ln(10);
            }
            
            // Популярные тесты
            if (isset($data['popular_tests'])) {
                $pdf->SetFont('dejavusans', 'B', 14);
                $pdf->Cell(0, 10, 'Популярные тесты', 0, 1);
                $pdf->SetFont('dejavusans', '', 10);
                
                $header = ['Тест', 'Категория', 'Количество'];
                $w = [80, 50, 30];
                
                for ($i = 0; $i < count($header); $i++) {
                    $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C');
                }
                $pdf->Ln();
                
                foreach ($data['popular_tests'] as $test) {
                    $pdf->Cell($w[0], 6, $test['name'], 'LR', 0, 'L');
                    $pdf->Cell($w[1], 6, $test['category'], 'LR', 0, 'C');
                    $pdf->Cell($w[2], 6, $test['count'], 'LR', 0, 'C');
                    $pdf->Ln();
                }
                
                $pdf->Cell(array_sum($w), 0, '', 'T');
            }
            
            // Отправка PDF в браузер
            $pdf->Output($filename, 'D');
            
        } catch (Exception $e) {
            throw new Exception("Ошибка генерации PDF: " . $e->getMessage());
        }
    }
    
    /**
     * Генерация Excel отчета
     * 
     * @param array $data Данные для отчета
     * @param string $filename Имя файла
     */
    private function generateExcelReport(array $data, string $filename): void
    {
        try {
            // Проверка наличия библиотеки PhpSpreadsheet
            if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
                throw new Exception('Библиотека PhpSpreadsheet не установлена');
            }
            
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Заголовок отчета
            $sheet->setTitle('Отчет');
            $sheet->setCellValue('A1', $data['report_title']);
            $sheet->mergeCells('A1:E1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');
            
            // Информация о психологе
            $row = 3;
            $sheet->setCellValue('A' . $row, 'Психолог:');
            $sheet->setCellValue('B' . $row, $data['psychologist']['full_name'] ?? 'Не указано');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            
            $row++;
            $sheet->setCellValue('A' . $row, 'ID психолога:');
            $sheet->setCellValue('B' . $row, $data['psychologist']['psychologist_id'] ?? 'Не указано');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            
            $row++;
            $sheet->setCellValue('A' . $row, 'Дата формирования:');
            $sheet->setCellValue('B' . $row, $data['generated_date']);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            
            $row += 2;
            
            // Основная статистика
            if (isset($data['stats'])) {
                $sheet->setCellValue('A' . $row, 'Основная статистика');
                $sheet->mergeCells('A' . $row . ':E' . $row);
                $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
                
                $row++;
                $stats = $data['stats'];
                
                $statData = [
                    ['Количество детей', $stats['children_count']],
                    ['Количество классов', $stats['classes_count']],
                    ['Всего тестов', $stats['total_tests']],
                    ['Тестов за 30 дней', $stats['recent_tests']],
                    ['Среднее тестов на ребенка', $stats['avg_tests_per_child']]
                ];
                
                foreach ($statData as $stat) {
                    $sheet->setCellValue('A' . $row, $stat[0]);
                    $sheet->setCellValue('B' . $row, $stat[1]);
                    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                    $row++;
                }
                
                $row++;
            }
            
            // Статистика по классам
            if (isset($data['classes'])) {
                $sheet->setCellValue('A' . $row, 'Статистика по классам');
                $sheet->mergeCells('A' . $row . ':E' . $row);
                $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
                
                $row++;
                $headers = ['Класс', 'Количество детей', 'Всего тестов', 'Среднее на ребенка'];
                
                // Заголовки таблицы
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
                
                // Данные таблицы
                foreach ($data['classes'] as $class) {
                    $sheet->setCellValue('A' . $row, $class['class']);
                    $sheet->setCellValue('B' . $row, $class['children_count']);
                    $sheet->setCellValue('C' . $row, $class['total_tests']);
                    $sheet->setCellValue('D' . $row, $class['avg_tests_per_child']);
                    $row++;
                }
                
                $row++;
            }
            
            // Автонастройка ширины столбцов
            foreach (range('A', 'E') as $columnID) {
                $sheet->getColumnDimension($columnID)->setAutoSize(true);
            }
            
            // Добавление границ
            $styleArray = [
                'borders' => [
                    'outline' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    ],
                ],
            ];
            
            $lastColumn = $sheet->getHighestColumn();
            $lastRow = $sheet->getHighestRow();
            $sheet->getStyle('A1:' . $lastColumn . $lastRow)->applyFromArray($styleArray);
            
            // Создание writer и отправка файла
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            
            $writer->save('php://output');
            
        } catch (Exception $e) {
            throw new Exception("Ошибка генерации Excel: " . $e->getMessage());
        }
    }
    
    /**
     * Генерация HTML отчета
     * 
     * @param array $data Данные для отчета
     */
    private function generateHtmlReport(array $data): void
    {
        try {
            $template = $this->core->getTemplate();
            $html = $template->render('dashboard/report-html.php', $data);
            
            header('Content-Type: text/html; charset=utf-8');
            header('Content-Disposition: attachment; filename="report.html"');
            
            echo $html;
            
        } catch (Exception $e) {
            throw new Exception("Ошибка генерации HTML: " . $e->getMessage());
        }
    }
    
    /**
     * Отображение страницы ошибки
     * 
     * @param string $message Сообщение об ошибке
     */
    private function showError(string $message): void
    {
        $content = $this->core->getTemplate()->render('dashboard/error.php', [
            'message' => $message
        ]);
        echo $this->core->renderPage($content, 'Ошибка');
    }
}