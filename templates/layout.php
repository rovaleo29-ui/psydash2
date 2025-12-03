<?php
/**
 * Основной шаблон системы
 * 
 * @package Templates
 */

use Core\Template;

/**
 * @var Template $this Объект шаблонизатора
 * @var array $data Данные для шаблона
 * @var string $page_title Заголовок страницы
 * @var string $page_content Содержимое страницы
 * @var array|null $current_user Текущий пользователь
 */

// Извлечение переменных из массива $data
extract($data);

// Определение активного пункта меню
$activeMenu = $_SERVER['REQUEST_URI'] ?? '/';
?>
<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->escape($page_title ? $page_title . ' | ' . ($app_name ?? 'Система психолога') : ($app_name ?? 'Система психолога')) ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= $this->asset('images/favicon.ico') ?>">
    
    <!-- CSS библиотеки -->
    <link rel="stylesheet" href="<?= $this->asset('css/tailwind.min.css') ?>">
    <link rel="stylesheet" href="<?= $this->asset('css/main.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- JavaScript библиотеки (в конце body) -->
    
    <!-- CSRF токен для JavaScript -->
    <meta name="csrf-token" content="<?= $this->csrfToken() ?>">
    
    <?php $this->section('head') ?>
    <?php $this->endSection() ?>
