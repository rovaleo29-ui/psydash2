<?php
/**
 * Класс шаблонизатора системы
 * 
 * @package Core
 */

namespace Core;

use Exception;

class Template
{
    /**
     * @var string Путь к директории шаблонов
     */
    private $templatesPath;
    
    /**
     * @var array Глобальные переменные для всех шаблонов
     */
    private $globals = [];
    
    /**
     * @var array Кэш загруженных шаблонов
     */
    private $cache = [];
    
    /**
     * @var array Стек родительских шаблонов
     */
    private $parentStack = [];
    
    /**
     * @var array Секции текущего шаблона
     */
    private $sections = [];
    
    /**
     * @var string Текущая секция
     */
    private $currentSection = '';
    
    /**
     * Конструктор класса
     * 
     * @param string $templatesPath Путь к директории шаблонов
     */
    public function __construct(string $templatesPath)
    {
        $this->templatesPath = rtrim($templatesPath, '/') . '/';
        
        if (!is_dir($this->templatesPath)) {
            throw new Exception("Директория шаблонов не найдена: {$templatesPath}");
        }
    }
    
    /**
     * Рендеринг шаблона
     * 
     * @param string $template Имя файла шаблона (относительно templatesPath)
     * @param array $data Данные для передачи в шаблон
     * @return string
     */
    public function render(string $template, array $data = []): string
    {
        // Объединение глобальных данных и переданных данных
        $allData = array_merge($this->globals, $data);
        
        // Извлечение переменных в локальную область видимости
        extract($allData, EXTR_SKIP);
        
        // Начало буферизации вывода
        ob_start();
        
        try {
            // Включение файла шаблона
            $templateFile = $this->templatesPath . $template;
            
            if (!file_exists($templateFile)) {
                throw new Exception("Шаблон не найден: {$template}");
            }
            
            // Подключение файла шаблона
            require $templateFile;
            
        } catch (Exception $e) {
            ob_end_clean();
            throw $e;
        }
        
        // Получение содержимого буфера
        $content = ob_get_clean();
        
        // Обработка родительских шаблонов
        if (!empty($this->parentStack)) {
            $parentTemplate = array_pop($this->parentStack);
            $this->sections['content'] = $content;
            $content = $this->render($parentTemplate, $allData);
            
            // Очистка секций после рендеринга
            $this->sections = [];
            $this->currentSection = '';
        }
        
        return $content;
    }
    
    /**
     * Расширение родительского шаблона
     * 
     * @param string $parentTemplate Имя родительского шаблона
     */
    public function extend(string $parentTemplate): void
    {
        $this->parentStack[] = $parentTemplate;
    }
    
    /**
     * Начало секции
     * 
     * @param string $name Имя секции
     */
    public function section(string $name): void
    {
        $this->currentSection = $name;
        ob_start();
    }
    
    /**
     * Завершение секции
     */
    public function endSection(): void
    {
        if (empty($this->currentSection)) {
            throw new Exception("Нельзя завершить секцию без её начала");
        }
        
        $this->sections[$this->currentSection] = ob_get_clean();
        $this->currentSection = '';
    }
    
    /**
     * Вывод содержимого секции
     * 
     * @param string $name Имя секции
     * @param string $default Значение по умолчанию
     */
    public function yield(string $name, string $default = ''): void
    {
        echo $this->sections[$name] ?? $default;
    }
    
    /**
     * Рендеринг частичного шаблона
     * 
     * @param string $partial Имя частичного шаблона
     * @param array $data Данные для передачи в шаблон
     */
    public function partial(string $partial, array $data = []): void
    {
        echo $this->render($partial, $data);
    }
    
