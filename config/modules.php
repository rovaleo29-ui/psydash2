<?php
/**
 * Конфигурация модулей системы
 * 
 * @package Config
 */

return [
    // Основные настройки модулей
    'enabled' => true,
    'auto_discovery' => true,
    'cache_discovery' => true,
    'cache_ttl' => 3600, // Время жизни кэша в секундах
    
    // Пути к модулям
    'paths' => [
        ROOT_PATH . '/modules',
        // Дополнительные пути можно добавить здесь
        // ROOT_PATH . '/custom-modules',
    ],
    
    // Настройки установки модулей
    'install' => [
        'check_dependencies' => true,
        'auto_migrate' => true,
        'create_backup' => true,
        'timeout' => 300, // Таймаут установки в секундах
    ],
    
    // Настройки обновления модулей
    'update' => [
        'check_for_updates' => true,
        'auto_update' => false,
        'update_backup' => true,
        'update_notifications' => true,
    ],
    
    // Настройки удаления модулей
    'uninstall' => [
        'remove_data' => false, // Удалять ли данные модуля
        'keep_backup' => true,
        'backup_retention_days' => 30,
    ],
    
    // Настройки безопасности модулей
    'security' => [
        'validate_signatures' => false, // Проверка цифровых подписей модулей
        'allowed_hosts' => [], // Разрешенные хосты для загрузки модулей
        'max_upload_size' => 10 * 1024 * 1024, // 10MB
        'scan_for_malware' => false,
    ],
    
    // Настройки зависимостей
    'dependencies' => [
        'core' => '1.0.0',
        'php' => '7.0.0',
        'extensions' => [
            'pdo',
            'pdo_mysql',
            'json',
            'mbstring',
            // 'gd', // для работы с изображениями
            // 'zip', // для архивирования
        ],
    ],
    
    // Категории модулей
    'categories' => [
        'эмоциональная сфера' => [
            'name' => 'Эмоциональная сфера',
            'description' => 'Тесты для диагностики эмоционального состояния',
            'color' => '#3b82f6',
            'icon' => 'heart',
        ],
        'познавательная сфера' => [
            'name' => 'Познавательная сфера',
            'description' => 'Тесты для оценки познавательных способностей',
            'color' => '#10b981',
            'icon' => 'brain',
        ],
        'личностные особенности' => [
            'name' => 'Личностные особенности',
            'description' => 'Тесты для диагностики личностных характеристик',
            'color' => '#8b5cf6',
            'icon' => 'user',
        ],
        'межличностные отношения' => [
            'name' => 'Межличностные отношения',
            'description' => 'Тесты для оценки социальных отношений',
            'color' => '#f59e0b',
            'icon' => 'users',
        ],
        'профориентация' => [
            'name' => 'Профориентация',
            'description' => 'Тесты для профессиональной ориентации',
            'color' => '#ef4444',
            'icon' => 'briefcase',
        ],
        'общий' => [
            'name' => 'Общие тесты',
            'description' => 'Общие психологические методики',
            'color' => '#6b7280',
            'icon' => 'clipboard-list',
        ],
    ],
    
    // Стандартные поля, которые должны быть в каждом модуле
    'required_module_fields' => [
        'module_key' => [
            'type' => 'string',
            'required' => true,
            'pattern' => '/^[a-z][a-z0-9_]*$/',
            'description' => 'Уникальный ключ модуля (только строчные буквы, цифры и подчеркивания)',
        ],
        'name' => [
            'type' => 'string',
            'required' => true,
            'min_length' => 3,
            'max_length' => 100,
            'description' => 'Название модуля',
        ],
        'description' => [
            'type' => 'string',
            'required' => true,
            'min_length' => 10,
            'max_length' => 500,
            'description' => 'Описание модуля',
        ],
        'version' => [
            'type' => 'string',
            'required' => true,
            'pattern' => '/^\d+\.\d+\.\d+$/',
            'description' => 'Версия модуля в формате X.Y.Z',
        ],
        'author' => [
            'type' => 'string',
            'required' => true,
            'description' => 'Автор модуля',
        ],
        'category' => [
            'type' => 'string',
            'required' => true,
            'enum' => array_keys($this->get('categories', [])),
            'description' => 'Категория модуля',
        ],
    ],
    
    // Стандартная структура таблицы результатов теста
    'required_table_fields' => [
        'child_id' => [
            'type' => 'INT',
            'constraints' => 'NOT NULL',
            'description' => 'ID ребенка',
        ],
        'psychologist_id' => [
            'type' => 'INT',
            'constraints' => 'NOT NULL',
            'description' => 'ID психолога',
        ],
        'test_date' => [
            'type' => 'DATE',
            'constraints' => 'NOT NULL',
            'description' => 'Дата проведения теста',
        ],
        'created_at' => [
            'type' => 'TIMESTAMP',
            'constraints' => 'DEFAULT CURRENT_TIMESTAMP',
            'description' => 'Дата создания записи',
        ],
    ],
    
    // Настройки интерфейса модулей
    'ui' => [
        'show_category_filter' => true,
        'show_search' => true,
        'show_stats' => true,
        'items_per_page' => 12,
        'default_view' => 'grid', // grid, list
    ],
    
    // Настройки API для модулей
    'api' => [
        'enabled' => false,
        'prefix' => '/api/modules',
        'version' => 'v1',
        'rate_limit' => 100, // Запросов в час
    ],
    
    // Настройки веб-хуков
    'webhooks' => [
        'enabled' => false,
        'events' => [
            'module.installed',
            'module.updated',
            'module.uninstalled',
            'module.enabled',
            'module.disabled',
        ],
    ],
    
    // Настройки уведомлений
    'notifications' => [
        'module_updates' => true,
        'security_alerts' => true,
        'dependency_warnings' => true,
    ],
    
    // Настройки кэширования модулей
    'caching' => [
        'config_cache' => true,
        'class_cache' => true,
        'template_cache' => true,
        'cache_driver' => 'file', // file, redis, memcached
    ],
    
    // Настройки для разработчиков модулей
    'development' => [
        'debug_mode' => false,
        'log_module_errors' => true,
        'allow_hot_reload' => false,
        'template_debug' => false,
    ],
    
    // Настройки совместимости
    'compatibility' => [
        'check_core_version' => true,
        'check_php_version' => true,
        'check_extension_dependencies' => true,
        'warn_on_deprecated' => true,
    ],
    
    // Черный список модулей (не будут загружаться)
    'blacklist' => [
        // Пример: 'malicious_module',
        // 'another_bad_module',
    ],
    
    // Рекомендуемые модули (будут показаны в интерфейсе)
    'recommended' => [
        'anxiety_spielberger' => [
            'reason' => 'Стандартный тест для диагностики тревожности',
            'priority' => 'high',
        ],
        'sociometry' => [
            'reason' => 'Популярная методика для оценки социальных отношений',
            'priority' => 'medium',
        ],
    ],
    
    // Настройки лицензирования (заглушка для будущей реализации)
    'licensing' => [
        'enabled' => false,
        'license_server' => '',
        'check_license' => false,
        'trial_days' => 30,
    ],
    
    // Статистика использования модулей
    'statistics' => [
        'track_usage' => true,
        'collect_anonymous_data' => false,
        'usage_report_frequency' => 'weekly', // daily, weekly, monthly
    ],
    
    // Резервное копирование данных модулей
    'module_backup' => [
        'enabled' => true,
        'frequency' => 'daily', // hourly, daily, weekly
        'keep_backups' => 7,
        'compress' => true,
    ],
];