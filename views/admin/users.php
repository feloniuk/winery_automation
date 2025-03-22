<?php
// views/admin/users.php
// Страница для просмотра и управления пользователями

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
$userList = $adminController->getAllUsers();

// Обработка действий создания/редактирования пользователя
$message = '';
$error = '';
$editUser = null;

// Если это запрос на редактирование, получаем данные пользователя
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $userId = $_GET['id'];
    $editUser = $adminController->getUserById($userId);
}

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Создание нового пользователя
    if (isset($_POST['add_user'])) {
        $userData = [
            'username' => trim($_POST['username'] ?? ''),
            'password' => trim($_POST['password'] ?? ''),
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'role' => $_POST['role'] ?? ''
        ];
        
        // Если роль - поставщик, добавляем данные поставщика
        if ($userData['role'] === 'supplier') {
            $userData['supplier'] = [
                'company_name' => trim($_POST['company_name'] ?? ''),
                'contact_person' => trim($_POST['contact_person'] ?? ''),
                'phone' => trim($_POST['phone'] ?? ''),
                'address' => trim($_POST['address'] ?? '')
            ];
        }
        
        $result = $adminController->createUser($userData);
        if ($result['success']) {
            $message = $result['message'];
            // Перезагружаем список пользователей
            $userList = $adminController->getAllUsers();
        } else {
            $error = $result['message'];
        }
    }
    
    // Обновление существующего пользователя
    if (isset($_POST['update_user'])) {
        $userId = $_POST['user_id'] ?? '';
        $userData = [
            'username' => trim($_POST['username'] ?? ''),
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'role' => $_POST['role'] ?? ''
        ];
        
        // Добавляем пароль только если он был введен
        if (!empty($_POST['password'])) {
            $userData['password'] = trim($_POST['password']);
        }
        
        // Если роль - поставщик, добавляем данные поставщика
        if ($userData['role'] === 'supplier') {
            $userData['supplier'] = [
                'company_name' => trim($_POST['company_name'] ?? ''),
                'contact_person' => trim($_POST['contact_person'] ?? ''),
                'phone' => trim($_POST['phone'] ?? ''),
                'address' => trim($_POST['address'] ?? '')
            ];
        }
        
        $result = $adminController->updateUser($userId, $userData);
        if ($result['success']) {
            $message = $result['message'];
            // Перезагружаем список пользователей
            $userList = $adminController->getAllUsers();
            // Сбрасываем режим редактирования
            $editUser = null;
            // Перенаправляем на страницу пользователей
            header('Location: users.php');
            exit;
        } else {
            $error = $result['message'];
        }
    }
    
    // Активация/блокировка пользователя
    if (isset($_POST['toggle_status'])) {
        $userId = $_POST['user_id'] ?? '';
        $isActive = isset($_POST['is_active']) ? (bool)$_POST['is_active'] : false;
        
        $result = $adminController->toggleUserStatus($userId, $isActive);
        if ($result['success']) {
            $message = $result['message'];
            // Перезагружаем список пользователей
            $userList = $adminController->getAllUsers();
        } else {
            $error = $result['message'];
        }
    }
}

