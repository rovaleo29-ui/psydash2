<?php
/**
 * Конфигурация базы данных
 * 
 * @package Config
 */

// Чтение настроек из .env файла, если он существует
$envFile = ROOT_PATH . '/.env';
$envConfig = [];

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Пропускаем комментарии
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Разбор строк вида KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Удаление кавычек
            if (($value[0] === '"' && $value[strlen($value)-1] === '"') ||
                ($value[0] === "'" && $value[strlen($value)-1] === "'")) {
                $value = substr($value, 1, -1);
            }
            
            $envConfig[$key] = $value;
        }
    }
}

// Функция для получения значения из .env или возврата значения по умолчанию
$getEnv = function($key, $default = null) use ($envConfig) {
    return $envConfig[$key] ?? $default;
};

return [
    // Основные настройки подключения
    'driver' => 'mysql',
    'host' => $getEnv('DB_HOST', 'localhost'),
    'port' => $getEnv('DB_PORT', '3306'),
    'database' => $getEnv('DB_DATABASE', 'psychologist_system'),
    'username' => $getEnv('DB_USERNAME', 'root'),
    'password' => $getEnv('DB_PASSWORD', ''),
    
    // Настройки кодировки
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    
    // Настройки PDO
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        PDO::MYSQL_ATTR_SSL_CA => $getEnv('DB_SSL_CA', null),
        PDO::MYSQL_ATTR_SSL_CERT => $getEnv('DB_SSL_CERT', null),
        PDO::MYSQL_ATTR_SSL_KEY => $getEnv('DB_SSL_KEY', null),
    ],
    
    // Настройки соединения
    'connection' => [
        'timeout' => 10, // Таймаут подключения в секундах
        'retry_attempts' => 3, // Количество попыток переподключения
        'retry_delay' => 1, // Задержка между попытками в секундах
        'persistent' => false, // Постоянное соединение
    ],
    
    // Настройки репликации (для масштабирования)
    'replication' => [
        'enabled' => false,
        'write' => [
            'host' => $getEnv('DB_WRITE_HOST', $getEnv('DB_HOST', 'localhost')),
            'port' => $getEnv('DB_WRITE_PORT', $getEnv('DB_PORT', '3306')),
        ],
        'read' => [
            [
                'host' => $getEnv('DB_READ_HOST', $getEnv('DB_HOST', 'localhost')),
                'port' => $getEnv('DB_READ_PORT', $getEnv('DB_PORT', '3306')),
            ]
        ],
    ],
    
    // Настройки пула соединений (если используется)
    'pool' => [
        'enabled' => false,
        'max_connections' => 20,
        'min_connections' => 5,
        'max_idle_time' => 300, // Время простоя в секундах
    ],
    
    // Настройки миграций
    'migrations' => [
        'table' => 'migrations',
        'path' => ROOT_PATH . '/database/migrations',
    ],
    
    // Настройки сидов (тестовых данных)
    'seeds' => [
        'path' => ROOT_PATH . '/database/seeds',
        'run_on_install' => false,
    ],
    
    // Настройки резервного копирования БД
    'backup' => [
        'enabled' => true,
        'compress' => true,
        'include_data' => true,
        'include_structure' => true,
        'max_backups' => 30,
        'tables_to_ignore' => [
            'migrations',
            'sessions',
            'cache',
        ],
    ],
    
    // Настройки производительности
    'performance' => [
        'query_logging' => $getEnv('APP_DEBUG', false),
        'slow_query_threshold' => 1.0, // В секундах
        'enable_query_cache' => true,
        'max_allowed_packet' => '16M', // Максимальный размер пакета
    ],
    
    // Настройки транзакций
    'transactions' => [
        'default_isolation_level' => 'REPEATABLE READ',
        'retry_on_deadlock' => true,
        'max_retry_attempts' => 3,
    ],
    
    // Дополнительные настройки
    'prefix' => $getEnv('DB_PREFIX', ''), // Префикс таблиц
    'strict' => true, // Строгий режим MySQL
    'engine' => 'InnoDB', // Движок таблиц
    
    // Настройки для разработки
    'development' => [
        'seed_test_data' => true,
        'truncate_on_refresh' => false,
        'log_all_queries' => false,
    ],
    
    // Настройки для тестирования
    'testing' => [
        'database' => $getEnv('DB_TEST_DATABASE', 'psychologist_system_test'),
        'host' => $getEnv('DB_TEST_HOST', $getEnv('DB_HOST', 'localhost')),
        'username' => $getEnv('DB_TEST_USERNAME', $getEnv('DB_USERNAME', 'root')),
        'password' => $getEnv('DB_TEST_PASSWORD', $getEnv('DB_PASSWORD', '')),
    ],
    
    // SQL-режимы
    'sql_modes' => [
        'ONLY_FULL_GROUP_BY',
        'STRICT_TRANS_TABLES',
        'NO_ZERO_IN_DATE',
        'NO_ZERO_DATE',
        'ERROR_FOR_DIVISION_BY_ZERO',
        'NO_ENGINE_SUBSTITUTION',
    ],
    
    // Настройки временной зоны БД
    'timezone' => '+03:00', // Московское время
    
    // Настройки кэширования запросов
    'query_cache' => [
        'enabled' => true,
        'ttl' => 300, // Время жизни кэша в секундах
        'adapter' => 'file', // file, redis, memcached
    ],
];