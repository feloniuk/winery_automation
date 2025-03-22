<?php
// views/admin/settings.php
// Страница настроек системы для администратора

// Подключение контроллеров
require_once '../../controllers/AuthController.php';
require_once '../../controllers/AdminController.php';

$authController = new AuthController();
$adminController = new AdminController();

// Проверка авторизации и роли
if (!$authController->isLoggedIn() || !$authController->checkRole('admin')) {
    header('Location: ../../index.php');
    exit;
}

// Получение данных для страницы
$currentUser = $authController->getCurrentUser();

// Обработка действий для настроек
$message = '';
$error = '';

// Создание резервной копии
if (isset($_POST['create_backup'])) {
    $result = $adminController->createBackup();
    if ($result['success']) {
        $message = "Резервная копия успешно создана: " . $result['filename'];
    } else {
        $error = "Ошибка при создании резервной копии";
    }
}

// Обновление параметров системы (заглушка, в реальной системе здесь был бы код для сохранения настроек)
if (isset($_POST['update_settings'])) {
    $message = "Настройки системы успешно обновлены";
}

// Обновление параметров уведомлений (заглушка, в реальной системе здесь был бы код для сохранения настроек уведомлений)
if (isset($_POST['update_notifications'])) {
    $message = "Настройки уведомлений успешно обновлены";
}

