<?php
/**
 * Шаблон формы добавления ребенка
 * 
 * @package Templates\Children
 */

use Core\Template;

/**
 * @var Template $this Объект шаблонизатора
 * @var array $data Данные для шаблона
 * @var array $form_data Данные формы
 * @var array $errors Ошибки валидации
 * @var array $unique_classes Уникальные классы
 * @var array $psychologist Текущий психолог
 */

// Извлечение переменных из массива $data
extract($data);

// Значения полей формы
$first_name = $form_data['first_name'] ?? '';
$last_name = $form_data['last_name'] ?? '';
$class = $form_data['class'] ?? '';
$birth_date = $form_data['birth_date'] ?? '';
$notes = $form_data['notes'] ?? '';
$gender = $form_data['gender'] ?? '';
$parent_phone = $form_data['parent_phone'] ?? '';
$parent_email = $form_data['parent_email'] ?? '';
$address = $form_data['address'] ?? '';
$health_info = $form_data['health_info'] ?? '';
?>
<div class="max-w-4xl mx-auto">
    <!-- Заголовок -->
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-800">Добавление ребенка</h1>
        <p class="text-gray-600 mt-2">Заполните информацию о ребенке для добавления в систему</p>
        
        <!-- Хлебные крошки -->
        <nav class="mt-4 flex items-center text-sm text-gray-500">
            <a href="/dashboard" class="hover:text-blue-600">
                <i class="fas fa-home mr-1"></i> Главная
            </a>
            <i class="fas fa-chevron-right mx-2"></i>
            <a href="/children" class="hover:text-blue-600">Дети</a>
            <i class="fas fa-chevron-right mx-2"></i>
            <span class="text-gray-700">Добавление</span>
        </nav>
    </div>

    <!-- Форма -->
    <form method="POST" action="/children/add" class="space-y-8">
        <?php $this->csrfField() ?>
        
        <!-- Общие ошибки -->
        <?php if (isset($errors['general']) || isset($errors['other'])): ?>
        <div class="rounded-lg bg-red-50 border border-red-200 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-red-400"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">Ошибки при заполнении формы</h3>
                    <div class="mt-2 text-sm text-red-700">
                        <ul class="list-disc list-inside space-y-1">
                            <?php 
                            $all_errors = [];
                            if (isset($errors['general']) && is_array($errors['general'])) {
                                $all_errors = array_merge($all_errors, $errors['general']);
                            }
                            if (isset($errors['other'])) {
                                $all_errors[] = $errors['other'];
                            }
                            foreach ($all_errors as $error): 
                            ?>
                            <li><?= $this->escape($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Основная информация -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h2 class="text-lg font-medium text-gray-800 mb-6 pb-3 border-b border-gray-200">
                <i class="fas fa-user-circle text-blue-500 mr-2"></i>
                Основная информация
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Фамилия -->
                <div>
                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">
                        Фамилия <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           id="last_name" 
                           name="last_name" 
                           value="<?= $this->escape($last_name) ?>" 
                           required
                           class="w-full px-4 py-2 border <?= isset($errors['last_name']) ? 'border-red-300' : 'border-gray-300' ?> rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Иванов">
                    <?php if (isset($errors['last_name'])): ?>
                    <p class="mt-1 text-sm text-red-600"><?= $this->escape($errors['last_name']) ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Имя -->
                <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">
                        Имя <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           id="first_name" 
                           name="first_name" 
                           value="<?= $this->escape($first_name) ?>" 
                           required
                           class="w-full px-4 py-2 border <?= isset($errors['first_name']) ? 'border-red-300' : 'border-gray-300' ?> rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Иван">
                    <?php if (isset($errors['first_name'])): ?>
                    <p class="mt-1 text-sm text-red-600"><?= $this->escape($errors['first_name']) ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Класс -->
                <div>
                    <label for="class" class="block text-sm font-medium text-gray-700 mb-1">
                        Класс <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="text" 
                               id="class" 
                               name="class" 
                               value="<?= $this->escape($class) ?>" 
                               required
                               list="class-list"
                               class="w-full px-4 py-2 border <?= isset($errors['class']) ? 'border-red-300' : 'border-gray-300' ?> rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="5А">
                        <i class="fas fa-graduation-cap absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        
                        <datalist id="class-list">
                            <?php foreach ($unique_classes as $class_item): ?>
                            <option value="<?= $this->escape($class_item['class']) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <?php if (isset($errors['class'])): ?>
                    <p class="mt-1 text-sm text-red-600"><?= $this->escape($errors['class']) ?></p>
                    <?php endif; ?>
                    <p class="mt-1 text-xs text-gray-500">Формат: цифра(цифры) и буква, например: 5А, 10Б</p>
                </div>
                
                <!-- Пол -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Пол</label>
                    <div class="flex space-x-4">
                        <label class="inline-flex items-center">
                            <input type="radio" 
                                   name="gender" 
                                   value="male" 
                                   <?= $gender === 'male' ? 'checked' : '' ?>
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                            <span class="ml-2 text-gray-700">Мальчик</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" 
                                   name="gender" 
                                   value="female" 
                                   <?= $gender === 'female' ? 'checked' : '' ?>
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                            <span class="ml-2 text-gray-700">Девочка</span>
                        </label>
                    </div>
                </div>
                
                <!-- Дата рождения -->
                <div>
                    <label for="birth_date" class="block text-sm font-medium text-gray-700 mb-1">
                        Дата рождения
                    </label>
                    <div class="relative">
                        <input type="date" 
                               id="birth_date" 
                               name="birth_date" 
                               value="<?= $this->escape($birth_date) ?>"
                               max="<?= date('Y-m-d', strtotime('-3 years')) ?>"
                               min="<?= date('Y-m-d', strtotime('-25 years')) ?>"
                               class="w-full px-4 py-2 border <?= isset($errors['birth_date']) ? 'border-red-300' : 'border-gray-300' ?> rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <i class="fas fa-calendar-alt absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    </div>
                    <?php if (isset($errors['birth_date'])): ?>
                    <p class="mt-1 text-sm text-red-600"><?= $this->escape($errors['birth_date']) ?></p>
                    <?php endif; ?>
                    <p class="mt-1 text-xs text-gray-500">Возраст должен быть от 3 до 25 лет</p>
                </div>
                
                <!-- Вычисление возраста -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Возраст</label>
                    <div class="px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg">
                        <span id="age-display" class="text-gray-700">
                            <?php if (!empty($birth_date)): ?>
                                <?= $this->calculateAge($birth_date) ?>
                            <?php else: ?>
                                Укажите дату рождения
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Контактная информация -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h2 class="text-lg font-medium text-gray-800 mb-6 pb-3 border-b border-gray-200">
                <i class="fas fa-address-book text-green-500 mr-2"></i>
                Контактная информация
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Телефон родителя -->
                <div>
                    <label for="parent_phone" class="block text-sm font-medium text-gray-700 mb-1">
                        Телефон родителя
                    </label>
                    <div class="relative">
                        <input type="tel" 
                               id="parent_phone" 
                               name="parent_phone" 
                               value="<?= $this->escape($parent_phone) ?>"
                               class="w-full px-4 py-2 pl-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="+7 (900) 123-45-67">
                        <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
                            <i class="fas fa-phone text-gray-400"></i>
                        </div>
                    </div>
                    <?php if (isset($errors['parent_phone'])): ?>
                    <p class="mt-1 text-sm text-red-600"><?= $this->escape($errors['parent_phone']) ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Email родителя -->
                <div>
                    <label for="parent_email" class="block text-sm font-medium text-gray-700 mb-1">
                        Email родителя
                    </label>
                    <div class="relative">
                        <input type="email" 
                               id="parent_email" 
                               name="parent_email" 
                               value="<?= $this->escape($parent_email) ?>"
                               class="w-full px-4 py-2 pl-12 border <?= isset($errors['parent_email']) ? 'border-red-300' : 'border-gray-300' ?> rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="parent@example.com">
                        <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
                            <i class="fas fa-envelope text-gray-400"></i>
                        </div>
                    </div>
                    <?php if (isset($errors['parent_email'])): ?>
                    <p class="mt-1 text-sm text-red-600"><?= $this->escape($errors['parent_email']) ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Адрес -->
                <div class="md:col-span-2">
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1">
                        Адрес проживания
                    </label>
                    <textarea id="address" 
                              name="address" 
                              rows="2"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                              placeholder="г. Москва, ул. Примерная, д. 1, кв. 1"><?= $this->escape($address) ?></textarea>
                </div>
            </div>
        </div>

        <!-- Дополнительная информация -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h2 class="text-lg font-medium text-gray-800 mb-6 pb-3 border-b border-gray-200">
                <i class="fas fa-sticky-note text-purple-500 mr-2"></i>
                Дополнительная информация
            </h2>
            
            <!-- Заметки -->
            <div class="mb-6">
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">
                    Заметки психолога
                </label>
                <textarea id="notes" 
                          name="notes" 
                          rows="4"
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                          placeholder="Дополнительная информация о ребенке, особенности, рекомендации..."><?= $this->escape($notes) ?></textarea>
                <p class="mt-1 text-xs text-gray-500">Эту информацию видят только психологи</p>
            </div>
            
            <!-- Информация о здоровье -->
            <div>
                <label for="health_info" class="block text-sm font-medium text-gray-700 mb-1">
                    Информация о здоровье
                </label>
                <textarea id="health_info" 
                          name="health_info" 
                          rows="3"
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                          placeholder="Особенности здоровья, медицинские показания, ограничения..."><?= $this->escape($health_info) ?></textarea>
                <p class="mt-1 text-xs text-gray-500">Конфиденциальная информация, доступна только психологам</p>
            </div>
        </div>

        <!-- Кнопки формы -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="text-sm text-gray-500">
                    <i class="fas fa-info-circle mr-1"></i>
                    Поля, отмеченные <span class="text-red-500">*</span>, обязательны для заполнения
                </div>
                
                <div class="flex space-x-3">
                    <!-- Кнопка отмены -->
                    <a href="/children" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                        Отмена
                    </a>
                    
                    <!-- Кнопка предварительного просмотра -->
                    <button type="button" 
                            id="preview-btn"
                            class="px-6 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200">
                        <i class="fas fa-eye mr-2"></i>
                        Предпросмотр
                    </button>
                    
                    <!-- Кнопка сохранения -->
                    <button type="submit" 
                            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center">
                        <i class="fas fa-save mr-2"></i>
                        Сохранить ребенка
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Модальное окно предпросмотра -->
<div id="preview-modal" class="fixed z-50 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Overlay -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

        <!-- Модальное окно -->
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fas fa-eye text-blue-600"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            Предпросмотр информации о ребенке
                        </h3>
                        
                        <div class="mt-4 bg-gray-50 p-4 rounded-lg">
                            <!-- Информация для предпросмотра -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-sm text-gray-500">ФИО</p>
                                    <p class="font-medium" id="preview-full-name">—</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Класс</p>
                                    <p class="font-medium" id="preview-class">—</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Дата рождения</p>
                                    <p class="font-medium" id="preview-birth-date">—</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Возраст</p>
                                    <p class="font-medium" id="preview-age">—</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Пол</p>
                                    <p class="font-medium" id="preview-gender">—</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Телефон родителя</p>
                                    <p class="font-medium" id="preview-parent-phone">—</p>
                                </div>
                            </div>
                            
                            <!-- Заметки -->
                            <div class="mt-4">
                                <p class="text-sm text-gray-500">Заметки психолога</p>
                                <p class="mt-1" id="preview-notes">—</p>
                            </div>
                        </div>
                        
                        <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                                </div>
                                <div class="ml-3">
                                    <h4 class="text-sm font-medium text-yellow-800">Проверьте информацию</h4>
                                    <div class="mt-2 text-sm text-yellow-700">
                                        <p>Пожалуйста, убедитесь, что все данные введены корректно перед сохранением.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm" onclick="document.querySelector('form').submit()">
                    <i class="fas fa-check mr-2"></i> Всё верно, сохранить
                </button>
                <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm close-preview-modal">
                    Вернуться к редактированию
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
$(document).ready(function() {
    // Автоматическое форматирование телефона
    $('#parent_phone').on('input', function() {
        let phone = $(this).val().replace(/\D/g, '');
        
        if (phone.length > 0) {
            if (phone.length <= 3) {
                phone = phone.replace(/(\d{0,3})/, '+7 ($1');
            } else if (phone.length <= 6) {
                phone = phone.replace(/(\d{3})(\d{0,3})/, '+7 ($1) $2');
            } else if (phone.length <= 8) {
                phone = phone.replace(/(\d{3})(\d{3})(\d{0,2})/, '+7 ($1) $2-$3');
            } else {
                phone = phone.replace(/(\d{3})(\d{3})(\d{2})(\d{0,2})/, '+7 ($1) $2-$3-$4');
            }
        }
        
        $(this).val(phone);
    });
    
    // Автоматическое обновление возраста при изменении даты рождения
    $('#birth_date').on('change', function() {
        updateAgeDisplay();
    });
    
    function updateAgeDisplay() {
        const birthDate = $('#birth_date').val();
        if (!birthDate) {
            $('#age-display').text('Укажите дату рождения');
            return;
        }
        
        const today = new Date();
        const birth = new Date(birthDate);
        
        if (birth > today) {
            $('#age-display').html('<span class="text-red-600">Дата в будущем</span>');
            return;
        }
        
        let years = today.getFullYear() - birth.getFullYear();
        let months = today.getMonth() - birth.getMonth();
        
        if (months < 0 || (months === 0 && today.getDate() < birth.getDate())) {
            years--;
            months += 12;
        }
        
        let ageText = years + ' ' + pluralize(years, 'год', 'года', 'лет');
        if (years === 0) {
            ageText = months + ' ' + pluralize(months, 'месяц', 'месяца', 'месяцев');
            if (months === 0) {
                ageText = 'менее месяца';
            }
        }
        
        $('#age-display').text(ageText);
    }
    
    function pluralize(number, one, two, many) {
        const mod10 = number % 10;
        const mod100 = number % 100;
        
        if (mod10 === 1 && mod100 !== 11) {
            return one;
        } else if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) {
            return two;
        } else {
            return many;
        }
    }
    
    // Инициализация отображения возраста
    updateAgeDisplay();
    
    // Предпросмотр
    $('#preview-btn').click(function() {
        updatePreview();
        $('#preview-modal').removeClass('hidden');
    });
    
    $('.close-preview-modal').click(function() {
        $('#preview-modal').addClass('hidden');
    });
    
    function updatePreview() {
        // Основная информация
        const lastName = $('#last_name').val().trim() || '—';
        const firstName = $('#first_name').val().trim() || '—';
        $('#preview-full-name').text(lastName + ' ' + firstName);
        
        $('#preview-class').text($('#class').val().trim() || '—');
        
        const birthDate = $('#birth_date').val();
        if (birthDate) {
            const date = new Date(birthDate);
            $('#preview-birth-date').text(date.toLocaleDateString('ru-RU'));
        } else {
            $('#preview-birth-date').text('—');
        }
        
        updateAgeDisplay();
        $('#preview-age').text($('#age-display').text());
        
        // Пол
        const gender = $('input[name="gender"]:checked').val();
        $('#preview-gender').text(gender === 'male' ? 'Мальчик' : gender === 'female' ? 'Девочка' : '—');
        
        // Контактная информация
        $('#preview-parent-phone').text($('#parent_phone').val().trim() || '—');
        
        // Заметки
        const notes = $('#notes').val().trim();
        $('#preview-notes').text(notes || '—');
    }
    
    // Закрытие модального окна по клику вне его
    $('#preview-modal').click(function(e) {
        if (e.target === this) {
            $(this).addClass('hidden');
        }
    });
    
    // Валидация формы перед отправкой
    $('form').submit(function(e) {
        let hasErrors = false;
        
        // Проверка обязательных полей
        $('[required]').each(function() {
            if (!$(this).val().trim()) {
                $(this).addClass('border-red-300');
                hasErrors = true;
            } else {
                $(this).removeClass('border-red-300');
            }
        });
        
        // Проверка формата класса
        const classValue = $('#class').val().trim();
        if (classValue && !/^\d{1,2}[А-ЯA-Z]$/.test(classValue)) {
            $('#class').addClass('border-red-300');
            hasErrors = true;
            alert('Класс должен быть в формате: цифра(цифры) и буква (например: 5А, 10Б)');
        }
        
        // Проверка даты рождения
        const birthDate = $('#birth_date').val();
        if (birthDate) {
            const date = new Date(birthDate);
            const today = new Date();
            const minDate = new Date();
            minDate.setFullYear(today.getFullYear() - 25);
            const maxDate = new Date();
            maxDate.setFullYear(today.getFullYear() - 3);
            
            if (date < minDate || date > maxDate) {
                $('#birth_date').addClass('border-red-300');
                hasErrors = true;
                alert('Дата рождения должна быть в диапазоне от 3 до 25 лет назад');
            }
        }
        
        if (hasErrors) {
            e.preventDefault();
            
            // Прокрутка к первой ошибке
            const firstError = $('.border-red-300').first();
            if (firstError.length) {
                $('html, body').animate({
                    scrollTop: firstError.offset().top - 100
                }, 500);
            }
            
            return false;
        }
        
        // Показать индикатор загрузки
        $('#loading').removeClass('hidden');
    });
    
    // Автозаполнение классов
    $('#class').on('input', function() {
        const input = $(this).val().toUpperCase();
        const datalist = $('#class-list');
        
        // Если введено более 1 символа, показываем подсказки
        if (input.length > 1) {
            // Здесь можно добавить AJAX запрос для получения подсказок
            // Пока используем статические данные
        }
    });
    
    // Автофокус на первом поле
    setTimeout(() => {
        if (!$('#last_name').val()) {
            $('#last_name').focus();
        }
    }, 100);
    
    // Обработка нажатия Enter для перехода между полями
    $('input:not([type="submit"]):not([type="button"])').keydown(function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const inputs = $('input:not([type="submit"]):not([type="button"]), textarea, select');
            const index = inputs.index(this);
            
            if (index > -1 && index < inputs.length - 1) {
                inputs.eq(index + 1).focus();
            }
        }
    });
    
    // Сохранение данных формы в localStorage (автосохранение)
    function saveFormData() {
        const formData = {
            last_name: $('#last_name').val(),
            first_name: $('#first_name').val(),
            class: $('#class').val(),
            birth_date: $('#birth_date').val(),
            gender: $('input[name="gender"]:checked').val(),
            parent_phone: $('#parent_phone').val(),
            parent_email: $('#parent_email').val(),
            address: $('#address').val(),
            notes: $('#notes').val(),
            health_info: $('#health_info').val()
        };
        
        localStorage.setItem('child_form_draft', JSON.stringify(formData));
    }
    
    function loadFormData() {
        const saved = localStorage.getItem('child_form_draft');
        if (saved) {
            try {
                const formData = JSON.parse(saved);
                
                $('#last_name').val(formData.last_name || '');
                $('#first_name').val(formData.first_name || '');
                $('#class').val(formData.class || '');
                $('#birth_date').val(formData.birth_date || '');
                if (formData.gender) {
                    $(`input[name="gender"][value="${formData.gender}"]`).prop('checked', true);
                }
                $('#parent_phone').val(formData.parent_phone || '');
                $('#parent_email').val(formData.parent_email || '');
                $('#address').val(formData.address || '');
                $('#notes').val(formData.notes || '');
                $('#health_info').val(formData.health_info || '');
                
                updateAgeDisplay();
                
                // Показать уведомление о восстановленных данных
                if (formData.last_name || formData.first_name) {
                    showNotification('Восстановлены несохраненные данные', 'info');
                }
            } catch (e) {
                console.error('Ошибка восстановления данных:', e);
            }
        }
    }
    
    // Загрузка сохраненных данных
    loadFormData();
    
    // Автосохранение каждые 30 секунд
    setInterval(saveFormData, 30000);
    
    // Сохранение при изменении
    $('input, textarea, select').on('input change', saveFormData);
    
    // Очистка сохраненных данных при успешной отправке
    $('form').on('submit', function() {
        localStorage.removeItem('child_form_draft');
    });
    
    // Подсказка для формата класса
    $('#class').on('focus', function() {
        $(this).attr('title', 'Примеры: 5А, 10Б, 11В');
    });
});
</script>

