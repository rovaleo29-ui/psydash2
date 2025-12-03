<?php
/**
 * Шаблон страницы входа в систему
 * 
 * @package Templates\Auth
 */

use Core\Template;

/**
 * @var Template $this Объект шаблонизатора
 * @var array $data Данные для шаблона
 * @var array $errors Массив ошибок валидации
 * @var string $username Введенное имя пользователя
 * @var string $error Общая ошибка
 */

// Извлечение переменных из массива $data
extract($data);
?>
<div class="min-h-full flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <!-- Заголовок -->
        <div class="text-center">
            <div class="flex justify-center">
                <div class="bg-blue-100 p-3 rounded-full">
                    <i class="fas fa-brain text-blue-600 text-4xl"></i>
                </div>
            </div>
            <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
                Вход в систему
            </h2>
            <p class="mt-2 text-sm text-gray-600">
                Система управления психологическими данными
            </p>
        </div>

        <!-- Форма входа -->
        <form class="mt-8 space-y-6" action="/login" method="POST">
            <?php $this->csrfField() ?>
            
            <!-- Сообщения об ошибках -->
            <?php if (isset($error) && $error): ?>
            <div class="rounded-md bg-red-50 p-4 alert-auto-close">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-400"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Ошибка входа</h3>
                        <div class="mt-2 text-sm text-red-700">
                            <p><?= $this->escape($error) ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['expired']) && $_GET['expired'] == '1'): ?>
            <div class="rounded-md bg-yellow-50 p-4 alert-auto-close">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">Сессия истекла</h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <p>Пожалуйста, войдите снова для продолжения работы.</p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['logged_out']) && $_GET['logged_out'] == '1'): ?>
            <div class="rounded-md bg-green-50 p-4 alert-auto-close">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-400"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-green-800">Выход выполнен</h3>
                        <div class="mt-2 text-sm text-green-700">
                            <p>Вы успешно вышли из системы.</p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['registered']) && $_GET['registered'] == '1'): ?>
            <div class="rounded-md bg-green-50 p-4 alert-auto-close">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-400"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-green-800">Регистрация успешна</h3>
                        <div class="mt-2 text-sm text-green-700">
                            <p>Теперь вы можете войти в систему с вашими учетными данными.</p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Поля формы -->
            <div class="rounded-md shadow-sm -space-y-px">
                <!-- Имя пользователя -->
                <div>
                    <label for="username" class="sr-only">Имя пользователя или ID психолога</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-user text-gray-400"></i>
                        </div>
                        <input id="username" 
                               name="username" 
                               type="text" 
                               value="<?= isset($username) ? $this->escape($username) : '' ?>" 
                               required 
                               class="appearance-none rounded-none relative block w-full px-3 py-3 pl-10 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm <?= isset($errors['username']) ? 'border-red-300' : '' ?>" 
                               placeholder="Имя пользователя или ID психолога">
                    </div>
                    <?php if (isset($errors['username'])): ?>
                    <p class="mt-1 text-sm text-red-600"><?= $this->escape($errors['username']) ?></p>
                    <?php endif; ?>
                </div>

                <!-- Пароль -->
                <div>
                    <label for="password" class="sr-only">Пароль</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input id="password" 
                               name="password" 
                               type="password" 
                               required 
                               class="appearance-none rounded-none relative block w-full px-3 py-3 pl-10 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm <?= isset($errors['password']) ? 'border-red-300' : '' ?>" 
                               placeholder="Пароль">
                        <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center toggle-password">
                            <i class="fas fa-eye text-gray-400 hover:text-gray-600"></i>
                        </button>
                    </div>
                    <?php if (isset($errors['password'])): ?>
                    <p class="mt-1 text-sm text-red-600"><?= $this->escape($errors['password']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Дополнительные опции -->
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <input id="remember" 
                           name="remember" 
                           type="checkbox" 
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="remember" class="ml-2 block text-sm text-gray-900">
                        Запомнить меня
                    </label>
                </div>

                <div class="text-sm">
                    <a href="/forgot-password" class="font-medium text-blue-600 hover:text-blue-500">
                        Забыли пароль?
                    </a>
                </div>
            </div>

            <!-- Кнопка входа -->
            <div>
                <button type="submit" class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <i class="fas fa-sign-in-alt text-blue-500 group-hover:text-blue-400"></i>
                    </span>
                    Войти в систему
                </button>
            </div>

            <!-- Информация для входа -->
            <div class="rounded-md bg-gray-50 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-gray-400"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-gray-800">Информация для входа</h3>
                        <div class="mt-2 text-sm text-gray-700">
                            <p>Для входа используйте:</p>
                            <ul class="list-disc list-inside mt-1 space-y-1">
                                <li>Ваш ID психолога (например: 1001)</li>
                                <li>Или ваш email адрес</li>
                                <li>И пароль, выданный администратором</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <!-- Ссылка на демо-доступ -->
        <div class="text-center">
            <p class="text-sm text-gray-600">
                Нет доступа к системе? 
                <a href="#" class="font-medium text-blue-600 hover:text-blue-500 demo-login">
                    Попробовать демо-версию
                </a>
            </p>
        </div>
    </div>
</div>

<!-- Демо-модальное окно -->
<div id="demo-modal" class="fixed z-10 inset-0 overflow-y-auto hidden">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Overlay -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

        <!-- Модальное окно -->
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fas fa-eye text-blue-600"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            Демо-доступ
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500">
                                Вы можете войти в демо-режим для ознакомления с системой. 
                                Демо-режим имеет ограниченный функционал и доступен на 30 минут.
                            </p>
                            <div class="mt-4 bg-gray-50 p-4 rounded-lg">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-sm font-medium text-gray-700">Логин:</p>
                                        <p class="text-sm text-gray-900 font-mono mt-1">demo_user</p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-700">Пароль:</p>
                                        <p class="text-sm text-gray-900 font-mono mt-1">demo123</p>
                                    </div>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-4">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                Все изменения в демо-режиме будут сброшены после выхода.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm demo-login-btn">
                    Войти в демо-режим
                </button>
                <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm close-demo-modal">
                    Отмена
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Переключение видимости пароля
    $('.toggle-password').click(function() {
        const passwordInput = $(this).closest('.relative').find('input');
        const icon = $(this).find('i');
        
        if (passwordInput.attr('type') === 'password') {
            passwordInput.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            passwordInput.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });
    
    // Демо-режим
    $('.demo-login').click(function(e) {
        e.preventDefault();
        $('#demo-modal').removeClass('hidden');
    });
    
    $('.close-demo-modal').click(function() {
        $('#demo-modal').addClass('hidden');
    });
    
    $('.demo-login-btn').click(function() {
        // Заполняем форму демо-данными
        $('#username').val('demo_user');
        $('#password').val('demo123');
        $('#remember').prop('checked', true);
        
        // Скрываем модальное окно
        $('#demo-modal').addClass('hidden');
        
        // Фокусируемся на кнопке входа
        $('button[type="submit"]').focus();
        
        // Показываем уведомление
        setTimeout(() => {
            showNotification('Форма заполнена демо-данными. Нажмите "Войти в систему".', 'info');
        }, 300);
    });
    
    // Закрытие модального окна по клику вне его
    $('#demo-modal').click(function(e) {
        if (e.target === this) {
            $(this).addClass('hidden');
        }
    });
    
    // Автофокус на поле ввода
    setTimeout(() => {
        if (!$('#username').val()) {
            $('#username').focus();
        } else {
            $('#password').focus();
        }
    }, 100);
    
    // Обработка нажатия Enter
    $('#username, #password').keypress(function(e) {
        if (e.which === 13) {
            if ($(this).attr('id') === 'username') {
                $('#password').focus();
            } else {
                $('form').submit();
            }
            e.preventDefault();
        }
    });
    
    // Проверка браузера
    if (!window.Promise || !window.fetch || !window.localStorage) {
        showNotification('Ваш браузер устарел. Некоторые функции могут работать некорректно.', 'warning');
    }
});
</script>

<style>
/* Стили для страницы входа */
.toggle-password {
    cursor: pointer;
    outline: none;
}

.toggle-password:focus {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
}

#demo-modal {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

/* Анимация для сообщений */
.alert-auto-close {
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        transform: translateY(-10px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Стили для фокуса */
input:focus, button:focus {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
}

/* Стили для чекбокса */
input[type="checkbox"]:focus {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
}
</style>
