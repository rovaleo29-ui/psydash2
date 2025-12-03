<?php
/**
 * Шаблон главной страницы дашборда
 * 
 * @package Templates\Dashboard
 */

use Core\Template;

/**
 * @var Template $this Объект шаблонизатора
 * @var array $data Данные для шаблона
 * @var array $stats Статистика системы
 * @var array $recent_children Последние добавленные дети
 * @var array $recent_tests Последние результаты тестов
 * @var array $active_modules Активные модули тестов
 * @var array $psychologist Текущий психолог
 */

// Извлечение переменных из массива $data
extract($data);
?>
<div class="space-y-6">
    <!-- Приветствие -->
    <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl p-6 text-white shadow-lg">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Добро пожаловать, <?= $this->escape($psychologist['full_name'] ?? 'Психолог') ?>!</h1>
                <p class="mt-2 opacity-90">Система управления психологическими данными. Сегодня <?= date('d.m.Y') ?></p>
            </div>
            <div class="hidden md:block">
                <i class="fas fa-chart-line text-4xl opacity-80"></i>
            </div>
        </div>
        
        <!-- Краткая статистика -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
            <div class="bg-white bg-opacity-20 p-4 rounded-lg backdrop-blur-sm">
                <div class="flex items-center">
                    <div class="bg-white bg-opacity-30 p-2 rounded-lg mr-3">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <p class="text-sm opacity-90">Детей в системе</p>
                        <p class="text-2xl font-bold"><?= $stats['children_count'] ?? 0 ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white bg-opacity-20 p-4 rounded-lg backdrop-blur-sm">
                <div class="flex items-center">
                    <div class="bg-white bg-opacity-30 p-2 rounded-lg mr-3">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div>
                        <p class="text-sm opacity-90">Всего тестов</p>
                        <p class="text-2xl font-bold"><?= $stats['total_tests'] ?? 0 ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white bg-opacity-20 p-4 rounded-lg backdrop-blur-sm">
                <div class="flex items-center">
                    <div class="bg-white bg-opacity-30 p-2 rounded-lg mr-3">
                        <i class="fas fa-school"></i>
                    </div>
                    <div>
                        <p class="text-sm opacity-90">Классов</p>
                        <p class="text-2xl font-bold"><?= $stats['classes_count'] ?? 0 ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white bg-opacity-20 p-4 rounded-lg backdrop-blur-sm">
                <div class="flex items-center">
                    <div class="bg-white bg-opacity-30 p-2 rounded-lg mr-3">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div>
                        <p class="text-sm opacity-90">Тестов за 30 дней</p>
                        <p class="text-2xl font-bold"><?= $stats['recent_tests'] ?? 0 ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Основные секции -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Последние дети -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-gray-800">
                    <i class="fas fa-user-plus text-blue-500 mr-2"></i>
                    Недавно добавленные дети
                </h2>
                <a href="/children" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    Все дети <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            
            <?php if (!empty($recent_children)): ?>
            <div class="space-y-4">
                <?php foreach ($recent_children as $child): ?>
                <div class="flex items-center p-3 hover:bg-gray-50 rounded-lg transition-colors">
                    <div class="flex-shrink-0">
                        <div class="h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-blue-600"></i>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <div class="flex items-center justify-between">
                            <a href="/children/<?= $child['id'] ?>" class="font-medium text-gray-900 hover:text-blue-600">
                                <?= $this->escape($child['last_name'] . ' ' . $child['first_name']) ?>
                            </a>
                            <span class="text-sm bg-blue-100 text-blue-800 px-2 py-1 rounded-full">
                                <?= $this->escape($child['class']) ?> класс
                            </span>
                        </div>
                        <div class="mt-1 flex items-center text-sm text-gray-500">
                            <i class="far fa-calendar mr-1"></i>
                            Добавлен <?= $this->formatDate($child['created_at'], 'd.m.Y') ?>
                            <?php if (!empty($child['birth_date'])): ?>
                            <span class="mx-2">•</span>
                            <i class="far fa-birthday-cake mr-1"></i>
                            <?= $this->formatAge($child['birth_date']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="text-center py-8">
                <div class="text-gray-400 mb-3">
                    <i class="fas fa-users text-4xl"></i>
                </div>
                <p class="text-gray-500">Дети еще не добавлены</p>
                <a href="/children/add" class="mt-3 inline-block px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-plus mr-2"></i> Добавить первого ребенка
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Последние тесты -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-gray-800">
                    <i class="fas fa-chart-line text-green-500 mr-2"></i>
                    Недавние тесты
                </h2>
                <a href="/tests" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    Все тесты <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            
            <?php if (!empty($recent_tests)): ?>
            <div class="space-y-4">
                <?php foreach ($recent_tests as $test): ?>
                <div class="flex items-center p-3 hover:bg-gray-50 rounded-lg transition-colors">
                    <div class="flex-shrink-0">
                        <div class="h-10 w-10 rounded-full flex items-center justify-center 
                            <?= $test['module_key'] == 'anxiety_spielberger' ? 'bg-purple-100' : 
                               ($test['module_key'] == 'sociometry' ? 'bg-green-100' : 'bg-blue-100') ?>">
                            <i class="<?= $test['module_key'] == 'anxiety_spielberger' ? 'fas fa-heartbeat text-purple-600' : 
                                        ($test['module_key'] == 'sociometry' ? 'fas fa-users text-green-600' : 'fas fa-chart-bar text-blue-600') ?>"></i>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <div class="flex items-center justify-between">
                            <div>
                                <a href="/tests/<?= $test['module_key'] ?>/<?= $test['id'] ?>" class="font-medium text-gray-900 hover:text-blue-600">
                                    <?= $this->escape($test['module_name'] ?? 'Тест') ?>
                                </a>
                                <span class="text-sm text-gray-500 ml-2">
                                    <?= $test['first_name'] ? $this->escape($test['last_name'] . ' ' . $test['first_name']) : 'Ребенок #' . $test['child_id'] ?>
                                </span>
                            </div>
                            <span class="text-sm bg-gray-100 text-gray-800 px-2 py-1 rounded-full">
                                <?= $this->escape($test['class'] ?? '?') ?>
                            </span>
                        </div>
                        <div class="mt-1 flex items-center text-sm text-gray-500">
                            <i class="far fa-calendar mr-1"></i>
                            <?= $this->formatDate($test['test_date'], 'd.m.Y') ?>
                            <span class="mx-2">•</span>
                            <i class="far fa-clock mr-1"></i>
                            <?= $this->formatDate($test['created_at'], 'H:i') ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="text-center py-8">
                <div class="text-gray-400 mb-3">
                    <i class="fas fa-clipboard-check text-4xl"></i>
                </div>
                <p class="text-gray-500">Тесты еще не проводились</p>
                <a href="/tests" class="mt-3 inline-block px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-play mr-2"></i> Провести первый тест
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Статистика и модули -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Детальная статистика -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-6">
                <i class="fas fa-chart-pie text-purple-500 mr-2"></i>
                Детальная статистика
            </h2>
            
            <div class="space-y-6">
                <!-- Прогресс-бары -->
                <div>
                    <div class="flex justify-between text-sm text-gray-600 mb-1">
                        <span>Тестов на ребенка (в среднем)</span>
                        <span><?= $stats['avg_tests_per_child'] ?? 0 ?></span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full" 
                             style="width: <?= min(($stats['avg_tests_per_child'] ?? 0) * 10, 100) ?>%"></div>
                    </div>
                </div>
                
                <!-- Распределение по классам -->
                <div>
                    <h3 class="font-medium text-gray-700 mb-3">Дети по классам</h3>
                    <div class="space-y-2">
                        <?php 
                        // Здесь должна быть логика получения распределения по классам
                        // Для примеси покажем заглушку
                        ?>
                        <div class="flex items-center">
                            <div class="w-24 text-sm text-gray-600">5А класс</div>
                            <div class="flex-1">
                                <div class="w-3/4 bg-blue-100 h-4 rounded-full overflow-hidden">
                                    <div class="bg-blue-500 h-full" style="width: 75%"></div>
                                </div>
                            </div>
                            <div class="w-10 text-right text-sm font-medium">12</div>
                        </div>
                        <div class="flex items-center">
                            <div class="w-24 text-sm text-gray-600">6Б класс</div>
                            <div class="flex-1">
                                <div class="w-3/4 bg-green-100 h-4 rounded-full overflow-hidden">
                                    <div class="bg-green-500 h-full" style="width: 60%"></div>
                                </div>
                            </div>
                            <div class="w-10 text-right text-sm font-medium">9</div>
                        </div>
                        <div class="flex items-center">
                            <div class="w-24 text-sm text-gray-600">7В класс</div>
                            <div class="flex-1">
                                <div class="w-3/4 bg-purple-100 h-4 rounded-full overflow-hidden">
                                    <div class="bg-purple-500 h-full" style="width: 45%"></div>
                                </div>
                            </div>
                            <div class="w-10 text-right text-sm font-medium">7</div>
                        </div>
                    </div>
                </div>
                
                <!-- Активность за месяц -->
                <div>
                    <h3 class="font-medium text-gray-700 mb-3">Активность за месяц</h3>
                    <div class="flex items-end h-32 space-x-2">
                        <?php for ($i = 0; $i < 30; $i++): 
                            $height = rand(10, 100);
                            $date = date('d.m', strtotime("-$i days"));
                        ?>
                        <div class="flex-1 flex flex-col items-center">
                            <div class="w-full bg-blue-100 rounded-t-lg" style="height: <?= $height ?>%"></div>
                            <div class="text-xs text-gray-500 mt-1 <?= $i % 5 === 0 ? '' : 'invisible' ?>">
                                <?= $date ?>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Доступные модули -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-gray-800">
                    <i class="fas fa-puzzle-piece text-orange-500 mr-2"></i>
                    Доступные тесты
                </h2>
                <a href="/tests" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    Все модули <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            
            <?php if (!empty($active_modules)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($active_modules as $module): 
                    $color_classes = [
                        'эмоциональная сфера' => 'bg-purple-100 text-purple-800',
                        'познавательная сфера' => 'bg-blue-100 text-blue-800',
                        'личностные особенности' => 'bg-green-100 text-green-800',
                        'межличностные отношения' => 'bg-yellow-100 text-yellow-800',
                        'профориентация' => 'bg-red-100 text-red-800',
                        'общий' => 'bg-gray-100 text-gray-800'
                    ];
                    
                    $icon_classes = [
                        'эмоциональная сфера' => 'fas fa-heart',
                        'познавательная сфера' => 'fas fa-brain',
                        'личностные особенности' => 'fas fa-user',
                        'межличностные отношения' => 'fas fa-users',
                        'профориентация' => 'fas fa-briefcase',
                        'общий' => 'fas fa-clipboard-list'
                    ];
                    
                    $category = $module['category'] ?? 'общий';
                    $color_class = $color_classes[$category] ?? $color_classes['общий'];
                    $icon_class = $icon_classes[$category] ?? $icon_classes['общий'];
                ?>
                <a href="/tests/<?= $module['module_key'] ?>" 
                   class="group block p-4 border border-gray-200 rounded-lg hover:border-blue-300 hover:shadow-md transition-all">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <div class="h-12 w-12 rounded-lg <?= str_replace('text-', 'bg-', $color_class) ?> flex items-center justify-center">
                                <i class="<?= $icon_class ?> <?= str_replace('bg-', 'text-', $color_class) ?> text-lg"></i>
                            </div>
                        </div>
                        <div class="ml-4 flex-1">
                            <h3 class="font-medium text-gray-900 group-hover:text-blue-600">
                                <?= $this->escape($module['name']) ?>
                            </h3>
                            <p class="text-sm text-gray-500 mt-1 line-clamp-2">
                                <?= $this->escape($module['description'] ?? 'Описание теста') ?>
                            </p>
                            <div class="mt-2 flex items-center justify-between">
                                <span class="text-xs px-2 py-1 rounded-full <?= $color_class ?>">
                                    <?= $this->escape($category) ?>
                                </span>
                                <span class="text-xs text-gray-500 group-hover:text-blue-500">
                                    <i class="fas fa-arrow-right ml-1"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="text-center py-8">
                <div class="text-gray-400 mb-3">
                    <i class="fas fa-puzzle-piece text-4xl"></i>
                </div>
                <p class="text-gray-500">Нет доступных тестов</p>
                <p class="text-sm text-gray-400 mt-1">Обратитесь к администратору для установки модулей</p>
            </div>
            <?php endif; ?>
            
            <!-- Быстрые действия -->
            <div class="mt-8 pt-6 border-t border-gray-200">
                <h3 class="font-medium text-gray-700 mb-4">Быстрые действия</h3>
                <div class="grid grid-cols-2 gap-3">
                    <a href="/children/add" class="flex items-center justify-center p-3 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors">
                        <i class="fas fa-user-plus mr-2"></i>
                        <span>Добавить ребенка</span>
                    </a>
                    <a href="/tests" class="flex items-center justify-center p-3 bg-green-50 text-green-700 rounded-lg hover:bg-green-100 transition-colors">
                        <i class="fas fa-play mr-2"></i>
                        <span>Провести тест</span>
                    </a>
                    <a href="/reports" class="flex items-center justify-center p-3 bg-purple-50 text-purple-700 rounded-lg hover:bg-purple-100 transition-colors">
                        <i class="fas fa-chart-pie mr-2"></i>
                        <span>Создать отчет</span>
                    </a>
                    <a href="/settings" class="flex items-center justify-center p-3 bg-gray-50 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors">
                        <i class="fas fa-cog mr-2"></i>
                        <span>Настройки</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Информационные карточки -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Системная информация -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="font-bold text-gray-800 mb-4">
                <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                Системная информация
            </h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">Версия системы:</span>
                    <span class="font-medium"><?= $app_version ?? '1.0.0' ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Пользователь:</span>
                    <span class="font-medium">ID <?= $psychologist['psychologist_id'] ?? '?' ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Дата:</span>
                    <span class="font-medium"><?= date('d.m.Y') ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Время:</span>
                    <span class="font-medium"><?= date('H:i') ?></span>
                </div>
            </div>
        </div>

        <!-- Полезные ссылки -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="font-bold text-gray-800 mb-4">
                <i class="fas fa-link text-green-500 mr-2"></i>
                Полезные ссылки
            </h3>
            <div class="space-y-2">
                <a href="/docs" class="flex items-center text-gray-700 hover:text-blue-600 p-2 hover:bg-blue-50 rounded-lg">
                    <i class="fas fa-book mr-3 text-gray-400"></i>
                    <span>Документация</span>
                </a>
                <a href="/help" class="flex items-center text-gray-700 hover:text-blue-600 p-2 hover:bg-blue-50 rounded-lg">
                    <i class="fas fa-question-circle mr-3 text-gray-400"></i>
                    <span>Помощь и поддержка</span>
                </a>
                <a href="/video" class="flex items-center text-gray-700 hover:text-blue-600 p-2 hover:bg-blue-50 rounded-lg">
                    <i class="fas fa-video mr-3 text-gray-400"></i>
                    <span>Видеоуроки</span>
                </a>
                <a href="/updates" class="flex items-center text-gray-700 hover:text-blue-600 p-2 hover:bg-blue-50 rounded-lg">
                    <i class="fas fa-sync-alt mr-3 text-gray-400"></i>
                    <span>Обновления</span>
                </a>
            </div>
        </div>

        <!-- Новости системы -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="font-bold text-gray-800 mb-4">
                <i class="fas fa-newspaper text-orange-500 mr-2"></i>
                Новости системы
            </h3>
            <div class="space-y-3">
                <div class="p-3 bg-blue-50 rounded-lg">
                    <div class="text-xs text-blue-600 font-medium mb-1">
                        <i class="fas fa-star mr-1"></i> Новый функционал
                    </div>
                    <p class="text-sm">Добавлен экспорт отчетов в Excel и PDF форматах</p>
                </div>
                <div class="p-3 bg-green-50 rounded-lg">
                    <div class="text-xs text-green-600 font-medium mb-1">
                        <i class="fas fa-bug mr-1"></i> Исправления
                    </div>
                    <p class="text-sm">Устранена ошибка при редактировании детей</p>
                </div>
                <div class="p-3 bg-purple-50 rounded-lg">
                    <div class="text-xs text-purple-600 font-medium mb-1">
                        <i class="fas fa-calendar mr-1"></i> Планы
                    </div>
                    <p class="text-sm">Скоро: мобильное приложение для психологов</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Графики -->
<div class="mt-8">
    <div class="bg-white rounded-xl shadow-md p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-6">
            <i class="fas fa-chart-bar text-indigo-500 mr-2"></i>
            Графики активности
        </h2>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- График активности по месяцам -->
            <div>
                <h3 class="font-medium text-gray-700 mb-4">Активность по месяцам</h3>
                <canvas id="monthlyActivityChart" height="250"></canvas>
            </div>
            
            <!-- Круговая диаграмма распределения -->
            <div>
                <h3 class="font-medium text-gray-700 mb-4">Распределение тестов</h3>
                <canvas id="distributionChart" height="250"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript для графиков -->
<script>
$(document).ready(function() {
    // Инициализация графиков
    initCharts();
    
    function initCharts() {
        // График активности по месяцам
        const monthlyCtx = document.getElementById('monthlyActivityChart').getContext('2d');
        const monthlyChart = new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'],
                datasets: [{
                    label: 'Количество тестов',
                    data: [12, 19, 8, 15, 22, 18, 14, 21, 25, 17, 20, 23],
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            drawBorder: false
                        },
                        ticks: {
                            stepSize: 5
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        
        // Круговая диаграмма распределения
        const distributionCtx = document.getElementById('distributionChart').getContext('2d');
        const distributionChart = new Chart(distributionCtx, {
            type: 'doughnut',
            data: {
                labels: ['Эмоциональная сфера', 'Познавательная сфера', 'Личностные особенности', 'Межличностные отношения'],
                datasets: [{
                    data: [35, 25, 20, 20],
                    backgroundColor: [
                        '#8b5cf6',
                        '#3b82f6',
                        '#10b981',
                        '#f59e0b'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12,
                            padding: 20
                        }
                    }
                },
                cutout: '70%'
            }
        });
        
        // Обновление графиков при изменении размера окна
        $(window).on('resize', function() {
            monthlyChart.resize();
            distributionChart.resize();
        });
    }
    
    // Анимация карточек при прокрутке
    function animateOnScroll() {
        const cards = document.querySelectorAll('.bg-white.rounded-xl');
        
        cards.forEach((card, index) => {
            const cardTop = card.getBoundingClientRect().top;
            const windowHeight = window.innerHeight;
            
            if (cardTop < windowHeight - 100) {
                card.style.animationDelay = `${index * 0.1}s`;
                card.classList.add('animate-fade-in-up');
            }
        });
    }
    
    // Запуск анимации
    animateOnScroll();
    window.addEventListener('scroll', animateOnScroll);
    
    // Автоматическое обновление времени
    function updateTime() {
        const timeElement = document.querySelector('.current-time');
        if (timeElement) {
            const now = new Date();
            timeElement.textContent = now.toLocaleTimeString('ru-RU', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
        }
    }
    
    setInterval(updateTime, 60000);
    updateTime();
    
    // Быстрый поиск
    const searchInput = document.querySelector('input[placeholder="Поиск..."]');
    if (searchInput) {
        searchInput.addEventListener('keyup', function(e) {
            if (e.key === 'Enter' && this.value.trim()) {
                window.location.href = `/search?q=${encodeURIComponent(this.value.trim())}`;
            }
        });
    }
    
    // Подсветка текущей даты
    const today = new Date().toLocaleDateString('ru-RU');
    document.querySelectorAll('.text-gray-500').forEach(el => {
        if (el.textContent.includes(today)) {
            el.classList.add('font-medium', 'text-blue-600');
        }
    });
});
</script>

<!-- Стили для анимаций -->
<style>
.animate-fade-in-up {
    animation: fadeInUp 0.6s ease-out forwards;
    opacity: 0;
    transform: translateY(20px);
}

@keyframes fadeInUp {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.hover-lift {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.hover-lift:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

/* Кастомные стили для графиков */
.chart-container {
    position: relative;
    height: 250px;
    width: 100%;
}

/* Стили для статистических карточек */
.stat-card {
    position: relative;
    overflow: hidden;
}

.stat-card::after {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, transparent 50%, rgba(255,255,255,0.1) 50%);
    border-radius: 0 0 0 100px;
}
</style>