</head>
<body class="bg-gray-50 font-sans">
    <!-- Блок загрузки -->
    <div id="loading" class="fixed inset-0 bg-white bg-opacity-80 flex items-center justify-center z-50 hidden">
        <div class="text-center">
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500 mb-4"></div>
            <p class="text-gray-600">Загрузка...</p>
        </div>
    </div>

    <!-- Уведомления -->
    <div id="notifications" class="fixed top-4 right-4 z-40 w-96 space-y-2"></div>

    <?php if ($current_user): ?>
    <!-- Основной контейнер -->
    <div class="flex h-screen">
        <!-- Боковая панель -->
        <aside class="sidebar w-64 bg-white border-r border-gray-200 flex flex-col flex-shrink-0">
            <!-- Логотип -->
            <div class="p-4 border-b border-gray-200">
                <a href="/dashboard" class="flex items-center space-x-3">
                    <img src="<?= $this->asset('images/logo.png') ?>" alt="Логотип" class="h-8 w-8">
                    <span class="text-xl font-bold text-gray-800"><?= $app_name ?? 'Система психолога' ?></span>
                </a>
                <div class="mt-2 text-sm text-gray-500">
                    <i class="fas fa-user mr-1"></i>
                    <?= $this->escape($current_user['full_name'] ?? 'Психолог') ?>
                </div>
            </div>

            <!-- Навигация -->
            <nav class="flex-1 overflow-y-auto p-4">
                <ul class="space-y-1">
                    <!-- Дашборд -->
                    <li>
                        <a href="/dashboard" class="nav-link <?= strpos($activeMenu, '/dashboard') === 0 ? 'active' : '' ?>">
                            <i class="fas fa-tachometer-alt mr-3"></i>
                            <span>Дашборд</span>
                        </a>
                    </li>

                    <!-- Дети -->
                    <li>
                        <a href="/children" class="nav-link <?= strpos($activeMenu, '/children') === 0 ? 'active' : '' ?>">
                            <i class="fas fa-users mr-3"></i>
                            <span>Дети</span>
                        </a>
                        <?php if (strpos($activeMenu, '/children') === 0): ?>
                        <ul class="ml-8 mt-1 space-y-1">
                            <li>
                                <a href="/children" class="subnav-link <?= $activeMenu === '/children' ? 'active' : '' ?>">
                                    <i class="fas fa-list mr-2"></i>
                                    <span>Список</span>
                                </a>
                            </li>
                            <li>
                                <a href="/children/add" class="subnav-link <?= $activeMenu === '/children/add' ? 'active' : '' ?>">
                                    <i class="fas fa-plus mr-2"></i>
                                    <span>Добавить</span>
                                </a>
                            </li>
                        </ul>
                        <?php endif; ?>
                    </li>

                    <!-- Тесты -->
                    <li>
                        <a href="/tests" class="nav-link <?= strpos($activeMenu, '/tests') === 0 ? 'active' : '' ?>">
                            <i class="fas fa-clipboard-check mr-3"></i>
                            <span>Тесты</span>
                        </a>
                        <?php if (strpos($activeMenu, '/tests') === 0): ?>
                        <ul class="ml-8 mt-1 space-y-1">
                            <li>
                                <a href="/tests" class="subnav-link <?= $activeMenu === '/tests' ? 'active' : '' ?>">
                                    <i class="fas fa-th-large mr-2"></i>
                                    <span>Все тесты</span>
                                </a>
                            </li>
                            <?php 
                            // Динамическое меню для активных модулей
                            $active_modules = $this->get('active_modules', []);
                            foreach ($active_modules as $module): 
                                $module_active = strpos($activeMenu, '/tests/' . $module['module_key']) === 0;
                            ?>
                            <li>
                                <a href="/tests/<?= $module['module_key'] ?>" 
                                   class="subnav-link <?= $module_active ? 'active' : '' ?>">
                                    <i class="fas fa-chart-bar mr-2"></i>
                                    <span><?= $this->escape($module['name']) ?></span>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </li>

                    <!-- Отчеты -->
                    <li>
                        <a href="/reports" class="nav-link <?= strpos($activeMenu, '/reports') === 0 ? 'active' : '' ?>">
                            <i class="fas fa-chart-pie mr-3"></i>
                            <span>Отчеты</span>
                        </a>
                    </li>

                    <!-- Настройки -->
                    <li>
                        <a href="/settings" class="nav-link <?= strpos($activeMenu, '/settings') === 0 ? 'active' : '' ?>">
                            <i class="fas fa-cog mr-3"></i>
                            <span>Настройки</span>
                        </a>
                    </li>

                    <!-- Управление модулями (только для админов) -->
                    <?php if (($current_user['psychologist_id'] ?? 0) == 1 || ($current_user['psychologist_id'] ?? 0) == 999): ?>
                    <li>
                        <a href="/tests/manage" class="nav-link <?= strpos($activeMenu, '/tests/manage') === 0 ? 'active' : '' ?>">
                            <i class="fas fa-puzzle-piece mr-3"></i>
                            <span>Модули</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>

                <!-- Разделитель -->
                <div class="my-6 border-t border-gray-200"></div>

                <!-- Системная информация -->
                <div class="px-2 py-3 bg-gray-50 rounded-lg">
                    <div class="text-xs text-gray-500 mb-1">
                        <i class="fas fa-database mr-1"></i> Система
                    </div>
                    <div class="text-sm">
                        <div class="flex justify-between mb-1">
                            <span>Версия:</span>
                            <span class="font-medium"><?= $app_version ?? '1.0.0' ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Пользователь:</span>
                            <span class="font-medium">ID <?= $current_user['psychologist_id'] ?? '?' ?></span>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Выход -->
            <div class="p-4 border-t border-gray-200">
                <a href="/logout" class="flex items-center justify-center px-4 py-2 bg-red-50 text-red-700 rounded-lg hover:bg-red-100 transition-colors">
                    <i class="fas fa-sign-out-alt mr-2"></i>
                    <span>Выйти</span>
                </a>
            </div>
        </aside>

        <!-- Основное содержимое -->
        <main class="flex-1 overflow-hidden flex flex-col">
            <!-- Шапка -->
            <header class="bg-white border-b border-gray-200 px-6 py-4 flex-shrink-0">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800"><?= $this->escape($page_title) ?></h1>
                        <?php if (isset($page_subtitle)): ?>
                        <p class="text-gray-600 mt-1"><?= $this->escape($page_subtitle) ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <!-- Уведомления -->
                        <button type="button" class="relative p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg">
                            <i class="fas fa-bell text-lg"></i>
                            <span class="absolute top-1 right-1 h-2 w-2 bg-red-500 rounded-full"></span>
                        </button>
                        
                        <!-- Поиск (только на некоторых страницах) -->
                        <?php if (in_array($activeMenu, ['/dashboard', '/children', '/tests'])): ?>
                        <div class="relative">
                            <input type="text" 
                                   placeholder="Поиск..." 
                                   class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent w-64">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Кнопка помощи -->
                        <button type="button" class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg" title="Помощь">
                            <i class="fas fa-question-circle text-lg"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Хлебные крошки -->
                <?php if (isset($breadcrumbs)): ?>
                <nav class="mt-4 flex items-center text-sm text-gray-500">
                    <a href="/dashboard" class="hover:text-blue-600">
                        <i class="fas fa-home mr-1"></i> Главная
                    </a>
                    <?php foreach ($breadcrumbs as $crumb): ?>
                    <i class="fas fa-chevron-right mx-2"></i>
                    <?php if (isset($crumb['url'])): ?>
                    <a href="<?= $crumb['url'] ?>" class="hover:text-blue-600"><?= $this->escape($crumb['title']) ?></a>
                    <?php else: ?>
                    <span class="text-gray-700"><?= $this->escape($crumb['title']) ?></span>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
                <?php endif; ?>
            </header>

            <!-- Контент -->
            <div class="flex-1 overflow-y-auto p-6">
                <!-- Сообщения об ошибках/успехе -->
                <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 mr-3 text-lg"></i>
                        <div>
                            <h3 class="font-medium text-green-800">Успешно!</h3>
                            <p class="text-green-600 text-sm mt-1">
                                <?php
                                $messages = [
                                    'create' => 'Запись успешно создана',
                                    'update' => 'Запись успешно обновлена',
                                    'delete' => 'Запись успешно удалена',
                                    'install' => 'Модуль успешно установлен',
                                    'uninstall' => 'Модуль успешно удален'
                                ];
                                echo $messages[$_GET['action'] ?? ''] ?? 'Операция выполнена успешно';
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (isset($_GET['error']) && $_GET['error'] == '1' && isset($_GET['message'])): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 mr-3 text-lg"></i>
                        <div>
                            <h3 class="font-medium text-red-800">Ошибка!</h3>
                            <p class="text-red-600 text-sm mt-1"><?= $this->escape(urldecode($_GET['message'])) ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (isset($errors) && !empty($errors)): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 mr-3 text-lg"></i>
                        <div>
                            <h3 class="font-medium text-red-800">Пожалуйста, исправьте ошибки:</h3>
                            <ul class="text-red-600 text-sm mt-1 list-disc list-inside">
                                <?php foreach ($errors as $field => $field_errors): ?>
                                    <?php if (is_array($field_errors)): ?>
                                        <?php foreach ($field_errors as $error): ?>
                                            <li><?= $this->escape($error) ?></li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li><?= $this->escape($field_errors) ?></li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Основной контент страницы -->
                <?= $page_content ?>
            </div>

            <!-- Подвал -->
            <footer class="bg-white border-t border-gray-200 px-6 py-4 flex-shrink-0">
                <div class="flex items-center justify-between text-sm text-gray-500">
                    <div>
                        &copy; <?= $current_year ?? date('Y') ?> <?= $app_name ?? 'Система психолога' ?>. Все права защищены.
                    </div>
                    <div class="flex items-center space-x-4">
                        <a href="/privacy" class="hover:text-gray-700">Конфиденциальность</a>
                        <a href="/terms" class="hover:text-gray-700">Условия использования</a>
                        <a href="/help" class="hover:text-gray-700">Помощь</a>
                        <span>Версия: <?= $app_version ?? '1.0.0' ?></span>
                    </div>
                </div>
            </footer>
        </main>
    </div>
    <?php else: ?>
    <!-- Страницы без авторизации (логин, восстановление пароля) -->
    <div class="min-h-screen bg-gray-50 flex flex-col">
        <!-- Шапка для неавторизованных страниц -->
        <header class="bg-white border-b border-gray-200">
            <div class="container mx-auto px-6 py-4">
                <div class="flex items-center justify-between">
                    <a href="/" class="flex items-center space-x-3">
                        <img src="<?= $this->asset('images/logo.png') ?>" alt="Логотип" class="h-8 w-8">
                        <span class="text-xl font-bold text-gray-800"><?= $app_name ?? 'Система психолога' ?></span>
                    </a>
                    <div class="text-sm text-gray-500">
                        <i class="fas fa-lock mr-1"></i>
                        Система для школьных психологов
                    </div>
                </div>
            </div>
        </header>

        <!-- Контент -->
        <main class="flex-1 flex items-center justify-center p-6">
            <div class="w-full max-w-4xl">
                <?= $page_content ?>
            </div>
        </main>

        <!-- Подвал для неавторизованных страниц -->
        <footer class="bg-white border-t border-gray-200 py-6">
            <div class="container mx-auto px-6">
                <div class="text-center text-sm text-gray-500">
                    <p>&copy; <?= $current_year ?? date('Y') ?> <?= $app_name ?? 'Система психолога' ?>. Все права защищены.</p>
                    <p class="mt-2">Система управления психологическими данными для образовательных учреждений</p>
                </div>
            </div>
        </footer>
    </div>
    <?php endif; ?>

    <!-- JavaScript библиотеки -->
    <script src="<?= $this->asset('js/jquery.min.js') ?>"></script>
    <script src="<?= $this->asset('js/chart.min.js') ?>"></script>
    <script src="<?= $this->asset('js/flatpickr.min.js') ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="<?= $this->asset('js/app.js') ?>"></script>
    
    <!-- Инициализация компонентов -->
    <script>
    $(document).ready(function() {
        // Инициализация Flatpickr для всех полей с датой
        $('input[type="date"], input.flatpickr').flatpickr({
            dateFormat: 'Y-m-d',
            locale: 'ru',
            disableMobile: true
        });
        
        // Инициализация Select2 для всех select
        $('select.select2').select2({
            width: '100%',
            language: 'ru'
        });
        
        // Инициализация DataTables для всех таблиц с классом datatable
        $('.datatable').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/ru.json'
            },
            pageLength: 25,
            responsive: true
        });
        
        // Управление загрузкой
        $(document).on({
            ajaxStart: function() {
                $('#loading').removeClass('hidden');
            },
            ajaxStop: function() {
                $('#loading').addClass('hidden');
            }
        });
        
        // Обработка навигационных ссылок
        $('.nav-link, .subnav-link').on('click', function() {
            $('#loading').removeClass('hidden');
        });
        
        // Инициализация тултипов
        $('[title]').tooltip();
        
        // Обработка форм с подтверждением
        $('form.confirm-form').on('submit', function(e) {
            if (!confirm('Вы уверены, что хотите выполнить это действие?')) {
                e.preventDefault();
                return false;
            }
        });
        
        // Уведомления
        window.showNotification = function(message, type = 'info') {
            const types = {
                success: 'bg-green-100 border-green-400 text-green-700',
                error: 'bg-red-100 border-red-400 text-red-700',
                warning: 'bg-yellow-100 border-yellow-400 text-yellow-700',
                info: 'bg-blue-100 border-blue-400 text-blue-700'
            };
            
            const notification = $(`
                <div class="notification p-4 rounded-lg border ${types[type]} shadow-lg transform transition-all duration-300 translate-x-full">
                    <div class="flex items-center">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'} mr-3"></i>
                        <div class="flex-1">${message}</div>
                        <button type="button" class="ml-4 text-gray-500 hover:text-gray-700 close-notification">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `);
            
            $('#notifications').append(notification);
            
            // Анимация появления
            setTimeout(() => {
                notification.removeClass('translate-x-full');
            }, 10);
            
            // Автоматическое скрытие
            setTimeout(() => {
                closeNotification(notification);
            }, 5000);
            
            // Закрытие по клику
            notification.find('.close-notification').on('click', function() {
                closeNotification(notification);
            });
        };
        
        function closeNotification(notification) {
            notification.addClass('translate-x-full opacity-0');
            setTimeout(() => {
                notification.remove();
            }, 300);
        }
        
        // Глобальный обработчик ошибок Ajax
        $(document).ajaxError(function(event, jqxhr, settings, thrownError) {
            if (jqxhr.status === 401) {
                // Неавторизован - перенаправляем на логин
                window.location.href = '/login?expired=1';
            } else if (jqxhr.status === 403) {
                showNotification('Доступ запрещен', 'error');
            } else if (jqxhr.status === 404) {
                showNotification('Запрашиваемый ресурс не найден', 'error');
            } else if (jqxhr.status === 500) {
                showNotification('Внутренняя ошибка сервера', 'error');
            } else {
                showNotification('Произошла ошибка при выполнении запроса', 'error');
            }
        });
        
        // CSRF токен для всех Ajax запросов
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        
        // Автоматическое скрытие сообщений через 5 секунд
        setTimeout(function() {
            $('.alert-auto-close').fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    });
    </script>
    
    <!-- Дополнительные скрипты страницы -->
    <?php $this->section('scripts') ?>
    <?php $this->endSection() ?>
</body>
</html>