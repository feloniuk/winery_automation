<?php
// views/auth/register.php
// Страница регистрации поставщика

// Подключение контроллера авторизации
require_once '../../controllers/AuthController.php';

$authController = new AuthController();

// Если пользователь уже авторизован, перенаправляем на соответствующую страницу
if ($authController->isLoggedIn()) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'admin':
            header('Location: ../admin/dashboard.php');
            break;
        case 'warehouse':
            header('Location: ../warehouse/dashboard.php');
            break;
        case 'purchasing':
            header('Location: ../purchasing/dashboard.php');
            break;
        case 'supplier':
            header('Location: ../supplier/dashboard.php');
            break;
    }
    exit;
}

// Обработка формы регистрации
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userData = [
        'username' => $_POST['username'] ?? '',
        'password' => $_POST['password'] ?? '',
        'name' => $_POST['name'] ?? '',
        'email' => $_POST['email'] ?? '',
    ];

    $supplierData = [
        'company_name' => $_POST['company_name'] ?? '',
        'contact_person' => $_POST['contact_person'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'address' => $_POST['address'] ?? '',
    ];

    $result = $authController->registerSupplier($userData, $supplierData);
    
    if ($result['success']) {
        $success = $result['message'];
    } else {
        $error = $result['message'];
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация поставщика - Автоматизация винного производства</title>
    <!-- Подключение Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Дополнительные стили -->
    <style>
        body {
            background-image: url('https://images.unsplash.com/photo-1560493676-04071c5f467b?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80');
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
    <div class="w-full max-w-xl glass-effect p-8">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-purple-900">Регистрация поставщика</h1>
            <p class="text-gray-600 mt-2">Заполните форму для создания учетной записи</p>
        </div>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p><?php echo $error; ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p><?php echo $success; ?></p>
                <p class="mt-2">
                    <a href="../../index.php" class="font-medium text-green-600 hover:text-green-500">
                        Перейти на страницу входа
                    </a>
                </p>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" class="space-y-6">
            <div class="bg-gray-50 p-4 rounded-lg mb-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Данные для входа</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700">Логин *</label>
                        <input type="text" id="username" name="username" required 
                              class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Пароль *</label>
                        <input type="password" id="password" name="password" required 
                              class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                    </div>
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">ФИО *</label>
                        <input type="text" id="name" name="name" required 
                              class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email *</label>
                        <input type="email" id="email" name="email" required 
                              class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                    </div>
                </div>
            </div>
            
            <div class="bg-gray-50 p-4 rounded-lg">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Информация о компании</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="company_name" class="block text-sm font-medium text-gray-700">Название компании *</label>
                        <input type="text" id="company_name" name="company_name" required 
                              class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                    </div>
                    <div>
                        <label for="contact_person" class="block text-sm font-medium text-gray-700">Контактное лицо *</label>
                        <input type="text" id="contact_person" name="contact_person" required 
                              class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                    </div>
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700">Телефон *</label>
                        <input type="text" id="phone" name="phone" required 
                              class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                    </div>
                    <div class="md:col-span-2">
                        <label for="address" class="block text-sm font-medium text-gray-700">Адрес *</label>
                        <textarea id="address" name="address" rows="3" required 
                                 class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500"></textarea>
                    </div>
                </div>
            </div>
            
            <div class="flex items-center">
                <input id="terms" name="terms" type="checkbox" required
                       class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                <label for="terms" class="ml-2 block text-sm text-gray-900">
                    Я согласен с <a href="#" class="text-purple-600 hover:text-purple-500">условиями использования</a> и <a href="#" class="text-purple-600 hover:text-purple-500">политикой конфиденциальности</a>
                </label>
            </div>
            
            <div>
                <button type="submit" 
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                    Зарегистрироваться
                </button>
            </div>
        </form>
        
        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600">
                Уже зарегистрированы?
                <a href="../../index.php" class="font-medium text-purple-600 hover:text-purple-500">
                    Войти в систему
                </a>
            </p>
        </div>
    </div>
</body>
</html>