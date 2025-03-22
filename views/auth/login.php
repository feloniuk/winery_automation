<?php
// views/auth/login.php
// Главная страница с формой авторизации

// Подключение контроллера авторизации
define('ROOT_PATH', dirname(dirname(__DIR__)));

// Подключаем файл инициализации
require_once ROOT_PATH . '/init.php';

// Подключение контроллера авторизации
require_once ROOT_PATH . '/controllers/AuthController.php';

$authController = new AuthController();

// Если пользователь уже авторизован, перенаправляем на соответствующую страницу
if ($authController->isLoggedIn()) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'admin':
            header('Location: http://winery_automation.loc/views/admin/dashboard.php');
            break;
        case 'warehouse':
            header('Location: http://winery_automation.loc/views/warehouse/dashboard.php');
            break;
        case 'purchasing':
            header('Location: http://winery_automation.loc/views/purchasing/dashboard.php');
            break;
        case 'supplier':
            header('Location: http://winery_automation.loc/views/supplier/dashboard.php');
            break;
    }
    exit;
}

// Обработка формы входа
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login']) && isset($_POST['password'])) {
        $result = $authController->login($_POST['login'], $_POST['password']);
        
        if ($result['success']) {
            $success = $result['message'];
            // Перенаправляем на соответствующую страницу
            switch ($result['role']) {
                case 'admin':
                    header('Location: http://winery_automation.loc/views/admin/dashboard.php');
                    break;
                case 'warehouse':
                    header('Location: http://winery_automation.loc/views/warehouse/dashboard.php');
                    break;
                case 'purchasing':
                    header('Location: http://winery_automation.loc/views/purchasing/dashboard.php');
                    break;
                case 'supplier':
                    header('Location: http://winery_automation.loc/views/supplier/dashboard.php');
                    break;
            }
            exit;
        } else {
            $error = $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Автоматизация винного производства - Авторизация</title>
    <!-- Подключение Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Дополнительные стили -->
    <style>
        body {
            background-image: url('https://images.unsplash.com/photo-1507434965515-63eec5e9b6e4?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80');
            background-size: cover;
            background-position: center;
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-md glass-effect p-8">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-purple-900">Винное производство</h1>
            <p class="text-gray-600 mt-2">Система автоматизации процессов</p>
        </div>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p><?php echo $error; ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p><?php echo $success; ?></p>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" class="space-y-6">
            <div>
                <label for="login" class="block text-sm font-medium text-gray-700">Логин</label>
                <input type="text" id="login" name="login" required 
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
            </div>
            
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Пароль</label>
                <input type="password" id="password" name="password" required 
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
            </div>
            
            <div>
                <button type="submit" 
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                    Войти в систему
                </button>
            </div>
        </form>
        
        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600">
                Вы поставщик и еще не зарегистрированы?
                <a href="views/auth/register.php" class="font-medium text-purple-600 hover:text-purple-500">
                    Зарегистрироваться
                </a>
            </p>
        </div>
        
        <div class="mt-8 text-center text-xs text-gray-500">
            <p>Демонстрационные учетные записи:</p>
            <p>Администратор: admin / password</p>
            <p>Склад: warehouse / password</p>
            <p>Закупки: purchasing / password</p>
            <p>Поставщик: supplier1 / password</p>
        </div>
    </div>
</body>
</html>