// Определяем названия ролей для отображения
$roleNames = [
    'admin' => 'Администратор',
    'warehouse' => 'Начальник склада',
    'purchasing' => 'Менеджер по закупкам',
    'supplier' => 'Поставщик'
];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление пользователями - Панель администратора</title>
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
                        <a href="users.php" class="flex items-center p-2 bg-indigo-100 text-indigo-700 rounded font-medium">
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
                        <a href="settings.php" class="flex items-center p-2 text-gray-700 hover:bg-indigo-50 rounded font-medium">
                            <i class="fas fa-cog w-5 mr-2"></i>
                            <span>Настройки</span>
                        </a>
                    </li>
                </ul>
            </div>
        </aside>
        
        <!-- Основной контент -->
        <main class="w-full md:w-3/4">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold text-gray-800">Управление пользователями</h2>
                    <?php if (!$editUser): ?>
                    <button id="showAddForm" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded">
                        <i class="fas fa-user-plus mr-1"></i> Добавить пользователя
                    </button>
                    <?php endif; ?>
                </div>
                
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
                
                <!-- Форма добавления/редактирования пользователя -->
                <div id="userForm" class="<?php echo $editUser || isset($_GET['action']) && $_GET['action'] == 'create' ? 'block' : 'hidden'; ?> bg-gray-50 p-6 rounded-lg mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        <?php echo $editUser ? 'Редактирование пользователя' : 'Создание нового пользователя'; ?>
                    </h3>
                    <form method="POST" action="">
                        <?php if ($editUser): ?>
                        <input type="hidden" name="user_id" value="<?php echo $editUser['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="username" class="block text-sm font-medium text-gray-700">Логин</label>
                                <input type="text" id="username" name="username" required
                                       value="<?php echo $editUser ? htmlspecialchars($editUser['username']) : ''; ?>"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700">
                                    <?php echo $editUser ? 'Новый пароль (оставьте пустым, чтобы не менять)' : 'Пароль'; ?>
                                </label>
                                <input type="password" id="password" name="password" <?php echo $editUser ? '' : 'required'; ?>
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">ФИО</label>
                                <input type="text" id="name" name="name" required
                                       value="<?php echo $editUser ? htmlspecialchars($editUser['name']) : ''; ?>"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                                <input type="email" id="email" name="email" required
                                       value="<?php echo $editUser ? htmlspecialchars($editUser['email']) : ''; ?>"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="role" class="block text-sm font-medium text-gray-700">Роль</label>
                                <select id="role" name="role" required onchange="toggleSupplierFields()" 
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Выберите роль</option>
                                    <option value="admin" <?php echo $editUser && $editUser['role'] == 'admin' ? 'selected' : ''; ?>>Администратор</option>
                                    <option value="warehouse" <?php echo $editUser && $editUser['role'] == 'warehouse' ? 'selected' : ''; ?>>Начальник склада</option>
                                    <option value="purchasing" <?php echo $editUser && $editUser['role'] == 'purchasing' ? 'selected' : ''; ?>>Менеджер по закупкам</option>
                                    <option value="supplier" <?php echo $editUser && $editUser['role'] == 'supplier' ? 'selected' : ''; ?>>Поставщик</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Поля для поставщика (скрыты по умолчанию) -->
                        <div id="supplierFields" class="<?php echo $editUser && $editUser['role'] == 'supplier' ? 'block' : 'hidden'; ?> mt-6 p-4 border border-gray-200 rounded-md">
                            <h4 class="text-md font-medium text-gray-900 mb-4">Информация о поставщике</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="company_name" class="block text-sm font-medium text-gray-700">Название компании</label>
                                    <input type="text" id="company_name" name="company_name"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                </div>
                                <div>
                                    <label for="contact_person" class="block text-sm font-medium text-gray-700">Контактное лицо</label>
                                    <input type="text" id="contact_person" name="contact_person"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                </div>
                                <div>
                                    <label for="phone" class="block text-sm font-medium text-gray-700">Телефон</label>
                                    <input type="text" id="phone" name="phone"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                </div>
                                <div class="md:col-span-2">
                                    <label for="address" class="block text-sm font-medium text-gray-700">Адрес</label>
                                    <textarea id="address" name="address" rows="2" 
                                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-6 flex justify-end space-x-3">
                            <button type="button" id="cancelForm" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Отмена
                            </button>
                            <?php if ($editUser): ?>
                            <button type="submit" name="update_user" class="bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700">
                                Сохранить изменения
                            </button>
                            <?php else: ?>
                            <button type="submit" name="add_user" class="bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700">
                                Создать пользователя
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <!-- Таблица пользователей -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Пользователь
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Роль
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Email
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Дата создания
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Активность
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Действия
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($userList)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                    Пользователи не найдены
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($userList as $user): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center">
                                                    <i class="fas fa-user text-indigo-600"></i>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($user['name']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($user['username']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                              <?php
                                              $roleColors = [
                                                  'admin' => 'bg-purple-100 text-purple-800',
                                                  'warehouse' => 'bg-blue-100 text-blue-800',
                                                  'purchasing' => 'bg-green-100 text-green-800',
                                                  'supplier' => 'bg-yellow-100 text-yellow-800'
                                              ];
                                              echo $roleColors[$user['role']] ?? 'bg-gray-100 text-gray-800';
                                              ?>">
                                            <?php echo $roleNames[$user['role']] ?? $user['role']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($user['email']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('d.m.Y', strtotime($user['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $user['transaction_count']; ?> транзакций
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="?action=edit&id=<?php echo $user['id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                            Редактировать
                                        </a>
                                        
                                        <?php if ($user['role'] === 'supplier'): ?>
                                        <form method="POST" action="" class="inline-block">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="is_active" value="0">
                                            <button type="submit" name="toggle_status" class="text-red-600 hover:text-red-900">
                                                Заблокировать
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <footer class="bg-white p-4 mt-8 border-t border-gray-200">
        <div class="container mx-auto text-center text-gray-500 text-sm">
            &copy; <?php echo date('Y'); ?> Винное производство. Система автоматизации процессов.
        </div>
    </footer>
    
    <!-- JavaScript -->
    <script>
        // Показать/скрыть форму добавления пользователя
        document.addEventListener('DOMContentLoaded', function() {
            const showAddFormBtn = document.getElementById('showAddForm');
            const cancelFormBtn = document.getElementById('cancelForm');
            const userForm = document.getElementById('userForm');
            
            if (showAddFormBtn) {
                showAddFormBtn.addEventListener('click', function() {
                    userForm.classList.remove('hidden');
                    showAddFormBtn.classList.add('hidden');
                });
            }
            
            if (cancelFormBtn) {
                cancelFormBtn.addEventListener('click', function() {
                    <?php if ($editUser): ?>
                    window.location.href = 'users.php';
                    <?php else: ?>
                    userForm.classList.add('hidden');
                    showAddFormBtn.classList.remove('hidden');
                    <?php endif; ?>
                });
            }
        });
        
        // Показать/скрыть поля для поставщика в зависимости от выбранной роли
        function toggleSupplierFields() {
            const roleSelect = document.getElementById('role');
            const supplierFields = document.getElementById('supplierFields');
            
            if (roleSelect.value === 'supplier') {
                supplierFields.classList.remove('hidden');
                
                // Делаем поля поставщика обязательными
                document.getElementById('company_name').required = true;
                document.getElementById('contact_person').required = true;
                document.getElementById('phone').required = true;
                document.getElementById('address').required = true;
            } else {
                supplierFields.classList.add('hidden');
                
                // Убираем обязательность полей поставщика
                document.getElementById('company_name').required = false;
                document.getElementById('contact_person').required = false;
                document.getElementById('phone').required = false;
                document.getElementById('address').required = false;
            }
        }
    </script>
</body>
</html>