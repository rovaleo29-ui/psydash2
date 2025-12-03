<?php
/**
 * Конфигурация приложения
 * 
 * @package Config
 */

return [
    // Основные настройки
    'app_name' => 'Система управления психологическими данными',
    'app_version' => '1.0.0',
    'app_description' => 'Система для школьных психологов',
    'app_author' => 'Школьная психологическая служба',
    'app_url' => 'https://psydash.ru', // Базовый URL приложения
    
    // Настройки окружения
    'environment' => getenv('APP_ENV') ?: 'production', // development, staging, production
    'debug' => getenv('APP_DEBUG') ?: false,
    'timezone' => 'Europe/Moscow',
    
    // Настройки сессии
    'session' => [
        'name' => 'psychologist_system',
        'lifetime' => 86400, // 24 часа в секундах
        'secure' => false, // Установить true при использовании HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ],
    
    // Настройки безопасности
    'security' => [
        'csrf_protection' => true,
        'password_min_length' => 8,
        'password_require_numbers' => true,
        'password_require_special_chars' => true,
        'login_attempts' => 5,
        'login_lockout_time' => 900, // 15 минут в секундах
    ],
    
    // Настройки загрузки файлов
    'uploads' => [
        'path' => ROOT_PATH . '/storage/uploads',
        'max_size' => 5 * 1024 * 1024, // 5MB
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],
        'image_max_width' => 1920,
        'image_max_height' => 1080,
    ],
    
    // Настройки логирования
    'logging' => [
        'enabled' => true,
        'path' => ROOT_PATH . '/storage/logs',
        'filename' => 'app.log',
        'level' => 'error', // debug, info, warning, error
        'max_files' => 30, // Количество дней хранения логов
    ],
    
    // Настройки кэширования
    'cache' => [
        'enabled' => true,
        'path' => ROOT_PATH . '/storage/cache',
        'lifetime' => 3600, // 1 час в секундах
    ],
    
    // Настройки резервного копирования
    'backup' => [
        'enabled' => true,
        'path' => ROOT_PATH . '/storage/backups',
        'keep_days' => 30,
        'time' => '03:00', // Время автоматического бэкапа
    ],
    
    // Настройки почты
    'mail' => [
        'enabled' => false,
        'host' => getenv('MAIL_HOST') ?: 'smtp.gmail.com',
        'port' => getenv('MAIL_PORT') ?: 587,
        'username' => getenv('MAIL_USERNAME') ?: '',
        'password' => getenv('MAIL_PASSWORD') ?: '',
        'encryption' => getenv('MAIL_ENCRYPTION') ?: 'tls',
        'from_address' => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@example.com',
        'from_name' => getenv('MAIL_FROM_NAME') ?: 'Система психолога',
    ],
    
    // Настройки модулей
    'modules' => [
        'path' => ROOT_PATH . '/modules',
        'auto_register' => true,
        'auto_install' => false,
        'check_dependencies' => true,
    ],
    
    // Настройки отчетов
    'reports' => [
        'default_format' => 'pdf', // pdf, excel, html
        'pdf_author' => 'Система психолога',
        'pdf_creator' => 'Система психолога',
        'excel_creator' => 'Система психолога',
    ],
    
    // Настройки пагинации
    'pagination' => [
        'per_page' => 20,
        'max_per_page' => 100,
        'visible_pages' => 5,
    ],
    
    // Настройки даты и времени
    'date_format' => 'd.m.Y',
    'datetime_format' => 'd.m.Y H:i',
    'time_format' => 'H:i',
    
    // Настройки классов
    'classes' => [
        'min_grade' => 1,
        'max_grade' => 11,
        'letters' => ['А', 'Б', 'В', 'Г', 'Д', 'Е', 'Ж', 'З', 'И', 'К'],
    ],
    
    // Настройки экспорта данных
    'export' => [
        'include_personal_data' => true,
        'anonymize_for_research' => false,
        'default_encoding' => 'UTF-8',
    ],
    
    // Настройки пользовательского интерфейса
    'ui' => [
        'theme' => 'light', // light, dark, auto
        'sidebar_collapsed' => false,
        'animations' => true,
        'high_contrast' => false,
        'font_size' => 'normal', // small, normal, large
    ],
    
    // Настройки интеграций (заглушки для будущих функций)
    'integrations' => [
        'sms_enabled' => false,
        'sms_provider' => '',
        'api_enabled' => false,
        'api_key' => '',
        'webhooks_enabled' => false,
    ],
    
    // Настройки производительности
    'performance' => [
        'gzip_compression' => true,
        'cache_headers' => true,
        'minify_html' => false,
        'minify_css' => true,
        'minify_js' => true,
    ],
    
    // Настройки для разработки
    'development' => [
        'show_errors' => true,
        'log_queries' => false,
        'profiler_enabled' => false,
        'debug_toolbar' => false,
    ],
    
    // Настройки обновлений
    'updates' => [
        'check_for_updates' => true,
        'auto_update' => false,
        'update_server' => '',
        'update_key' => '',
    ],
    
    // Дополнительные настройки (могут быть переопределены в .env)
    'features' => [
        'multi_psychologist' => false,
        'child_photos' => true,
        'test_comments' => true,
        'result_comparison' => true,
        'trend_analysis' => true,
        'automatic_backups' => true,
        'email_notifications' => false,
    ],
];
