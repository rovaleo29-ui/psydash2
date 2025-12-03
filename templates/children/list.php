<?php
/**
 * Шаблон списка детей
 * 
 * @package Templates\Children
 */

use Core\Template;

/**
 * @var Template $this Объект шаблонизатора
 * @var array $data Данные для шаблона
 * @var array $children Список детей
 * @var int $total_children Общее количество детей
 * @var int $current_page Текущая страница
 * @var int $total_pages Всего страниц
 * @var int $per_page Количество на странице
 * @var array $filters Фильтры
 * @var array $unique_classes Уникальные классы
 * @var array $children_stats Статистика по детям
 * @var array $psychologist Текущий психолог
 */

// Извлечение переменных из массива $data
extract($data);

// Определение активного фильтра
$active_class = $filters['class'] ?? null;
$active_search = $filters['search'] ?? null;
?>
<div class="space-y-6">
    <!-- Заголовок и кнопки -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Дети</h1>
            <p class="text-gray-600 mt-1">Управление детьми в системе</p>
        </div>
        
        <div class="flex items-center space-x-3">
            <!-- Кнопка экспорта -->
            <div class="relative group">
                <button type="button" class="flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                    <i class="fas fa-download mr-2"></i>
                    Экспорт
                    <i class="fas fa-chevron-down ml-2"></i>
                </button>
                <div class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-10 hidden group-hover:block">
                    <a href="/children/export?format=excel" class="block px-4 py-3 hover:bg-gray-50 rounded-t-lg">
                        <i class="fas fa-file-excel text-green-600 mr-2"></i>
                        Excel (.xlsx)
                    </a>
                    <a href="/children/export?format=pdf" class="block px-4 py-3 hover:bg-gray-50">
                        <i class="fas fa-file-pdf text-red-600 mr-2"></i>
                        PDF (.pdf)
                    </a>
                    <a href="/children/export?format=csv" class="block px-4 py-3 hover:bg-gray-50 rounded-b-lg">
                        <i class="fas fa-file-csv text-blue-600 mr-2"></i>
                        CSV (.csv)
                    </a>
                </div>
            </div>
            
            <!-- Кнопка добавления -->
            <a href="/children/add" class="flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                <i class="fas fa-plus mr-2"></i>
                Добавить ребенка
            </a>
        </div>
    </div>

    <!-- Фильтры и поиск -->
    <div class="bg-white rounded-xl shadow-md p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Поиск по имени -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Поиск по имени</label>
                <form method="GET" action="/children" class="relative">
                    <input type="text" 
                           name="search" 
                           value="<?= $this->escape($active_search ?? '') ?>" 
                           placeholder="Фамилия или имя..." 
                           class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                </form>
            </div>
            
            <!-- Фильтр по классу -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Класс</label>
                <form method="GET" action="/children" class="relative">
                    <select name="class" 
                            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 appearance-none"
                            onchange="this.form.submit()">
                        <option value="">Все классы</option>
                        <?php foreach ($unique_classes as $class_item): ?>
                        <option value="<?= $this->escape($class_item['class']) ?>" 
                                <?= $active_class == $class_item['class'] ? 'selected' : '' ?>>
                            <?= $this->escape($class_item['class']) ?> класс
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <i class="fas fa-graduation-cap absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                </form>
            </div>
            
            <!-- Фильтр по возрасту -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Возраст</label>
                <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option>Любой возраст</option>
                    <option>7-10 лет</option>
                    <option>11-14 лет</option>
                    <option>15-18 лет</option>
                </select>
            </div>
            
            <!-- Кнопка сброса -->
            <div class="flex items-end">
                <a href="/children" class="w-full px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-center">
                    <i class="fas fa-redo mr-2"></i>
                    Сбросить фильтры
                </a>
            </div>
        </div>
    </div>

    <!-- Статистика -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-md p-4">
            <div class="flex items-center">
                <div class="bg-blue-100 p-3 rounded-lg mr-4">
                    <i class="fas fa-users text-blue-600"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Всего детей</p>
                    <p class="text-2xl font-bold"><?= $total_children ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-md p-4">
            <div class="flex items-center">
                <div class="bg-green-100 p-3 rounded-lg mr-4">
                    <i class="fas fa-graduation-cap text-green-600"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Классов</p>
                    <p class="text-2xl font-bold"><?= count($unique_classes) ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-md p-4">
            <div class="flex items-center">
                <div class="bg-purple-100 p-3 rounded-lg mr-4">
                    <i class="fas fa-birthday-cake text-purple-600"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">С датой рождения</p>
                    <p class="text-2xl font-bold"><?= $children_stats['with_birth_date'] ?? 0 ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-md p-4">
            <div class="flex items-center">
                <div class="bg-orange-100 p-3 rounded-lg mr-4">
                    <i class="fas fa-chart-line text-orange-600"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Средний возраст</p>
                    <p class="text-2xl font-bold"><?= $children_stats['avg_age'] ?? '—' ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Таблица детей -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <!-- Заголовок таблицы -->
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-lg font-medium text-gray-800">Список детей</h2>
            <div class="text-sm text-gray-500">
                Показано <span class="font-medium"><?= count($children) ?></span> из <span class="font-medium"><?= $total_children ?></span>
            </div>
        </div>
        
        <?php if (!empty($children)): ?>
        <!-- Таблица -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 datatable">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Ребенок
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Класс
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Возраст
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Тестов
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Последний тест
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Дата добавления
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Действия
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($children as $child): 
                        $age = $this->calculateAge($child['birth_date']);
                        $test_stats = $this->get('child_test_stats', [])[$child['id']] ?? ['total_tests' => 0, 'last_test_date' => null];
                    ?>
                    <tr class="hover:bg-gray-50">
                        <!-- Имя и фамилия -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                        <i class="fas fa-user text-blue-600"></i>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        <a href="/children/<?= $child['id'] ?>" class="hover:text-blue-600">
                                            <?= $this->escape($child['last_name'] . ' ' . $child['first_name']) ?>
                                        </a>
                                    </div>
                                    <?php if (!empty($child['notes'])): ?>
                                    <div class="text-xs text-gray-500 truncate max-w-xs" title="<?= $this->escape($child['notes']) ?>">
                                        <i class="fas fa-sticky-note mr-1"></i>
                                        <?= $this->escape(mb_substr($child['notes'], 0, 50)) . (mb_strlen($child['notes']) > 50 ? '...' : '') ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        
                        <!-- Класс -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                <?= $this->escape($child['class']) ?>
                            </span>
                        </td>
                        
                        <!-- Возраст -->
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php if (!empty($child['birth_date'])): ?>
                            <div class="flex items-center">
                                <i class="fas fa-birthday-cake mr-2 text-gray-400"></i>
                                <?= $age ?>
                            </div>
                            <div class="text-xs text-gray-400">
                                <?= $this->formatDate($child['birth_date'], 'd.m.Y') ?>
                            </div>
                            <?php else: ?>
                            <span class="text-gray-400">—</span>
                            <?php endif; ?>
                        </td>
                        
                        <!-- Количество тестов -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-center">
                                <span class="text-lg font-bold <?= $test_stats['total_tests'] > 0 ? 'text-green-600' : 'text-gray-400' ?>">
                                    <?= $test_stats['total_tests'] ?>
                                </span>
                                <div class="text-xs text-gray-500 mt-1">тестов</div>
                            </div>
                        </td>
                        
                        <!-- Последний тест -->
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php if (!empty($test_stats['last_test_date'])): ?>
                            <div class="flex items-center">
                                <i class="far fa-calendar-check mr-2 text-gray-400"></i>
                                <?= $this->formatDate($test_stats['last_test_date'], 'd.m.Y') ?>
                            </div>
                            <div class="text-xs text-gray-400">
                                <?= $this->formatDate($test_stats['last_test_date'], 'H:i') ?>
                            </div>
                            <?php else: ?>
                            <span class="text-gray-400">—</span>
                            <?php endif; ?>
                        </td>
                        
                        <!-- Дата добавления -->
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <div class="flex items-center">
                                <i class="far fa-calendar-plus mr-2 text-gray-400"></i>
                                <?= $this->formatDate($child['created_at'], 'd.m.Y') ?>
                            </div>
                            <div class="text-xs text-gray-400">
                                <?= $this->formatDate($child['created_at'], 'H:i') ?>
                            </div>
                        </td>
                        
                        <!-- Действия -->
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex items-center space-x-2">
                                <!-- Просмотр -->
                                <a href="/children/<?= $child['id'] ?>" 
                                   class="text-blue-600 hover:text-blue-900 p-2 hover:bg-blue-50 rounded-lg"
                                   title="Просмотр">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <!-- Редактирование -->
                                <a href="/children/<?= $child['id'] ?>/edit" 
                                   class="text-green-600 hover:text-green-900 p-2 hover:bg-green-50 rounded-lg"
                                   title="Редактировать">
                                    <i class="fas fa-edit"></i>
                                </a>
                                
                                <!-- Добавить тест -->
                                <div class="relative group">
                                    <button type="button" 
                                            class="text-purple-600 hover:text-purple-900 p-2 hover:bg-purple-50 rounded-lg"
                                            title="Добавить тест">
                                        <i class="fas fa-plus-circle"></i>
                                    </button>
                                    <div class="absolute left-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-10 hidden group-hover:block">
                                        <?php foreach ($active_modules as $module): ?>
                                        <a href="/tests/<?= $module['module_key'] ?>/add?child_id=<?= $child['id'] ?>" 
                                           class="block px-4 py-2 hover:bg-gray-50">
                                            <i class="fas fa-chart-bar mr-2 text-sm"></i>
                                            <?= $this->escape($module['name']) ?>
                                        </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <!-- Удаление -->
                                <form method="POST" 
                                      action="/children/<?= $child['id'] ?>/delete" 
                                      class="inline"
                                      onsubmit="return confirm('Удалить ребенка <?= $this->escape($child['last_name'] . ' ' . $child['first_name']) ?>?')">
                                    <?php $this->csrfField() ?>
                                    <button type="submit" 
                                            class="text-red-600 hover:text-red-900 p-2 hover:bg-red-50 rounded-lg"
                                            title="Удалить">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Пагинация -->
        <?php if ($total_pages > 1): ?>
        <div class="px-6 py-4 border-t border-gray-200">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-700">
                    Страница <span class="font-medium"><?= $current_page ?></span> из <span class="font-medium"><?= $total_pages ?></span>
                </div>
                
                <div class="flex space-x-2">
                    <!-- Первая страница -->
                    <?php if ($current_page > 1): ?>
                    <a href="/children?page=1<?= $active_class ? '&class=' . urlencode($active_class) : '' ?><?= $active_search ? '&search=' . urlencode($active_search) : '' ?>" 
                       class="px-3 py-1 border border-gray-300 rounded-lg hover:bg-gray-50">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <?php endif; ?>
                    
                    <!-- Предыдущая страница -->
                    <?php if ($current_page > 1): ?>
                    <a href="/children?page=<?= $current_page - 1 ?><?= $active_class ? '&class=' . urlencode($active_class) : '' ?><?= $active_search ? '&search=' . urlencode($active_search) : '' ?>" 
                       class="px-3 py-1 border border-gray-300 rounded-lg hover:bg-gray-50">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php endif; ?>
                    
                    <!-- Номера страниц -->
                    <?php 
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $start_page + 4);
                    $start_page = max(1, min($start_page, $end_page - 4));
                    
                    for ($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                    <a href="/children?page=<?= $i ?><?= $active_class ? '&class=' . urlencode($active_class) : '' ?><?= $active_search ? '&search=' . urlencode($active_search) : '' ?>" 
                       class="px-3 py-1 border rounded-lg <?= $i == $current_page ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 hover:bg-gray-50' ?>">
                        <?= $i ?>
                    </a>
                    <?php endfor; ?>
                    
                    <!-- Следующая страница -->
                    <?php if ($current_page < $total_pages): ?>
                    <a href="/children?page=<?= $current_page + 1 ?><?= $active_class ? '&class=' . urlencode($active_class) : '' ?><?= $active_search ? '&search=' . urlencode($active_search) : '' ?>" 
                       class="px-3 py-1 border border-gray-300 rounded-lg hover:bg-gray-50">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php endif; ?>
                    
                    <!-- Последняя страница -->
                    <?php if ($current_page < $total_pages): ?>
                    <a href="/children?page=<?= $total_pages ?><?= $active_class ? '&class=' . urlencode($active_class) : '' ?><?= $active_search ? '&search=' . urlencode($active_search) : '' ?>" 
                       class="px-3 py-1 border border-gray-300 rounded-lg hover:bg-gray-50">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
                
                <div class="text-sm text-gray-700">
                    Показать по:
                    <select class="ml-2 border border-gray-300 rounded-lg px-2 py-1" 
                            onchange="window.location.href='/children?per_page=' + this.value + '<?= $active_class ? '&class=' . urlencode($active_class) : '' ?><?= $active_search ? '&search=' . urlencode($active_search) : '' ?>'">
                        <option value="20" <?= $per_page == 20 ? 'selected' : '' ?>>20</option>
                        <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= $per_page == 100 ? 'selected' : '' ?>>100</option>
                    </select>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <!-- Сообщение об отсутствии детей -->
        <div class="px-6 py-12 text-center">
            <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-6">
                <i class="fas fa-users text-gray-400 text-3xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Дети не найдены</h3>
            <p class="text-gray-500 mb-6 max-w-md mx-auto">
                <?php if ($active_class || $active_search): ?>
                Попробуйте изменить параметры поиска или сбросить фильтры
                <?php else: ?>
                В системе еще нет детей. Добавьте первого ребенка, чтобы начать работу.
                <?php endif; ?>
            </p>
            <div class="space-x-3">
                <?php if ($active_class || $active_search): ?>
                <a href="/children" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                    <i class="fas fa-redo mr-2"></i> Сбросить фильтры
                </a>
                <?php endif; ?>
                <a href="/children/add" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-plus mr-2"></i> Добавить ребенка
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Графики распределения -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Распределение по классам -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="text-lg font-medium text-gray-800 mb-4">
                <i class="fas fa-chart-bar text-blue-500 mr-2"></i>
                Распределение по классам
            </h3>
            <div class="h-64">
                <canvas id="classDistributionChart"></canvas>
            </div>
        </div>
        
        <!-- Возрастное распределение -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="text-lg font-medium text-gray-800 mb-4">
                <i class="fas fa-user-friends text-green-500 mr-2"></i>
                Возрастное распределение
            </h3>
            <div class="h-64">
                <canvas id="ageDistributionChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
$(document).ready(function() {
    // Инициализация DataTables
    $('.datatable').DataTable({
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/ru.json'
        },
        pageLength: <?= $per_page ?>,
        lengthMenu: [20, 50, 100],
        order: [[0, 'asc']],
        responsive: true,
        dom: '<"flex justify-between items-center mb-4"<"text-gray-700">f>rt<"flex justify-between items-center mt-4"<"text-gray-700 text-sm">p>',
        initComplete: function() {
            // Кастомизация поиска
            const searchInput = $('.dataTables_filter input');
            searchInput.attr('placeholder', 'Поиск по всем полям...');
            searchInput.addClass('px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500');
        }
    });
    
    // Инициализация графиков
    initCharts();
    
    function initCharts() {
        // График распределения по классам
        const classCtx = document.getElementById('classDistributionChart').getContext('2d');
        const classChart = new Chart(classCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($children_stats['by_class'] ?? [], 'class')) ?>,
                datasets: [{
                    label: 'Количество детей',
                    data: <?= json_encode(array_column($children_stats['by_class'] ?? [], 'count')) ?>,
                    backgroundColor: '#3b82f6',
                    borderColor: '#2563eb',
                    borderWidth: 1
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
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
        
        // График возрастного распределения
        const ageCtx = document.getElementById('ageDistributionChart').getContext('2d');
        const ageChart = new Chart(ageCtx, {
            type: 'pie',
            data: {
                labels: ['7-10 лет', '11-14 лет', '15-18 лет', 'Возраст не указан'],
                datasets: [{
                    data: [8, 12, 5, 3], // Заглушка - в реальной системе нужно считать
                    backgroundColor: [
                        '#8b5cf6',
                        '#3b82f6',
                        '#10b981',
                        '#9ca3af'
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
                        position: 'right'
                    }
                }
            }
        });
    }
    
    // Подсказки для действий
    $('[title]').tooltip({
        placement: 'top',
        trigger: 'hover'
    });
    
    // Подтверждение удаления
    $('form button[type="submit"]').click(function(e) {
        const form = $(this).closest('form');
        const childName = form.data('child-name') || 'ребенка';
        
        if (!confirm(`Вы уверены, что хотите удалить ${childName}?`)) {
            e.preventDefault();
            return false;
        }
    });
    
    // Быстрый поиск при вводе
    let searchTimer;
    $('input[name="search"]').on('input', function() {
        clearTimeout(searchTimer);
        const searchValue = $(this).val().trim();
        
        if (searchValue.length >= 2 || searchValue.length === 0) {
            searchTimer = setTimeout(() => {
                $(this).closest('form').submit();
            }, 500);
        }
    });
    
    // Экспорт данных
    $('.export-btn').click(function(e) {
        e.preventDefault();
        const format = $(this).data('format');
        let url = '/children/export?format=' + format;
        
        // Добавляем фильтры к URL экспорта
        if ('<?= $active_class ?>') {
            url += '&class=' + encodeURIComponent('<?= $active_class ?>');
        }
        if ('<?= $active_search ?>') {
            url += '&search=' + encodeURIComponent('<?= $active_search ?>');
        }
        
        window.location.href = url;
    });
    
    // Массовые действия
    let selectedChildren = [];
    
    $('.select-child').change(function() {
        const childId = $(this).data('child-id');
        const isChecked = $(this).is(':checked');
        
        if (isChecked) {
            selectedChildren.push(childId);
        } else {
            selectedChildren = selectedChildren.filter(id => id !== childId);
        }
        
        updateSelectionCounter();
    });
    
    function updateSelectionCounter() {
        const counter = $('#selected-counter');
        if (selectedChildren.length > 0) {
            counter.text(selectedChildren.length + ' выбрано');
            counter.removeClass('hidden');
        } else {
            counter.addClass('hidden');
        }
    }
    
    $('#select-all').change(function() {
        const isChecked = $(this).is(':checked');
        $('.select-child').prop('checked', isChecked).trigger('change');
    });
    
    // Групповое удаление
    $('#bulk-delete').click(function() {
        if (selectedChildren.length === 0) {
            alert('Выберите хотя бы одного ребенка');
            return;
        }
        
        if (confirm(`Удалить ${selectedChildren.length} выбранных детей?`)) {
            // Здесь будет отправка AJAX запроса
            console.log('Удаление детей:', selectedChildren);
        }
    });
    
    // Групповой экспорт
    $('#bulk-export').click(function() {
        if (selectedChildren.length === 0) {
            alert('Выберите хотя бы одного ребенка');
            return;
        }
        
        const childIds = selectedChildren.join(',');
        window.location.href = '/children/export?format=excel&ids=' + childIds;
    });
});
</script>

<!-- Стили -->
<style>
/* Кастомные стили для таблицы */
.dataTables_wrapper .dataTables_length select {
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    padding: 0.25rem 2rem 0.25rem 0.5rem;
    background-color: white;
}

.dataTables_wrapper .dataTables_filter input {
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    padding: 0.5rem 1rem;
    margin-left: 0.5rem;
}

/* Стили для строк таблицы */
table.dataTable tbody tr:hover {
    background-color: #f9fafb !important;
}

/* Стили для пагинации */
.dataTables_wrapper .dataTables_paginate .paginate_button {
    border: 1px solid #d1d5db !important;
    border-radius: 0.375rem !important;
    margin: 0 0.125rem !important;
    padding: 0.375rem 0.75rem !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: #3b82f6 !important;
    color: white !important;
    border-color: #3b82f6 !important;
}

/* Анимация загрузки */
@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

.loading-row {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

/* Стили для выпадающих меню */
.dropdown-menu {
    animation: fadeIn 0.2s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Стили для тултипов */
.tooltip-inner {
    background-color: #1f2937;
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
}

.tooltip.bs-tooltip-top .arrow::before {
    border-top-color: #1f2937;
}

/* Адаптивные стили */
@media (max-width: 768px) {
    .table-actions {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .table-actions > * {
        width: 100%;
    }
}
</style>