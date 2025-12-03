<?php
/**
 * Главный входной файл системы
 * 
 * @package PsychologistSystem
 */

// Настройки ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Определение корневой директории
define('ROOT_PATH', __DIR__);
define('APP_ENV', getenv('APP_ENV') ?: 'production');

// Загрузка автозагрузчика Composer
//require_once ROOT_PATH . '/vendor/autoload.php';

// Загрузка конфигурации приложения
$config = require_once ROOT_PATH . '/config/app.php';

// Установка часового пояса
date_default_timezone_set($config['timezone']);

// Настройка логирования
if (APP_ENV === 'production') {
    ini_set('error_log', ROOT_PATH . '/storage/logs/app.log');
}

// Запуск сессии (только если не запущена)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400 * 30, // 30 дней
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Инициализация приложения
try {
    // Загрузка ядра
    require_once ROOT_PATH . '/core/Core.php';
    
    // Получение экземпляра ядра
    $core = Core::getInstance();
    
    // Обработка запроса через маршрутизатор
    $router = new Router();
    $router->handle($_SERVER['REQUEST_URI']);
    
} catch (Throwable $e) {
    // Логирование ошибки
    error_log('[' . date('Y-m-d H:i:s') . '] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    
    // Показ ошибки (в зависимости от окружения)
    if (APP_ENV === 'development') {
        echo '<h1>Ошибка</h1>';
        echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    } else {
        // В продакшене показываем общую страницу ошибки
        header('HTTP/1.1 500 Internal Server Error');
        if (file_exists(ROOT_PATH . '/templates/errors/500.php')) {
            include ROOT_PATH . '/templates/errors/500.php';
        } else {
            echo '<h1>Внутренняя ошибка сервера</h1>';
            echo '<p>Пожалуйста, попробуйте позже.</p>';
        }
    }
    exit;
}