    /**
     * Экранирование строки для HTML
     * 
     * @param string $string Строка для экранирования
     * @return string
     */
    public function escape(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    
    /**
     * Форматирование даты
     * 
     * @param string $date Дата в формате YYYY-MM-DD
     * @param string $format Формат вывода
     * @return string
     */
    public function formatDate(string $date, string $format = 'd.m.Y'): string
    {
        if (empty($date)) {
            return '';
        }
        
        $timestamp = strtotime($date);
        
        if ($timestamp === false) {
            return $date;
        }
        
        return date($format, $timestamp);
    }
    
    /**
     * Форматирование класса
     * 
     * @param string $class Название класса (например, "5A")
     * @return string
     */
    public function formatClass(string $class): string
    {
        return $this->escape($class) . ' класс';
    }
    
    /**
     * Форматирование возраста
     * 
     * @param string $birthDate Дата рождения
     * @return string
     */
    public function formatAge(string $birthDate): string
    {
        if (empty($birthDate)) {
            return 'не указано';
        }
        
        $birthTimestamp = strtotime($birthDate);
        $currentTimestamp = time();
        
        if ($birthTimestamp === false || $birthTimestamp > $currentTimestamp) {
            return 'некорректная дата';
        }
        
        $age = date('Y', $currentTimestamp) - date('Y', $birthTimestamp);
        
        // Корректировка, если день рождения еще не наступил в этом году
        if (date('md', $currentTimestamp) < date('md', $birthTimestamp)) {
            $age--;
        }
        
        $lastDigit = $age % 10;
        $lastTwoDigits = $age % 100;
        
        if ($lastTwoDigits >= 11 && $lastTwoDigits <= 19) {
            $word = 'лет';
        } elseif ($lastDigit == 1) {
            $word = 'год';
        } elseif ($lastDigit >= 2 && $lastDigit <= 4) {
            $word = 'года';
        } else {
            $word = 'лет';
        }
        
        return $age . ' ' . $word;
    }
    
    /**
     * Форматирование числа
     * 
     * @param mixed $number Число
     * @param int $decimals Количество знаков после запятой
     * @return string
     */
    public function formatNumber($number, int $decimals = 0): string
    {
        return number_format((float)$number, $decimals, ',', ' ');
    }
    
    /**
     * Генерация CSRF токена
     * 
     * @return string
     */
    public function csrfToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Генерация CSRF поля для формы
     */
    public function csrfField(): void
    {
        echo '<input type="hidden" name="csrf_token" value="' . $this->csrfToken() . '">';
    }
    
    /**
     * Проверка CSRF токена
     * 
     * @param string $token Токен для проверки
     * @return bool
     */
    public function verifyCsrfToken(string $token): bool
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Добавление глобальной переменной
     * 
     * @param string $key Ключ
     * @param mixed $value Значение
     */
    public function addGlobal(string $key, $value): void
    {
        $this->globals[$key] = $value;
    }
    
    /**
     * Получение пути к ассетам
     * 
     * @param string $path Путь к ассету
     * @return string
     */
    public function asset(string $path): string
    {
        // Удаление начального слеша, если есть
        $path = ltrim($path, '/');
        
        // Добавление версии для инвалидации кэша
        $version = $this->globals['app_version'] ?? '1.0.0';
        
        return '/assets/' . $path . '?v=' . $version;
    }
    
    /**
     * Получение URL маршрута
     * 
     * @param string $route Имя маршрута или путь
     * @param array $params Параметры для подстановки
     * @return string
     */
    public function url(string $route, array $params = []): string
    {
        // Если передан прямой путь
        if (strpos($route, '/') === 0) {
            $url = $route;
        } else {
            // Поиск маршрута по имени (упрощенная версия)
            $url = $this->getRouteUrl($route);
        }
        
        // Подстановка параметров в URL
        foreach ($params as $key => $value) {
            $url = str_replace('{' . $key . '}', urlencode($value), $url);
        }
        
        return $url;
    }
    
    /**
     * Получение URL по имени маршрута (упрощенная реализация)
     * 
     * @param string $routeName Имя маршрута
     * @return string
     */
    private function getRouteUrl(string $routeName): string
    {
        $routes = [
            'dashboard' => '/dashboard',
            'login' => '/login',
            'logout' => '/logout',
            'children' => '/children',
            'children.add' => '/children/add',
            'children.view' => '/children/{id}',
            'tests' => '/tests',
            'tests.module' => '/tests/{module_key}',
            'tests.add' => '/tests/{module_key}/add',
            'tests.view' => '/tests/{module_key}/{id}',
            'reports' => '/reports',
            'settings' => '/settings',
        ];
        
        return $routes[$routeName] ?? '/';
    }
    
    /**
     * Проверка активного маршрута
     * 
     * @param string $route Маршрут для проверки
     * @return bool
     */
    public function isActiveRoute(string $route): bool
    {
        $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $routePath = $this->url($route);
        
        return $currentPath === $routePath;
    }
    
    /**
     * Вывод сообщения об ошибке
     * 
     * @param string $message Сообщение
     * @param string $type Тип сообщения (error, success, warning, info)
     */
    public function alert(string $message, string $type = 'info'): void
    {
        $cssClasses = [
            'error' => 'bg-red-100 border-red-400 text-red-700',
            'success' => 'bg-green-100 border-green-400 text-green-700',
            'warning' => 'bg-yellow-100 border-yellow-400 text-yellow-700',
            'info' => 'bg-blue-100 border-blue-400 text-blue-700'
        ];
        
        $class = $cssClasses[$type] ?? $cssClasses['info'];
        $icon = $type;
        
        echo '<div class="border-l-4 p-4 mb-4 ' . $class . '" role="alert">';
        echo '<div class="flex items-center">';
        echo '<div class="flex-shrink-0">';
        
        // Иконки для разных типов сообщений
        switch ($type) {
            case 'error':
                echo '<svg class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">';
                echo '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>';
                echo '</svg>';
                break;
            case 'success':
                echo '<svg class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">';
                echo '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>';
                echo '</svg>';
                break;
            default:
                echo '<svg class="h-5 w-5 text-blue-500" fill="currentColor" viewBox="0 0 20 20">';
                echo '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>';
                echo '</svg>';
        }
        
        echo '</div>';
        echo '<div class="ml-3">';
        echo '<p class="text-sm">' . $this->escape($message) . '</p>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Вывод ошибок валидации
     * 
     * @param array $errors Массив ошибок
     */
    public function validationErrors(array $errors): void
    {
        if (empty($errors)) {
            return;
        }
        
        echo '<div class="mb-4 p-4 bg-red-50 border border-red-200 rounded">';
        echo '<h4 class="text-red-800 font-medium mb-2">Пожалуйста, исправьте следующие ошибки:</h4>';
        echo '<ul class="list-disc list-inside text-red-600">';
        
        foreach ($errors as $field => $fieldErrors) {
            foreach ($fieldErrors as $error) {
                echo '<li>' . $this->escape($error) . '</li>';
            }
        }
        
        echo '</ul>';
        echo '</div>';
    }
    
    /**
     * Отладочный вывод переменной
     * 
     * @param mixed $var Переменная для отладки
     * @param bool $return Возвращать вместо вывода
     * @return string|null
     */
    public function dump($var, bool $return = false): ?string
    {
        $output = '<pre class="bg-gray-100 p-4 rounded text-sm overflow-auto">' . 
                  htmlspecialchars(print_r($var, true), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . 
                  '</pre>';
        
        if ($return) {
            return $output;
        }
        
        echo $output;
        return null;
    }
}