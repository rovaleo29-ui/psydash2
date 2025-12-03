<?php
/**
 * Контроллер для аутентификации пользователей
 * 
 * @package App\Controllers
 */

namespace App\Controllers;

use Core\Core;
use Core\Router;
use Exception;

class AuthController
{
    /**
     * @var Core Экземпляр ядра системы
     */
    private $core;
    
    /**
     * @var Router Экземпляр маршрутизатора
     */
    private $router;
    
    /**
     * Конструктор контроллера
     * 
     * @param Core $core Экземпляр ядра системы
     */
    public function __construct(Core $core)
    {
        $this->core = $core;
        $this->router = new Router();
    }
    
    /**
     * Отображение формы входа
     */
    public function showLogin(): void
    {
        // Если пользователь уже авторизован, перенаправляем на дашборд
        if ($this->core->isAuthenticated()) {
            $this->router->redirect('/dashboard');
            return;
        }
        
        // Отображение страницы входа
        $content = $this->core->getTemplate()->render('auth/login.php');
        echo $this->core->renderPage($content, 'Вход в систему');
    }
    
    /**
     * Обработка входа пользователя
     */
    public function login(): void
    {
        // Проверка CSRF токена
        if (!$this->validateCsrfToken()) {
            $this->showLoginWithError('Недействительный токен безопасности. Пожалуйста, обновите страницу.');
            return;
        }
        
        // Получение данных из формы
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        
        // Валидация входных данных
        $errors = $this->validateLoginInput($username, $password);
        
        if (!empty($errors)) {
            $this->showLoginWithErrors($errors, $username);
            return;
        }
        
        try {
            // Попытка входа пользователя
            if ($this->core->login($username, $password, $remember)) {
                // Успешный вход
                $this->router->redirect('/dashboard');
                return;
            } else {
                // Неверные учетные данные
                $this->showLoginWithError('Неверное имя пользователя или пароль');
                return;
            }
            
        } catch (Exception $e) {
            // Ошибка при входе
            error_log("Ошибка входа: " . $e->getMessage());
            $this->showLoginWithError('Произошла ошибка при входе. Пожалуйста, попробуйте позже.');
            return;
        }
    }
    
    /**
     * Выход пользователя из системы
     */
    public function logout(): void
    {
        $this->core->logout();
        $this->router->redirect('/login');
    }
    
    /**
     * Валидация CSRF токена
     * 
     * @return bool
     */
    private function validateCsrfToken(): bool
    {
        $token = $_POST['csrf_token'] ?? '';
        return $this->core->getTemplate()->verifyCsrfToken($token);
    }
    
    /**
     * Валидация входных данных для входа
     * 
     * @param string $username Имя пользователя
     * @param string $password Пароль
     * @return array Массив ошибок
     */
    private function validateLoginInput(string $username, string $password): array
    {
        $errors = [];
        
        // Проверка имени пользователя
        if (empty($username)) {
            $errors['username'] = 'Имя пользователя обязательно для заполнения';
        } elseif (strlen($username) < 3) {
            $errors['username'] = 'Имя пользователя должно содержать не менее 3 символов';
        } elseif (strlen($username) > 100) {
            $errors['username'] = 'Имя пользователя должно содержать не более 100 символов';
        }
        
        // Проверка пароля
        if (empty($password)) {
            $errors['password'] = 'Пароль обязателен для заполнения';
        } elseif (strlen($password) < 6) {
            $errors['password'] = 'Пароль должен содержать не менее 6 символов';
        }
        
        return $errors;
    }
    
    /**
     * Отображение формы входа с ошибкой
     * 
     * @param string $errorMessage Сообщение об ошибке
     */
    private function showLoginWithError(string $errorMessage): void
    {
        $template = $this->core->getTemplate();
        $data = [
            'error' => $errorMessage,
            'username' => $_POST['username'] ?? ''
        ];
        
        $content = $template->render('auth/login.php', $data);
        echo $this->core->renderPage($content, 'Вход в систему');
    }
    
    /**
     * Отображение формы входа с несколькими ошибками
     * 
     * @param array $errors Массив ошибок
     * @param string $username Имя пользователя
     */
    private function showLoginWithErrors(array $errors, string $username): void
    {
        $template = $this->core->getTemplate();
        $data = [
            'errors' => $errors,
            'username' => $username
        ];
        
        $content = $template->render('auth/login.php', $data);
        echo $this->core->renderPage($content, 'Вход в систему');
    }
    
