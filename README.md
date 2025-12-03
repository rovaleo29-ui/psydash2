ТЕХНИЧЕСКОЕ ЗАДАНИЕ
СИСТЕМА УПРАВЛЕНИЯ ПСИХОЛОГИЧЕСКИМИ ДАННЫМИ
________________________________________
1. АРХИТЕКТУРА СИСТЕМЫ
1.1. Основные принципы
1.	Чистый PHP 7.0+ без фреймворков
2.	Модульность - каждый тест в отдельной папке со своей таблицей
3.	Изоляция данных - психолог видит только своих детей
4.	Единый шаблон - все модули наследуют общий дизайн
5.	Минимализм - современный, чистый интерфейс
1.2. Логика работы тестов
1.	Психолог заводит детей в общую базу
2.	При работе с тестом система предоставляет доступ к детям психолога
3.	Каждый тест создает СВОЮ таблицу для хранения результатов
4.	Результаты вносятся психологом вручную через формы теста
5.	Данные сохраняются в таблицу теста + привязываются к ребенку
________________________________________
2. СТРУКТУРА ПРОЕКТА
public_html/
├── .htaccess
├── index.php
├── assets/
│   ├── css/
│   │   ├── main.css
│   │   └── tailwind.min.css
│   ├── js/
│   │   ├── app.js
│   │   ├── jquery.min.js
│   │   ├── chart.min.js
│   │   └── flatpickr.min.js
│   ├── fonts/
│   │   ├── fontawesome/
│   │   └── inter/
│   └── images/
│       ├── logo.png
│       ├── favicon.ico
│       └── avatar-placeholder.png
├── core/
│   ├── Core.php
│   ├── Database.php
│   ├── Auth.php
│   ├── Template.php
│   ├── ModuleLoader.php
│   └── Router.php
├── modules/
│   ├── anxiety_spielberger/
│   │   ├── module.json
│   │   ├── Test.php
│   │   ├── install.sql
│   │   └── views/
│   │       ├── main.php
│   │       ├── add.php
│   │       └── view.php
│   └── sociometry/
│       ├── module.json
│       ├── Test.php
│       ├── install.sql
│       └── views/
│           ├── main.php
│           ├── add.php
│           └── view.php
├── app/
│   ├── controllers/
│   │   ├── DashboardController.php
│   │   ├── ChildrenController.php
│   │   ├── TestsController.php
│   │   └── AuthController.php
│   └── models/
│       ├── User.php
│       ├── Child.php
│       └── TestResult.php
├── templates/
│   ├── layout.php
│   ├── auth/
│   │   └── login.php
│   ├── dashboard/
│   │   └── index.php
│   ├── children/
│   │   ├── list.php
│   │   └── add.php
│   ├── tests/
│   │   └── list.php
│   └── errors/
│       ├── 404.php
│       └── 500.php
├── config/
│   ├── app.php
│   ├── database.php
│   └── modules.php
├── storage/
│   ├── logs/
│   │   └── app.log
│   ├── cache/
│   ├── uploads/
│   └── backups/
├── vendor/
│   ├── autoload.php
│   ├── tcpdf/
│   ├── phpoffice/
│   ├── monolog/
│   └── vlucas/
├── install/
│   ├── install.php
│   └── database.sql
├── scripts/
│   ├── backup.php
│   └── maintenance.php
└── .env________________________________________
3. СТРУКТУРА БАЗЫ ДАННЫХ
3.1. Общие таблицы (фиксированные)
sql
-- 1. Пользователи (психологи)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    psychologist_id INT NOT NULL UNIQUE, -- Внешний ID психолога
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_psychologist (psychologist_id)
);

-- 2. Дети (общая таблица для всех психологов)
CREATE TABLE children (
    id INT PRIMARY KEY AUTO_INCREMENT,
    psychologist_id INT NOT NULL, -- Привязка к психологу
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    class VARCHAR(10) NOT NULL, -- Формат: "5A", "10Б"
    birth_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (psychologist_id) REFERENCES users(psychologist_id) ON DELETE CASCADE,
    INDEX idx_psychologist (psychologist_id),
    INDEX idx_class (class),
    INDEX idx_name (last_name, first_name)
);

