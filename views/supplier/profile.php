<?php
// views/supplier/profile.php
// Страница профиля для поставщика

// Подключение контроллеров
require_once '../../controllers/AuthController.php';
require_once '../../controllers/SupplierController.php';

$authController = new AuthController();
$supplierController = new SupplierController();

// Проверка авторизации и роли
if (!$authController->isLoggedIn() || !$authController->checkRole('supplier')) {
    header('Location: ../../index.php');
    exit;
}

// Получение данных пользователя
$currentUser = $authController->getCurrentUser();
$supplierId = $supplierController->getSupplierIdByUserId($currentUser['id']);

if (!$supplierId) {
    // Если данные поставщика не найдены, отображаем ошибку
    $error = "Ошибка: данные поставщика не найдены. Обратитесь к администратору.";
} else {
    // Получаем информацию о поставщике
    $supplierInfo = $supplierController->getSupplierInfo($supplierId);
    
    // Получение количества непрочитанных сообщений
    $unreadMessages = $supplierController->getUnreadMessages($currentUser['id']);
}

// Обработка отправки формы обновления профиля
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Данные пользователя
    $userData = [
        'name' => $_POST['name'] ?? '',
        'email' => $_POST['email'] ?? '',
    ];
    
    // Если пароль заполнен, обновляем его
    if (!empty($_POST['password'])) {
        $userData['password'] = $_POST['password'];
    }
    
    // Данные поставщика
    $supplierData = [
        'company_name' => $_POST['company_name'] ?? '',
        'contact_person' => $_POST['contact_person'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'address' => $_POST['address'] ?? '',
    ];
    
    // Проверка заполнения обязательных полей
    if (empty($userData['name']) || empty($userData['email']) || 
        empty($supplierData['company_name']) || empty($supplierData['contact_person']) ||
        empty($supplierData['phone']) || empty($supplierData['address'])) {
        $error = "Пожалуйста, заполните все обязательные поля";
    } else {
        // Обновление профиля
        $result = $supplierController->updateSupplierProfile($supplierId, $userData, $supplierData);
        
        if ($result['success']) {
            $message = $result['message'];
            // Обновляем данные после успешного обновления
            $supplierInfo = $supplierController->getSupplierInfo($supplierId);
            $currentUser = $authController->getCurrentUser(); // Обновляем данные пользователя
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
    <title>Мой профиль - Винное производство</title>
    <!-- Подключение Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Иконки -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Верхняя навигационная панель -->
    <nav class="bg-amber-800 text-white p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center">
                <i class="fas fa-wine-bottle text-2xl mr-3"></i>
                <h1 class="text-xl font-bold">Винное производство</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span><?php echo htmlspecialchars($currentUser['name']); ?> (Поставщик)</span>
                <a href="../../controllers/logout.php" class="bg-amber-700 hover:bg-amber-600 py-2 px-4 rounded text-sm">
                    <i class="fas fa-sign-out-alt mr-1"></i> Выйти
                </a>
            </div>
        </div>
    </nav>
    
    <?php if (isset($error) && $error === "Ошибка: данные поставщика не найдены. Обратитесь к администратору."): ?>
    <div class="container mx-auto mt-6 px-4">
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
            <p><?php echo $error; ?></p>
        </div>
    </div>
    <?php else: ?>
    
    <!-- Боковая панель и основной контент -->
    <div class="container mx-auto flex flex-wrap mt-6 px-4">
        <!-- Боковая навигация -->
        <aside class="w-full md:w-1/4 pr-0 md:pr-6">
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <div class="flex items-center mb-4 pb-4 border-b border-gray-200">
                    <div class="bg-amber-100 p-3 rounded-full mr-4">
                        <i class="fas fa-user text-amber-600"></i>
                    </div>
                    <div>
                        <p class="font-semibold"><?php echo htmlspecialchars($currentUser['name']); ?></p>
                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($supplierInfo['company_name']); ?></p>
                    </div>
                </div>
                
                <ul class="space-y-2">
                    <li>
                        <a href="dashboard.php" class="flex items-center p-2 text-gray-700 hover:bg-amber-50 rounded font-medium">
                            <i class="fas fa-tachometer-alt w-5 mr-2"></i>
                            <span>Панель управления</span>
                        </a>
                    </li>
                    <li>
                        <a href="orders.php" class="flex items-center p-2 text-gray-700 hover:bg-amber-50 rounded font-medium">
                            <i class="fas fa-shopping-cart w-5 mr-2"></i>
                            <span>Заказы</span>
                        </a>
                    </li>
                    <li>
                        <a href="products.php" class="flex items-center p-2 text-gray-700 hover:bg-amber-50 rounded font-medium">
                            <i class="fas fa-boxes w-5 mr-2"></i>
                            <span>Мои товары</span>
                        </a>
                    </li>
                    <li>
                        <a href="messages.php" class="flex items-center p-2 text-gray-700 hover:bg-amber-50 rounded font-medium">
                            <i class="fas fa-envelope w-5 mr-2"></i>
                            <span>Сообщения</span>
                            <?php if (count($unreadMessages) > 0): ?>
                            <span class="ml-auto bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                <?php echo count($unreadMessages); ?>
                            </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="profile.php" class="flex items-center p-2 bg-amber-100 text-amber-700 rounded font-medium">
                            <i class="fas fa-user-cog w-5 mr-2"></i>
                            <span>Мой профиль</span>
                        </a>
                    </li>
                </ul>
            </div>
        </aside>
        
        <!-- Основной контент -->
        <main class="w-full md:w-3/4">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">Мой профиль</h2>
                
                <?php if ($message): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p><?php echo $message; ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($error && $error !== "Ошибка: данные поставщика не найдены. Обратитесь к администратору."): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p><?php echo $error; ?></p>
                </div>
                <?php endif; ?>
                
                <!-- Форма редактирования профиля -->
                <form method="POST" action="">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Блок с данными пользователя -->
                        <div class="col-span-2 md:col-span-1">
                            <div class="bg-amber-50 p-4 rounded-lg">
                                <h3 class="text-lg font-medium text-amber-800 mb-4">Данные пользователя</h3>
                                
                                <div class="mb-4">
                                    <label for="name" class="block text-sm font-medium text-gray-700">ФИО *</label>
                                    <input type="text" id="name" name="name" required 
                                           value="<?php echo htmlspecialchars($currentUser['name']); ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm">
                                </div>
                                
                                <div class="mb-4">
                                    <label for="email" class="block text-sm font-medium text-gray-700">Email *</label>
                                    <input type="email" id="email" name="email" required 
                                           value="<?php echo htmlspecialchars($currentUser['email']); ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm">
                                </div>
                                
                                <div class="mb-4">
                                    <label for="username" class="block text-sm font-medium text-gray-700">Логин (не изменяется)</label>
                                    <input type="text" id="username" disabled 
                                           value="<?php echo htmlspecialchars($currentUser['username']); ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 bg-gray-100 shadow-sm sm:text-sm">
                                </div>
                                
                                <div>
                                    <label for="password" class="block text-sm font-medium text-gray-700">Новый пароль (оставьте пустым, чтобы не менять)</label>
                                    <input type="password" id="password" name="password" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Блок с данными компании -->
                        <div class="col-span-2 md:col-span-1">
                            <div class="bg-amber-50 p-4 rounded-lg">
                                <h3 class="text-lg font-medium text-amber-800 mb-4">Данные компании</h3>
                                
                                <div class="mb-4">
                                    <label for="company_name" class="block text-sm font-medium text-gray-700">Название компании *</label>
                                    <input type="text" id="company_name" name="company_name" required 
                                           value="<?php echo htmlspecialchars($supplierInfo['company_name']); ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm">
                                </div>
                                
                                <div class="mb-4">
                                    <label for="contact_person" class="block text-sm font-medium text-gray-700">Контактное лицо *</label>
                                    <input type="text" id="contact_person" name="contact_person" required 
                                           value="<?php echo htmlspecialchars($supplierInfo['contact_person']); ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm">
                                </div>
                                
                                <div class="mb-4">
                                    <label for="phone" class="block text-sm font-medium text-gray-700">Телефон *</label>
                                    <input type="text" id="phone" name="phone" required 
                                           value="<?php echo htmlspecialchars($supplierInfo['phone']); ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm">
                                </div>
                                
                                <div>
                                    <label for="address" class="block text-sm font-medium text-gray-700">Адрес *</label>
                                    <textarea id="address" name="address" rows="3" required 
                                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm"><?php echo htmlspecialchars($supplierInfo['address']); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end">
                        <button type="submit" name="update_profile" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-amber-600 hover:bg-amber-700 focus:outline-none">
                            <i class="fas fa-save mr-2"></i> Сохранить изменения
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Дополнительная информация -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Дополнительная информация</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="text-lg font-medium text-gray-900 mb-3">Статус аккаунта</h3>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-700">Текущий статус:</span>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    Активный
                                </span>
                            </div>
                            <div class="mt-2 flex justify-between items-center">
                                <span class="text-gray-700">Дата регистрации:</span>
                                <span class="text-gray-900">
                                    <?php echo date('d.m.Y', strtotime($currentUser['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="text-lg font-medium text-gray-900 mb-3">Безопасность аккаунта</h3>
                            <p class="text-gray-700 mb-2">
                                Регулярно меняйте пароль для обеспечения безопасности вашего аккаунта.
                                Используйте сложные пароли, содержащие буквы, цифры и специальные символы.
                            </p>
                            <div class="mt-2">
                                <a href="#" onclick="document.getElementById('password').focus()" class="text-amber-600 hover:text-amber-800">
                                    <i class="fas fa-key mr-1"></i> Сменить пароль
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Контактная информация поддержки</h3>
                    <p class="text-gray-700">
                        Если у вас возникли вопросы или проблемы с использованием системы, вы можете обратиться в службу поддержки:
                    </p>
                    <ul class="mt-2 space-y-1">
                        <li class="text-gray-700">
                            <i class="fas fa-envelope text-amber-600 mr-2"></i> Email: support@winery.com
                        </li>
                        <li class="text-gray-700">
                            <i class="fas fa-phone text-amber-600 mr-2"></i> Телефон: +7 (999) 123-45-67
                        </li>
                        <li class="text-gray-700">
                            <i class="fas fa-comments text-amber-600 mr-2"></i> Внутренняя система сообщений: отправьте сообщение администратору через <a href="messages.php" class="text-amber-600 hover:text-amber-800">страницу сообщений</a>
                        </li>
                    </ul>
                </div>
            </div>
        </main>
    </div>
    
    <footer class="bg-white p-4 mt-8 border-t border-gray-200">
        <div class="container mx-auto text-center text-gray-500 text-sm">
            &copy; <?php echo date('Y'); ?> Винное производство. Система автоматизации процессов.
        </div>
    </footer>
    
    <?php endif; ?>
</body>
</html>