// Обновление данных аккаунта администратора
if (isset($_POST['update_profile'])) {
    // В реальной системе здесь был бы код обновления профиля
    $message = "Данные профиля успешно обновлены";
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Настройки - Панель администратора</title>
    <!-- Подключение Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Иконки -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Верхняя навигационная панель -->
    <nav class="bg-indigo-800 text-white p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center">
                <i class="fas fa-wine-bottle text-2xl mr-3"></i>
                <h1 class="text-xl font-bold">Винное производство</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span><?php echo htmlspecialchars($currentUser['name']); ?> (Администратор)</span>
                <a href="../../controllers/logout.php" class="bg-indigo-700 hover:bg-indigo-600 py-2 px-4 rounded text-sm">
                    <i class="fas fa-sign-out-alt mr-1"></i> Выйти
                </a>
            </div>
        </div>
    </nav>
    
    <!-- Боковая панель и основной контент -->
    <div class="container mx-auto flex flex-wrap mt-6 px-4">
        <!-- Боковая навигация -->
        <aside class="w-full md:w-1/4 pr-0 md:pr-6">
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <div class="flex items-center mb-4 pb-4 border-b border-gray-200">
                    <div class="bg-indigo-100 p-3 rounded-full mr-4">
                        <i class="fas fa-user-shield text-indigo-600"></i>
                    </div>
                    <div>
                        <p class="font-semibold"><?php echo htmlspecialchars($currentUser['name']); ?></p>
                        <p class="text-sm text-gray-500">Администратор системы</p>
                    </div>
                </div>
                
                <ul class="space-y-2">
                    <li>
                        <a href="dashboard.php" class="flex items-center p-2 text-gray-700 hover:bg-indigo-50 rounded font-medium">
                            <i class="fas fa-tachometer-alt w-5 mr-2"></i>
                            <span>Панель управления</span>
                        </a>
                    </li>
                    <li>
                        <a href="users.php" class="flex items-center p-2 text-gray-700 hover:bg-indigo-50 rounded font-medium">
                            <i class="fas fa-users w-5 mr-2"></i>
                            <span>Пользователи</span>
                        </a>
                    </li>
                    <li>
                        <a href="cameras.php" class="flex items-center p-2 text-gray-700 hover:bg-indigo-50 rounded font-medium">
                            <i class="fas fa-video w-5 mr-2"></i>
                            <span>Камеры наблюдения</span>
                        </a>
                    </li>
                    <li>
                        <a href="warehouse.php" class="flex items-center p-2 text-gray-700 hover:bg-indigo-50 rounded font-medium">
                            <i class="fas fa-boxes w-5 mr-2"></i>
                            <span>Склад</span>
                        </a>
                    </li>
                    <li>
                        <a href="purchasing.php" class="flex items-center p-2 text-gray-700 hover:bg-indigo-50 rounded font-medium">
                            <i class="fas fa-shopping-cart w-5 mr-2"></i>
                            <span>Закупки</span>
                        </a>
                    </li>
                    <li>
                        <a href="reports.php" class="flex items-center p-2 text-gray-700 hover:bg-indigo-50 rounded font-medium">
                            <i class="fas fa-chart-bar w-5 mr-2"></i>
                            <span>Отчеты</span>
                        </a>
                    </li>
                    <li>
                        <a href="settings.php" class="flex items-center p-2 bg-indigo-100 text-indigo-700 rounded font-medium">
                            <i class="fas fa-cog w-5 mr-2"></i>
                            <span>Настройки</span>
                        </a>
                    </li>
                </ul>
            </div>
            
            
            <!-- Информация о системе -->
            <div class="bg-white rounded-lg shadow-md p-4">
                <h3 class="font-semibold text-lg mb-3">Информация о системе</h3>
                <ul class="space-y-3">
                    <li class="flex justify-between">
                        <span class="text-gray-600">Версия:</span>
                        <span class="font-semibold">1.2.5</span>
                    </li>
                    <li class="flex justify-between">
                        <span class="text-gray-600">Последнее обновление:</span>
                        <span class="font-semibold"><?php echo date('d.m.Y'); ?></span>
                    </li>
                    <li class="flex justify-between">
                        <span class="text-gray-600">Лицензия:</span>
                        <span class="font-semibold">Enterprise</span>
                    </li>
                    <li class="flex justify-between">
                        <span class="text-gray-600">Поддержка до:</span>
                        <span class="font-semibold"><?php echo date('d.m.Y', strtotime('+1 year')); ?></span>
                    </li>
                </ul>
            </div>
        </aside>
        
        <!-- Основной контент -->
        <main class="w-full md:w-3/4">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">Настройки системы</h2>
                
                <?php if ($message): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p><?php echo $message; ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p><?php echo $error; ?></p>
                </div>
                <?php endif; ?>
                
                <!-- Вкладки для настроек -->
                <div class="border-b border-gray-200 mb-6">
                    <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                        <a href="#system-settings" class="tab-link border-indigo-500 text-indigo-600 whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm" onclick="showTab('system-settings')">
                            Системные настройки
                        </a>
                        <a href="#notification-settings" class="tab-link border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm" onclick="showTab('notification-settings')">
                            Уведомления
                        </a>
                        <a href="#account-settings" class="tab-link border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm" onclick="showTab('account-settings')">
                            Аккаунт
                        </a>
                        <a href="#backup-settings" class="tab-link border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm" onclick="showTab('backup-settings')">
                            Резервное копирование
                        </a>
                    </nav>
                </div>
                
                <!-- Содержимое вкладок -->
                <div id="system-settings" class="tab-content block">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Системные настройки</h3>
                    <form method="POST" action="">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="company_name" class="block text-sm font-medium text-gray-700">Название компании</label>
                                <input type="text" id="company_name" name="company_name" value="Винное производство" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="system_email" class="block text-sm font-medium text-gray-700">Email системы</label>
                                <input type="email" id="system_email" name="system_email" value="system@winery.example" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="timezone" class="block text-sm font-medium text-gray-700">Часовой пояс</label>
                                <select id="timezone" name="timezone" 
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="Europe/Kyiv" selected>Киев (UTC+2)</option>
                                </select>
                            </div>
                            <div>
                                <label for="date_format" class="block text-sm font-medium text-gray-700">Формат даты</label>
                                <select id="date_format" name="date_format" 
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="d.m.Y" selected>DD.MM.YYYY (31.12.2023)</option>
                                    <option value="Y-m-d">YYYY-MM-DD (2023-12-31)</option>
                                    <option value="d/m/Y">DD/MM/YYYY (31/12/2023)</option>
                                    <option value="m/d/Y">MM/DD/YYYY (12/31/2023)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="border-t border-gray-200 pt-6">
                            <h4 class="text-md font-medium text-gray-900 mb-3">Настройки безопасности</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <div>
                                    <label for="session_lifetime" class="block text-sm font-medium text-gray-700">Время жизни сессии (минут)</label>
                                    <input type="number" id="session_lifetime" name="session_lifetime" value="120" min="15" max="480" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                </div>
                                <div>
                                    <label for="password_policy" class="block text-sm font-medium text-gray-700">Политика паролей</label>
                                    <select id="password_policy" name="password_policy" 
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="simple">Простая (минимум 6 символов)</option>
                                        <option value="medium" selected>Средняя (минимум 8 символов, цифры)</option>
                                        <option value="strong">Строгая (минимум 10 символов, цифры, спец. символы)</option>
                                    </select>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" id="force_password_change" name="force_password_change" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    <label for="force_password_change" class="ml-2 block text-sm text-gray-700">
                                        Принудительная смена пароля каждые 90 дней
                                    </label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" id="enable_2fa" name="enable_2fa" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    <label for="enable_2fa" class="ml-2 block text-sm text-gray-700">
                                        Включить двухфакторную аутентификацию
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-end mt-6">
                            <button type="submit" name="update_settings" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-6 rounded-md">
                                Сохранить настройки
                            </button>
                        </div>
                    </form>
                </div>
                
                <div id="notification-settings" class="tab-content hidden">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Настройки уведомлений</h3>
                    <form method="POST" action="">
                        <div class="space-y-4 mb-6">
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                                <div>
                                    <h4 class="font-medium text-gray-900">Низкий запас товаров</h4>
                                    <p class="text-sm text-gray-500">Уведомления о товарах с запасом ниже минимального</p>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="low_stock_email" checked class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm text-gray-700">Email</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="low_stock_sms" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm text-gray-700">SMS</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="low_stock_push" checked class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm text-gray-700">Уведомления в системе</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                                <div>
                                    <h4 class="font-medium text-gray-900">Новые заказы</h4>
                                    <p class="text-sm text-gray-500">Уведомления о создании новых заказов</p>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="new_order_email" checked class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm text-gray-700">Email</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="new_order_sms" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm text-gray-700">SMS</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="new_order_push" checked class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm text-gray-700">Уведомления в системе</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                                <div>
                                    <h4 class="font-medium text-gray-900">Проблемы с камерами</h4>
                                    <p class="text-sm text-gray-500">Уведомления о неактивных камерах наблюдения</p>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="camera_issue_email" checked class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm text-gray-700">Email</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="camera_issue_sms" checked class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm text-gray-700">SMS</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="camera_issue_push" checked class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm text-gray-700">Уведомления в системе</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                                <div>
                                    <h4 class="font-medium text-gray-900">Отчеты</h4>
                                    <p class="text-sm text-gray-500">Еженедельные отчеты о состоянии системы</p>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="reports_email" checked class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm text-gray-700">Email</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="reports_sms" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm text-gray-700">SMS</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="reports_push" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm text-gray-700">Уведомления в системе</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="border-t border-gray-200 pt-6">
                            <h4 class="text-md font-medium text-gray-900 mb-3">Контакты для уведомлений</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <div>
                                    <label for="notification_email" class="block text-sm font-medium text-gray-700">Email для уведомлений</label>
                                    <input type="email" id="notification_email" name="notification_email" value="admin@winery.example" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                </div>
                                <div>
                                    <label for="notification_phone" class="block text-sm font-medium text-gray-700">Телефон для SMS</label>
                                    <input type="tel" id="notification_phone" name="notification_phone" value="+7 (999) 123-45-67" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-end mt-6">
                            <button type="submit" name="update_notifications" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-6 rounded-md">
                                Сохранить настройки уведомлений
                            </button>
                        </div>
                    </form>
                </div>
                
                <div id="account-settings" class="tab-content hidden">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Настройки аккаунта</h3>
                    <form method="POST" action="">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="account_name" class="block text-sm font-medium text-gray-700">ФИО</label>
                                <input type="text" id="account_name" name="account_name" value="<?php echo htmlspecialchars($currentUser['name']); ?>" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="account_email" class="block text-sm font-medium text-gray-700">Email</label>
                                <input type="email" id="account_email" name="account_email" value="<?php echo htmlspecialchars($currentUser['email']); ?>" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="account_username" class="block text-sm font-medium text-gray-700">Логин</label>
                                <input type="text" id="account_username" name="account_username" value="<?php echo htmlspecialchars($currentUser['username']); ?>" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm bg-gray-100" readonly>
                                <p class="mt-1 text-xs text-gray-500">Логин изменить нельзя</p>
                            </div>
                            <div>
                                <label for="account_password" class="block text-sm font-medium text-gray-700">Новый пароль</label>
                                <input type="password" id="account_password" name="account_password" placeholder="Оставьте пустым, чтобы не менять" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="account_password_confirm" class="block text-sm font-medium text-gray-700">Подтверждение пароля</label>
                                <input type="password" id="account_password_confirm" name="account_password_confirm" placeholder="Подтвердите новый пароль" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                        </div>
                        
                        <div class="border-t border-gray-200 pt-6">
                            <h4 class="text-md font-medium text-gray-900 mb-3">Настройки интерфейса</h4>
                            <div class="grid grid-cols-1 gap-6 mb-6">
                                <div>
                                    <label for="interface_theme" class="block text-sm font-medium text-gray-700">Тема интерфейса</label>
                                    <select id="interface_theme" name="interface_theme" 
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="light" selected>Светлая</option>
                                        <option value="dark">Темная</option>
                                        <option value="system">Системная</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="items_per_page" class="block text-sm font-medium text-gray-700">Элементов на странице</label>
                                    <select id="items_per_page" name="items_per_page" 
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="10">10</option>
                                        <option value="25" selected>25</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                    </select>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" id="enable_animations" name="enable_animations" checked class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    <label for="enable_animations" class="ml-2 block text-sm text-gray-700">
                                        Включить анимации интерфейса
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-end mt-6">
                            <button type="submit" name="update_profile" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-6 rounded-md">
                                Сохранить настройки аккаунта
                            </button>
                        </div>
                    </form>
                </div>
                
                <div id="backup-settings" class="tab-content hidden">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Резервное копирование</h3>
                    
                    <div class="mb-6">
                        <h4 class="text-md font-medium text-gray-700 mb-3">Создать резервную копию</h4>
                        <p class="text-sm text-gray-500 mb-4">Создайте полную резервную копию базы данных системы. Это поможет восстановить работу системы в случае сбоя.</p>
                        <form method="POST" action="" class="mb-3">
                            <button type="submit" name="create_backup" class="flex items-center bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded">
                                <i class="fas fa-database mr-2"></i> Создать резервную копию
                            </button>
                        </form>
                    </div>
                    
                    <div class="mb-6">
                        <h4 class="text-md font-medium text-gray-700 mb-3">Автоматические резервные копии</h4>
                        <div class="bg-gray-50 p-4 rounded mb-4">
                            <div class="flex items-center mb-4">
                                <input type="checkbox" id="enable_auto_backup" name="enable_auto_backup" checked class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="enable_auto_backup" class="ml-2 block text-sm font-medium text-gray-700">
                                    Включить автоматическое резервное копирование
                                </label>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="backup_frequency" class="block text-sm font-medium text-gray-700">Частота</label>
                                    <select id="backup_frequency" name="backup_frequency" 
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="daily">Ежедневно</option>
                                        <option value="weekly" selected>Еженедельно</option>
                                        <option value="monthly">Ежемесячно</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="backup_time" class="block text-sm font-medium text-gray-700">Время</label>
                                    <input type="time" id="backup_time" name="backup_time" value="02:00" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                </div>
                                <div>
                                    <label for="backup_retention" class="block text-sm font-medium text-gray-700">Хранить копии</label>
                                    <select id="backup_retention" name="backup_retention" 
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="7">7 дней</option>
                                        <option value="14">14 дней</option>
                                        <option value="30" selected>30 дней</option>
                                        <option value="90">90 дней</option>
                                        <option value="365">1 год</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" name="update_backup_settings" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded">
                                Сохранить настройки
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <h4 class="text-md font-medium text-gray-700 mb-3">История резервных копий</h4>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Имя файла
                                        </th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Дата создания
                                        </th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Размер
                                        </th>
                                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Действия
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <!-- В реальной системе здесь был бы цикл по реальным резервным копиям -->
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                            backup_2023-03-22_02-00-00.sql
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            22.03.2023 02:00
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            5.4 MB
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="#" class="text-indigo-600 hover:text-indigo-900 mr-3">Скачать</a>
                                            <a href="#" class="text-red-600 hover:text-red-900">Удалить</a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                            backup_2023-03-15_02-00-00.sql
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            15.03.2023 02:00
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            5.2 MB
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="#" class="text-indigo-600 hover:text-indigo-900 mr-3">Скачать</a>
                                            <a href="#" class="text-red-600 hover:text-red-900">Удалить</a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                            backup_2023-03-08_02-00-00.sql
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            08.03.2023 02:00
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            5.1 MB
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="#" class="text-indigo-600 hover:text-indigo-900 mr-3">Скачать</a>
                                            <a href="#" class="text-red-600 hover:text-red-900">Удалить</a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <footer class="bg-white p-4 mt-8 border-t border-gray-200">
        <div class="container mx-auto text-center text-gray-500 text-sm">
            &copy; <?php echo date('Y'); ?> Винное производство. Система автоматизации процессов.
        </div>
    </footer>
    
    <!-- JavaScript для вкладок -->
    <script>
        // Функция для отображения вкладки
        function showTab(tabId) {
            // Скрываем все вкладки
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });
            
            // Убираем активный класс со всех ссылок вкладок
            document.querySelectorAll('.tab-link').forEach(link => {
                link.classList.remove('border-indigo-500', 'text-indigo-600');
                link.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
            });
            
            // Показываем выбранную вкладку
            document.getElementById(tabId).classList.remove('hidden');
            
            // Добавляем активный класс выбранной ссылке
            document.querySelectorAll('.tab-link').forEach(link => {
                if (link.getAttribute('href') === '#' + tabId) {
                    link.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
                    link.classList.add('border-indigo-500', 'text-indigo-600');
                }
            });
        }
        
        // Проверка хэша в URL при загрузке страницы
        document.addEventListener('DOMContentLoaded', function() {
            const hash = window.location.hash.substring(1);
            if (hash && document.getElementById(hash)) {
                showTab(hash);
            }
            
            // Добавляем обработчики событий для ссылок вкладок
            document.querySelectorAll('.tab-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const tabId = this.getAttribute('href').substring(1);
                    showTab(tabId);
                    window.location.hash = tabId;
                });
            });
        });
    </script>
</body>
</html>