-- 3. Зарегистрированные модули тестов
CREATE TABLE test_modules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    module_key VARCHAR(50) UNIQUE NOT NULL, -- Имя папки модуля
    name VARCHAR(100) NOT NULL,
    description TEXT,
    version VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
);
3.2. Таблицы модулей тестов (создаются каждым модулем)
Каждый модуль создает свою таблицу по шаблону:
sql
-- Базовые поля, которые должны быть в КАЖДОЙ таблице результатов:
-- 1. child_id (INT) - ссылка на ребенка
-- 2. psychologist_id (INT) - кто добавил результат
-- 3. test_date (DATE/DATETIME) - дата тестирования

-- Пример таблицы для теста тревожности:
CREATE TABLE test_anxiety_spielberger_results (
    id INT PRIMARY KEY AUTO_INCREMENT,
    child_id INT NOT NULL,
    psychologist_id INT NOT NULL,
    test_date DATE NOT NULL,
    
    -- Специфичные поля теста:
    situational_score INT,
    personal_score INT,
    q1 INT, q2 INT, q3 INT, -- и т.д. для 40 вопросов
    
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE,
    INDEX idx_child (child_id),
    INDEX idx_date (test_date)
);
________________________________________
4. АРХИТЕКТУРА МОДУЛЕЙ
4.1. Структура модуля теста
text
modules/anxiety_spielberger/
├── module.json          # КОНФИГУРАЦИЯ модуля (обязательно)
├── Test.php             # КЛАСС модуля (обязательно)
├── install.sql          # SQL для таблицы (опционально)
└── views/               # ШАБЛОНЫ модуля (опционально)
    ├── main.php
    ├── add.php
    └── view.php
4.2. Конфигурация модуля (module.json)
json
{
    "module_key": "anxiety_spielberger",
    "name": "Тест тревожности Спилбергера",
    "description": "Диагностика личностной и ситуативной тревожности",
    "version": "1.0.0",
    "author": "Автор",
    "category": "эмоциональная сфера",
    
    "required_fields": ["child_id", "test_date"],
    
    "database": {
        "table_name": "test_anxiety_spielberger_results",
        "create_sql": "install.sql"
    },
    
    "dependencies": {
        "core": "1.0",
        "php": "7.0"
    }
}
4.3. Базовый класс модуля (Test.php - минимальная структура)
php
<?php
namespace Modules\AnxietySpielberger;

class Test {
    protected $db;          // Объект Database из ядра
    protected $template;    // Объект Template из ядра
    protected $config;      // Конфигурация из module.json
    
    public function __construct($db, $template, $config) {
        $this->db = $db;
        $this->template = $template;
        $this->config = $config;
    }
    
    // ДОСТУП К ОБЩИМ ФУНКЦИЯМ ЯДРА:
    // 1. Получить детей психолога
    // 2. Использовать общий шаблон
    // 3. Доступ к БД через $this->db
    
    public function getName() {
        return $this->config['name'];
    }
    
    public function getKey() {
        return $this->config['module_key'];
    }
}
4.4. Что доступно модулю из ядра:
1.	$this->db - объект Database для запросов
2.	$this->template - объект Template для рендеринга
3.	Методы для получения детей:
php
$children = $this->db->getChildren($psychologistId, ['class' => '5A']);
4.	Доступ к сессии для получения psychologist_id
5.	Общие CSS/JS библиотеки
________________________________________
5. КЛАСС ЯДРА (CORE)
5.1. Основные методы ядра
php
class Core {
    // СИНГЛТОН
    public static function getInstance();
    
    // БАЗА ДАННЫХ
    public function getDB();
    
    // ШАБЛОНИЗАЦИЯ
    public function getTemplate();
    public function renderPage($content, $title = '');
    
    // РАБОТА С ДЕТЬМИ
    public function getChildren($psychologistId, $filters = []);
    public function getChild($id, $psychologistId);
    public function addChild($data, $psychologistId);
    
    // РАБОТА С МОДУЛЯМИ
    public function getModule($moduleKey);
    public function getActiveModules();
    public function installModule($moduleKey);
    
    // АВТОРИЗАЦИЯ
    public function login($username, $password);
    public function logout();
    public function getCurrentPsychologist();
}
5.2. Класс Database (PDO обертка)
php
class Database {
    private $pdo;
    