    /**
     * Отображение страницы восстановления пароля
     */
    public function showForgotPassword(): void
    {
        // Если пользователь уже авторизован, перенаправляем на дашборд
        if ($this->core->isAuthenticated()) {
            $this->router->redirect('/dashboard');
            return;
        }
        
        // Проверка возможности восстановления пароля
        $mailEnabled = $this->core->getConfig('mail')['enabled'] ?? false;
        
        if (!$mailEnabled) {
            $content = $this->core->getTemplate()->render('auth/forgot-password-disabled.php');
            echo $this->core->renderPage($content, 'Восстановление пароля');
            return;
        }
        
        $content = $this->core->getTemplate()->render('auth/forgot-password.php');
        echo $this->core->renderPage($content, 'Восстановление пароля');
    }
    
    /**
     * Обработка запроса на восстановление пароля
     */
    public function forgotPassword(): void
    {
        // Проверка CSRF токена
        if (!$this->validateCsrfToken()) {
            $this->showForgotPasswordWithError('Недействительный токен безопасности');
            return;
        }
        
        $email = $_POST['email'] ?? '';
        
        // Валидация email
        if (empty($email)) {
            $this->showForgotPasswordWithError('Email обязателен для заполнения');
            return;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->showForgotPasswordWithError('Введите корректный email адрес');
            return;
        }
        
        try {
            // Поиск пользователя по email
            $db = $this->core->getDB();
            $user = $db->fetch(
                "SELECT id, email, full_name FROM users WHERE email = :email AND is_active = 1",
                [':email' => $email]
            );
            
            if ($user) {
                // Генерация токена сброса пароля
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600); // 1 час
                
                // Сохранение токена в БД
                $db->insert('password_resets', [
                    'user_id' => $user['id'],
                    'token' => hash('sha256', $token),
                    'expires_at' => $expires,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                // Отправка email с ссылкой для сброса пароля
                $resetLink = $this->router->getBaseUrl() . '/reset-password?token=' . $token;
                
                // Здесь должна быть логика отправки email
                // Для простоты просто логируем
                error_log("Ссылка для сброса пароля для {$email}: {$resetLink}");
            }
            
            // Всегда показываем успешное сообщение (даже если email не найден)
            // Это предотвращает утечку информации о существовании email
            $this->showForgotPasswordSuccess();
            
        } catch (Exception $e) {
            error_log("Ошибка восстановления пароля: " . $e->getMessage());
            $this->showForgotPasswordWithError('Произошла ошибка. Пожалуйста, попробуйте позже.');
        }
    }
    
    /**
     * Отображение формы восстановления пароля с ошибкой
     * 
     * @param string $errorMessage Сообщение об ошибке
     */
    private function showForgotPasswordWithError(string $errorMessage): void
    {
        $template = $this->core->getTemplate();
        $data = [
            'error' => $errorMessage,
            'email' => $_POST['email'] ?? ''
        ];
        
        $content = $template->render('auth/forgot-password.php', $data);
        echo $this->core->renderPage($content, 'Восстановление пароля');
    }
    
    /**
     * Отображение успешного сообщения о восстановлении пароля
     */
    private function showForgotPasswordSuccess(): void
    {
        $content = $this->core->getTemplate()->render('auth/forgot-password-success.php');
        echo $this->core->renderPage($content, 'Восстановление пароля');
    }
    
    /**
     * Отображение формы сброса пароля
     */
    public function showResetPassword(): void
    {
        $token = $_GET['token'] ?? '';
        
        if (empty($token)) {
            $this->router->redirect('/forgot-password');
            return;
        }
        
        // Проверка валидности токена
        $isValid = $this->validateResetToken($token);
        
        if (!$isValid) {
            $this->showResetPasswordError('Недействительная или просроченная ссылка для сброса пароля');
            return;
        }
        
        $template = $this->core->getTemplate();
        $data = ['token' => $token];
        
        $content = $template->render('auth/reset-password.php', $data);
        echo $this->core->renderPage($content, 'Сброс пароля');
    }
    
    /**
     * Обработка сброса пароля
     */
    public function resetPassword(): void
    {
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        
        // Проверка CSRF токена
        if (!$this->validateCsrfToken()) {
            $this->showResetPasswordWithError('Недействительный токен безопасности', $token);
            return;
        }
        
        // Валидация токена
        $isValid = $this->validateResetToken($token);
        
        if (!$isValid) {
            $this->showResetPasswordError('Недействительная или просроченная ссылка для сброса пароля');
            return;
        }
        
        // Валидация пароля
        $errors = $this->validatePassword($password, $passwordConfirm);
        
        if (!empty($errors)) {
            $this->showResetPasswordWithErrors($errors, $token);
            return;
        }
        
        try {
            $db = $this->core->getDB();
            
            // Поиск записи сброса пароля
            $resetRecord = $db->fetch(
                "SELECT * FROM password_resets WHERE token = :token AND expires_at > NOW()",
                [':token' => hash('sha256', $token)]
            );
            
            if (!$resetRecord) {
                $this->showResetPasswordError('Недействительная или просроченная ссылка для сброса пароля');
                return;
            }
            
            // Обновление пароля пользователя
            $auth = $this->core->getAuth();
            $auth->changePassword($resetRecord['user_id'], $password);
            
            // Удаление использованного токена
            $db->delete('password_resets', ['id' => $resetRecord['id']]);
            
            // Удаление всех других токенов этого пользователя
            $db->query(
                "DELETE FROM password_resets WHERE user_id = :user_id",
                [':user_id' => $resetRecord['user_id']]
            );
            
            // Показ страницы успеха
            $content = $this->core->getTemplate()->render('auth/reset-password-success.php');
            echo $this->core->renderPage($content, 'Пароль изменен');
            
        } catch (Exception $e) {
            error_log("Ошибка сброса пароля: " . $e->getMessage());
            $this->showResetPasswordWithError('Произошла ошибка. Пожалуйста, попробуйте позже.', $token);
        }
    }
    
    /**
     * Валидация токена сброса пароля
     * 
     * @param string $token Токен
     * @return bool
     */
    private function validateResetToken(string $token): bool
    {
        if (empty($token) || strlen($token) !== 64) {
            return false;
        }
        
        try {
            $db = $this->core->getDB();
            $record = $db->fetch(
                "SELECT COUNT(*) as count FROM password_resets WHERE token = :token AND expires_at > NOW()",
                [':token' => hash('sha256', $token)]
            );
            
            return $record && $record['count'] > 0;
            
        } catch (Exception $e) {
            error_log("Ошибка проверки токена: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Валидация пароля
     * 
     * @param string $password Пароль
     * @param string $passwordConfirm Подтверждение пароля
     * @return array Массив ошибок
     */
    private function validatePassword(string $password, string $passwordConfirm): array
    {
        $errors = [];
        
        // Проверка пароля
        if (empty($password)) {
            $errors['password'] = 'Пароль обязателен для заполнения';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'Пароль должен содержать не менее 8 символов';
        }
        
        // Проверка подтверждения пароля
        if (empty($passwordConfirm)) {
            $errors['password_confirm'] = 'Подтверждение пароля обязательно';
        } elseif ($password !== $passwordConfirm) {
            $errors['password_confirm'] = 'Пароли не совпадают';
        }
        
        return $errors;
    }
    
    /**
     * Отображение формы сброса пароля с ошибкой
     * 
     * @param string $errorMessage Сообщение об ошибке
     * @param string $token Токен сброса
     */
    private function showResetPasswordWithError(string $errorMessage, string $token): void
    {
        $template = $this->core->getTemplate();
        $data = [
            'error' => $errorMessage,
            'token' => $token
        ];
        
        $content = $template->render('auth/reset-password.php', $data);
        echo $this->core->renderPage($content, 'Сброс пароля');
    }
    
    /**
     * Отображение формы сброса пароля с несколькими ошибками
     * 
     * @param array $errors Массив ошибок
     * @param string $token Токен сброса
     */
    private function showResetPasswordWithErrors(array $errors, string $token): void
    {
        $template = $this->core->getTemplate();
        $data = [
            'errors' => $errors,
            'token' => $token
        ];
        
        $content = $template->render('auth/reset-password.php', $data);
        echo $this->core->renderPage($content, 'Сброс пароля');
    }
    
    /**
     * Отображение ошибки сброса пароля
     * 
     * @param string $errorMessage Сообщение об ошибке
     */
    private function showResetPasswordError(string $errorMessage): void
    {
        $content = $this->core->getTemplate()->render('auth/reset-password-error.php', [
            'error' => $errorMessage
        ]);
        echo $this->core->renderPage($content, 'Ошибка сброса пароля');
    }
}
