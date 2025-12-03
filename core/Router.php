<?php
/**
 * Класс маршрутизатора системы
 * 
 * @package Core
 */

namespace Core;

use Exception;

class Router
{
    /**
     * @var array Массив зарегистрированных маршрутов
     */
    private $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
        'PATCH' => []
    ];
    
    /**
     * @var array Параметры текущего маршрута
     */
    private $params = [];
    
    /**
     * @var string Текущий URI
     */
    private $currentUri;
    
    /**
     * @var string Текущий HTTP метод
     */
    private $currentMethod;
    
    /**
     * @var Core Экземпляр ядра системы
     */
    private $core;
    
    /**
     * Конструктор класса
     */
    public function __construct()
    {
        $this->core = Core::getInstance();
        $this->currentMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->currentUri = $this->getCurrentUri();
        
        // Регистрация стандартных маршрутов
        $this->registerDefaultRoutes();
    }
    
    /**
     * Получение текущего URI
     * 
     * @return string
     */
    private function getCurrentUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Удаление query string
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }
        
        // Декодирование URL
        $uri = rawurldecode($uri);
        
        // Удаление начального и конечного слэшей
        $uri = trim($uri, '/');
        
        return $uri ?: '/';
    }
    
    /**
     * Регистрация маршрута для GET запроса
     * 
     * @param string $path Путь маршрута
     * @param mixed $handler Обработчик маршрута
     */
    public function get(string $path, $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }
    
    /**
     * Регистрация маршрута для POST запроса
     * 
     * @param string $path Путь маршрута
     * @param mixed $handler Обработчик маршрута
     */
    public function post(string $path, $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }
    
    /**
     * Регистрация маршрута для PUT запроса
     * 
     * @param string $path Путь маршрута
     * @param mixed $handler Обработчик маршрута
     */
    public function put(string $path, $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }
    
    /**
     * Регистрация маршрута для DELETE запроса
     * 
     * @param string $path Путь маршрута
     * @param mixed $handler Обработчик маршрута
     */
    public function delete(string $path, $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }
    
    /**
     * Регистрация маршрута для PATCH запроса
     * 
     * @param string $path Путь маршрута
     * @param mixed $handler Обработчик маршрута
     */
    public function patch(string $path, $handler): void
    {
        $this->addRoute('PATCH', $path, $handler);
    }
    
    /**
     * Добавление маршрута
     * 
     * @param string $method HTTP метод
     * @param string $path Путь маршрута
     * @param mixed $handler Обработчик маршрута
     */
    private function addRoute(string $method, string $path, $handler): void
    {
        // Нормализация пути
        $path = trim($path, '/');
        $path = $path ?: '/';
        
        // Преобразование параметров маршрута в регулярное выражение
        $pattern = $this->buildPattern($path);
        
        $this->routes[$method][$pattern] = [
            'path' => $path,
            'handler' => $handler,
            'pattern' => $pattern
        ];
    }
    
    /**
     * Преобразование пути с параметрами в регулярное выражение
     * 
     * @param string $path Путь маршрута
     * @return string
     */
    private function buildPattern(string $path): string
    {
        // Замена параметров {param} на регулярные выражения
        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $path);
        
        // Экранирование слэшей
        $pattern = str_replace('/', '\/', $pattern);
        
        // Добавление начала и конца
        return '/^' . $pattern . '$/';
    }
    
    /**
     * Обработка текущего запроса
     * 
     * @param string|null $uri URI для обработки (если не указан, берется текущий)
     */
    public function handle(?string $uri = null): void
    {
        if ($uri !== null) {
            $this->currentUri = trim($uri, '/');
            $this->currentUri = $this->currentUri ?: '/';
        }
        
        // Поиск соответствующего маршрута
        $route = $this->findRoute();
        
        if (!$route) {
            $this->handleNotFound();
            return;
        }
        
        // Выполнение обработчика маршрута
        $this->executeHandler($route['handler']);
    }
    
    /**
     * Поиск маршрута, соответствующего текущему URI
     * 
     * @return array|null
     */
    private function findRoute(): ?array
    {
        $method = $this->currentMethod;
        
        if (!isset($this->routes[$method])) {
            return null;
        }
        
        foreach ($this->routes[$method] as $pattern => $route) {
            if (preg_match($pattern, $this->currentUri, $matches)) {
                // Извлечение именованных параметров
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $this->params[$key] = $value;
                    }
                }
                
                return $route;
            }
        }
        
        return null;
    }
    
    /**
     * Выполнение обработчика маршрута
     * 
     * @param mixed $handler Обработчик маршрута
     */
    private function executeHandler($handler): void
    {
        try {
            if (is_callable($handler)) {
                // Если обработчик - функция
                call_user_func($handler, $this->params);
            } elseif (is_string($handler) && strpos($handler, '@') !== false) {
                // Если обработчик в формате "Controller@method"
                $this->callControllerMethod($handler);
            } elseif (is_array($handler) && count($handler) === 2) {
                // Если обработчик - массив [Controller, method]
                $this->callControllerMethod($handler[0] . '@' . $handler[1]);
            } else {
                throw new Exception("Неподдерживаемый формат обработчика маршрута");
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }
    
    /**
     * Вызов метода контроллера
     * 
     * @param string $handler Строка в формате "Controller@method"
     */
    private function callControllerMethod(string $handler): void
    {
        list($controllerName, $methodName) = explode('@', $handler);
        
        // Добавление пространства имен контроллеров
        $controllerClass = 'App\\Controllers\\' . $controllerName;
        
        if (!class_exists($controllerClass)) {
            throw new Exception("Контроллер не найден: {$controllerClass}");
        }
        
        // Создание экземпляра контроллера
        $controller = new $controllerClass($this->core);
        
        if (!method_exists($controller, $methodName)) {
            throw new Exception("Метод не найден: {$controllerClass}::{$methodName}");
        }
        
        // Вызов метода контроллера с параметрами
        call_user_func_array([$controller, $methodName], $this->params);
    }
    
    /**
     * Обработка ошибки 404 (маршрут не найден)
     */
    private function handleNotFound(): void
    {
        http_response_code(404);
        
        $template = $this->core->getTemplate();
        
        // Проверка существования кастомной страницы 404
        $errorPage = ROOT_PATH . '/templates/errors/404.php';
        
        if (file_exists($errorPage)) {
            echo $template->render('errors/404.php');
        } else {
            echo '<h1>404 - Страница не найдена</h1>';
            echo '<p>Запрашиваемая страница не существует.</p>';
        }
    }
    
    /**
     * Обработка ошибки при выполнении маршрута
     * 
     * @param Exception $e Исключение
     */
    private function handleError(Exception $e): void
    {
        error_log("Ошибка маршрутизатора: " . $e->getMessage());
        
        http_response_code(500);
        
        $template = $this->core->getTemplate();
        
        // Проверка существования кастомной страницы 500
        $errorPage = ROOT_PATH . '/templates/errors/500.php';
        
        if (file_exists($errorPage)) {
            echo $template->render('errors/500.php', ['error' => $e]);
        } else {
            echo '<h1>500 - Внутренняя ошибка сервера</h1>';
            
            if ($this->core->getConfig('debug')) {
                echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
                echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            } else {
                echo '<p>Произошла внутренняя ошибка сервера. Пожалуйста, попробуйте позже.</p>';
            }
        }
    }
    
    /**
     * Регистрация стандартных маршрутов системы
     */
    private function registerDefaultRoutes(): void
    {
        // Главная страница
        $this->get('/', function() {
            if ($this->core->isAuthenticated()) {
                $this->redirect('/dashboard');
            } else {
                $this->redirect('/login');
            }
        });
        
        // Аутентификация
        $this->get('/login', 'AuthController@showLogin');
        $this->post('/login', 'AuthController@login');
        $this->get('/logout', 'AuthController@logout');
        
        // Дашборд
        $this->get('/dashboard', 'DashboardController@index');
        
        // Дети
        $this->get('/children', 'ChildrenController@index');
        $this->get('/children/add', 'ChildrenController@showAddForm');
        $this->post('/children/add', 'ChildrenController@add');
        $this->get('/children/{id}', 'ChildrenController@show');
        $this->get('/children/{id}/edit', 'ChildrenController@showEditForm');
        $this->post('/children/{id}/edit', 'ChildrenController@edit');
        $this->post('/children/{id}/delete', 'ChildrenController@delete');
        
        // Тесты
        $this->get('/tests', 'TestsController@index');
        
        // Маршруты для модулей тестов (динамические)
        $this->registerModuleRoutes();
    }
    
    /**
     * Регистрация маршрутов для модулей тестов
     */
    private function registerModuleRoutes(): void
    {
        try {
            $modules = $this->core->getModuleLoader()->getActiveModules();
            
            foreach ($modules as $module) {
                $moduleKey = $module['module_key'];
                
                // Главная страница модуля
                $this->get("/tests/{$moduleKey}", function($params) use ($moduleKey) {
                    $this->callModuleMethod($moduleKey, 'index', [$params['module_key'] ?? $moduleKey]);
                });
                
                // Добавление результата теста
                $this->get("/tests/{$moduleKey}/add", function($params) use ($moduleKey) {
                    $this->callModuleMethod($moduleKey, 'showAddForm', [$params['module_key'] ?? $moduleKey]);
                });
                
                $this->post("/tests/{$moduleKey}/add", function($params) use ($moduleKey) {
                    $this->callModuleMethod($moduleKey, 'add', [$params['module_key'] ?? $moduleKey]);
                });
                
                // Просмотр результата теста
                $this->get("/tests/{$moduleKey}/{id}", function($params) use ($moduleKey) {
                    $this->callModuleMethod($moduleKey, 'show', [$params['module_key'] ?? $moduleKey, $params['id']]);
                });
                
                // Редактирование результата теста
                $this->get("/tests/{$moduleKey}/{id}/edit", function($params) use ($moduleKey) {
                    $this->callModuleMethod($moduleKey, 'showEditForm', [$params['module_key'] ?? $moduleKey, $params['id']]);
                });
                
                $this->post("/tests/{$moduleKey}/{id}/edit", function($params) use ($moduleKey) {
                    $this->callModuleMethod($moduleKey, 'edit', [$params['module_key'] ?? $moduleKey, $params['id']]);
                });
                
                // Удаление результата теста
                $this->post("/tests/{$moduleKey}/{id}/delete", function($params) use ($moduleKey) {
                    $this->callModuleMethod($moduleKey, 'delete', [$params['module_key'] ?? $moduleKey, $params['id']]);
                });
                
                // Экспорт результатов
                $this->get("/tests/{$moduleKey}/export", function($params) use ($moduleKey) {
                    $this->callModuleMethod($moduleKey, 'export', [$params['module_key'] ?? $moduleKey]);
                });
            }
            
        } catch (Exception $e) {
            // Игнорируем ошибки при регистрации маршрутов модулей
            error_log("Ошибка при регистрации маршрутов модулей: " . $e->getMessage());
        }
    }
    
    /**
     * Вызов метода модуля через маршрутизатор
     * 
     * @param string $moduleKey Ключ модуля
     * @param string $method Имя метода
     * @param array $params Параметры
     */
    private function callModuleMethod(string $moduleKey, string $method, array $params = []): void
    {
        $moduleLoader = $this->core->getModuleLoader();
        
        try {
            $module = $moduleLoader->getModule($moduleKey);
            
            if (!$module) {
                throw new Exception("Модуль {$moduleKey} не найден");
            }
            
            if (!method_exists($module, $method)) {
                throw new Exception("Метод {$method} не существует в модуле {$moduleKey}");
            }
            
            call_user_func_array([$module, $method], $params);
            
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }
    
    /**
     * Перенаправление на другой URL
     * 
     * @param string $url URL для перенаправления
     * @param int $statusCode HTTP статус код
     */
    public function redirect(string $url, int $statusCode = 302): void
    {
        // Добавление базового URL, если необходимо
        if (strpos($url, 'http') !== 0) {
            $url = $this->getBaseUrl() . ltrim($url, '/');
        }
        
        header("Location: {$url}", true, $statusCode);
        exit;
    }
    
    /**
     * Получение базового URL приложения
     * 
     * @return string
     */
    public function getBaseUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Определение базового пути, если приложение находится в подпапке
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = dirname($scriptName);
        
        if ($basePath === '/' || $basePath === '\\') {
            $basePath = '';
        }
        
        return $protocol . $host . $basePath;
    }
    
    /**
     * Получение параметров текущего маршрута
     * 
     * @param string|null $key Ключ параметра
     * @return mixed
     */
    public function getParams(?string $key = null)
    {
        if ($key === null) {
            return $this->params;
        }
        
        return $this->params[$key] ?? null;
    }
    
    /**
     * Получение текущего URI
     * 
     * @return string
     */
    public function getCurrentUri(): string
    {
        return $this->currentUri;
    }
    
    /**
     * Получение текущего HTTP метода
     * 
     * @return string
     */
    public function getCurrentMethod(): string
    {
        return $this->currentMethod;
    }
    
    /**
     * Проверка, является ли текущий запрос AJAX запросом
     * 
     * @return bool
     */
    public function isAjax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Отправка JSON ответа
     * 
     * @param mixed $data Данные для отправки
     * @param int $statusCode HTTP статус код
     */
    public function json($data, int $statusCode = 200): void
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Генерация URL для маршрута
     * 
     * @param string $routeName Имя маршрута
     * @param array $params Параметры для подстановки
     * @return string
     */
    public function generateUrl(string $routeName, array $params = []): string
    {
        // Поиск маршрута по имени (упрощенная реализация)
        $routeMap = [
            'dashboard' => '/dashboard',
            'login' => '/login',
            'logout' => '/logout',
            'children' => '/children',
            'children.add' => '/children/add',
            'children.view' => '/children/{id}',
            'children.edit' => '/children/{id}/edit',
            'tests' => '/tests',
            'tests.module' => '/tests/{module_key}',
            'tests.add' => '/tests/{module_key}/add',
            'tests.view' => '/tests/{module_key}/{id}',
            'tests.edit' => '/tests/{module_key}/{id}/edit',
            'reports' => '/reports',
            'settings' => '/settings',
        ];
        
        $path = $routeMap[$routeName] ?? '/';
        
        // Подстановка параметров
        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', urlencode($value), $path);
        }
        
        return $this->getBaseUrl() . $path;
    }
}