<!-- Стили -->
<style>
/* Кастомные стили для формы */
input:focus, textarea:focus, select:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Стили для радио-кнопок */
input[type="radio"]:checked {
    background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 16 16' fill='white' xmlns='http://www.w3.org/2000/svg'%3e%3ccircle cx='8' cy='8' r='3'/%3e%3c/svg%3e");
}

/* Стили для required полей */
label[required]::after {
    content: " *";
    color: #ef4444;
}

/* Анимация для предпросмотра */
#preview-modal {
    animation: fadeIn 0.3s ease-out;
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

/* Стили для валидации */
.border-red-300 {
    border-color: #fca5a5;
}

.border-red-300:focus {
    border-color: #fca5a5;
    box-shadow: 0 0 0 3px rgba(252, 165, 165, 0.1);
}

/* Стили для datalist */
datalist {
    position: absolute;
    background-color: white;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    max-height: 200px;
    overflow-y: auto;
}

/* Адаптивные стили */
@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .form-actions > * {
        width: 100%;
    }
}

/* Стили для текстовых областей */
textarea {
    resize: vertical;
    min-height: 80px;
}

/* Стили для иконок в полях ввода */
.input-with-icon {
    position: relative;
}

.input-with-icon i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
    pointer-events: none;
}

.input-with-icon input {
    padding-left: 40px;
}

/* Стили для секций формы */
.form-section {
    transition: all 0.3s ease;
}

.form-section:hover {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}
</style>