    public function __construct($host, $dbname, $user, $pass);
    
    // ОСНОВНЫЕ МЕТОДЫ
    public function query($sql, $params = []);
    public function fetch($sql, $params = []);
    public function fetchAll($sql, $params = []);
    public function insert($table, $data);
    public function update($table, $data, $where);
    public function delete($table, $where);
    
    // СПЕЦИАЛЬНЫЕ МЕТОДЫ ДЛЯ СИСТЕМЫ
    public function getChildren($psychologistId, $filters = []);
    public function tableExists($tableName);
    public function executeFile($sqlFile);
}
5.3. Класс Template (шаблонизатор)
php
class Template {
    // РЕНДЕРИНГ В ОБЩИЙ ШАБЛОН
    public function render($template, $data = []);
    
    // ЧАСТИЧНЫЕ ШАБЛОНЫ
    public function partial($partial, $data = []);
    
    // ЭКРАНИРОВАНИЕ
    public function escape($string);
    
    // ФОРМАТИРОВАНИЕ
    public function formatDate($date, $format = 'd.m.Y');
    public function formatClass($class);
}
________________________________________
6. ИСПОЛЬЗУЕМЫЕ БИБЛИОТЕКИ И СТИЛИ
6.1. CSS Фреймворки и библиотеки
Библиотека	Версия	Назначение
Tailwind CSS	3.0+	Основной CSS фреймворк (минимизированная версия)
Font Awesome	6.0+	Иконки
Google Fonts	-	Шрифты (Inter, Roboto)
Chart.js	4.0+	Графики и диаграммы (для статистики)
Flatpickr	4.6+	Календарь для выбора дат
6.2. JavaScript библиотеки
Библиотека	Версия	Назначение
jQuery	3.6.0	Основная JS библиотека
Select2	4.0+	Улучшенные select-элементы
DataTables	1.13+	Таблицы с сортировкой и поиском
jsPDF	2.5+	Генерация PDF на клиенте
html2canvas	1.4+	Создание скриншотов для PDF
6.3. PHP библиотеки (через Composer)
Библиотека	Версия	Назначение
TCPDF	6.6+	Генерация PDF на сервере
PhpSpreadsheet	1.28+	Работа с Excel (экспорт/импорт)
Monolog	2.0+	Логирование
Valitron	1.4+	Валидация данных
PHPMailer	6.8+	Отправка email
6.4. Стилевая концепция (Tailwind CSS)
css
/* Основные цвета */
:root {
  --primary: #3b82f6;    /* Синий */
  --secondary: #10b981;  /* Зеленый */
  --accent: #8b5cf6;     /* Фиолетовый */
  --danger: #ef4444;     /* Красный */
  --warning: #f59e0b;    /* Желтый */
}

/* Компоненты */
.btn-primary { @apply bg-blue-600 text-white hover:bg-blue-700; }
.btn-secondary { @apply bg-green-600 text-white hover:bg-green-700; }
.card { @apply bg-white rounded-lg shadow-md p-6; }
.input { @apply border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500; }
6.5. Основные стили (templates/css/main.css)
css
/* Основные стили системы */
body {
  font-family: 'Inter', sans-serif;
  background-color: #f9fafb;
}

/* Навигация */
.sidebar {
  width: 250px;
  background: white;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

/* Карточки */
.card {
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
  transition: box-shadow 0.2s;
}
.card:hover {
  box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

/* Таблицы */
.table-striped tbody tr:nth-child(odd) {
  background-color: #f9fafb;
}

/* Формы */
.form-group {
  margin-bottom: 1rem;
}
.form-label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
}

/* Утилиты */
.text-truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Адаптивность */
@media (max-width: 768px) {
  .sidebar {
    width: 100%;
    position: static;
  }
  .table-responsive {
    overflow-x: auto;
  }
}
________________________________________
7. МАРШРУТИЗАЦИЯ
7.1. Структура URL
text
/                          → Главная (редирект на дашборд или логин)
/login                    → Страница входа
/dashboard                → Дашборд психолога
/children                 → Список детей
/children/add             → Добавление ребенка
/children/{id}            → Просмотр ребенка
/tests                    → Список доступных тестов
/tests/{module_key}       → Главная страница модуля теста
/tests/{module_key}/add   → Добавление результата теста
/tests/{module_key}/{id}  → Просмотр результата
/reports                  → Отчеты
/settings                 → Настройки
7.2. Маршрутизатор (Router.php)
php
class Router {
    // ОБРАБОТКА ЗАПРОСА
    public function handle($request);
    
    // РЕГИСТРАЦИЯ МАРШРУТОВ
    public function get($path, $handler);
    public function post($path, $handler);
    
    // ПАРАМЕТРЫ ИЗ URL
    public function getParams();
    
    // РЕДИРЕКТЫ
    public function redirect($url);
}
________________________________________
8. ПРОЦЕСС РАЗРАБОТКИ МОДУЛЯ
8.1. Шаги создания нового теста:
1.	Создать папку в modules/test_name/
2.	Создать module.json с конфигурацией
3.	Создать класс Test.php с логикой теста
4.	Создать install.sql для таблицы результатов
5.	Добавить views/ с шаблонами
6.	Зарегистрировать модуль в системе
8.2. Что может делать модуль:
1.	Создавать свои таблицы в БД
2.	Иметь собственные формы ввода
3.	Реализовывать свою логику расчета
4.	Использовать общие стили и библиотеки
5.	Получать доступ к детям психолога
8.3. Что НЕ может делать модуль:
1.	Изменять общие таблицы системы
2.	Нарушать общий дизайн
3.	Получать доступ к чужим данным
4.	Менять ядро системы
________________________________________
9. БЕЗОПАСНОСТЬ
9.1. Основные меры:
1.	Подготовленные SQL запросы (PDO)
2.	Валидация всех входных данных
3.	Экранирование вывода (htmlspecialchars)
4.	CSRF токены для форм
5.	Проверка прав доступа на каждом уровне
9.2. Контроль доступа:
php
// В каждом методе модуля:
if ($_SESSION['psychologist_id'] != $child['psychologist_id']) {
    throw new Exception('Доступ запрещен');
}
________________________________________
10. РАСШИРЕНИЕ СИСТЕМЫ
10.1. Будущие возможности:
1.	API для интеграции с другими системами
2.	Веб-хуки для событий системы
3.	Плагины для расширения функционала
4.	Темы оформления на выбор
5.	Мультиязычность
10.2. Структура для масштабирования:
text
psychologist-system/
├── core/          # Ядро (не трогать)
├── modules/       # Модули тестов
├── plugins/       # Дополнительные плагины
├── themes/        # Темы оформления
└── locales/       # Локализации
________________________________________
11. ТРЕБОВАНИЯ К СЕРВЕРУ
Минимальные:
•	PHP 7.0+
•	MySQL 5.7+ / MariaDB 10.2+
•	Apache 2.4+ / Nginx
•	512MB RAM
•	1GB дискового пространства
Рекомендуемые:
•	PHP 7.4+
•	MySQL 8.0+
•	1GB+ RAM
•	SSD диск
•	HTTPS (SSL)
________________________________________
12. ПРИНЦИПЫ РАЗРАБОТКИ
12.1. Для ядра системы:
1.	KISS (Keep It Simple, Stupid)
2.	DRY (Don't Repeat Yourself)
3.	Модульность и низкая связность
4.	Безопасность на первом месте
12.2. Для модулей тестов:
1.	Автономность - модуль должен работать независимо
2.	Совместимость - следование интерфейсам ядра
3.	Простота - минимальный необходимый функционал
4.	Документация - описание работы модуля
12.3. Код-стайл:
php
// Именование
$variableName = 'value';
function functionName() {}
class ClassName {}

// Структура файлов
// 1. Namespace
// 2. Imports
// 3. Class definition
// 4. Properties
// 5. Methods

// Комментарии
/**
 * Описание метода
 * @param type $param Описание параметра
 * @return type Описание возвращаемого значения
 */
________________________________________
КРАТКОЕ РЕЗЮМЕ:
1.	Ядро предоставляет базовый функционал и доступ к данным
2.	Модули - самостоятельные тесты со своими таблицами
3.	Дети хранятся в общей таблице с привязкой к психологу
4.	Дизайн единый через Tailwind CSS и общие шаблоны
5.	Безопасность через изоляцию данных и проверку прав
6.	Масштабируемость через модульную архитектуру

