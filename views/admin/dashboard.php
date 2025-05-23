<?php
// views/admin/dashboard.php
// Панель керування для адміністратора

// Підключення контролерів
require_once '../../controllers/AuthController.php';
require_once '../../controllers/AdminController.php';

$authController = new AuthController();
$adminController = new AdminController();

// Перевірка авторизації та ролі
if (!$authController->isLoggedIn() || !$authController->checkRole('admin')) {
    header('Location: ../../index.php');
    exit;
}

// Отримання даних для дашборду
$currentUser = $authController->getCurrentUser();
$userStats = $adminController->getUserStatistics();
$inventoryStats = $adminController->getInventoryStatistics();
$recentTransactions = $adminController->getRecentTransactions(5);
$cameraList = $adminController->getCameras();

// Отримання 5 найактивніших користувачів системи
$activeUsers = $adminController->getMostActiveUsers(5);

// Отримання списку сповіщень системи
$systemAlerts = $adminController->getSystemAlerts();

?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель адміністратора - Винне виробництво</title>
    <!-- Підключення Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js для графіків -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Іконки -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Верхня навігаційна панель -->
    <nav class="bg-indigo-800 text-white p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center">
                <i class="fas fa-wine-bottle text-2xl mr-3"></i>
                <h1 class="text-xl font-bold">Винне виробництво</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span><?php echo htmlspecialchars($currentUser['name']); ?> (Адміністратор)</span>
                <a href="../../controllers/logout.php" class="bg-indigo-700 hover:bg-indigo-600 py-2 px-4 rounded text-sm">
                    <i class="fas fa-sign-out-alt mr-1"></i> Вийти
                </a>
            </div>
        </div>
    </nav>
    
    <!-- Бічна панель і основний контент -->
    <div class="container mx-auto flex flex-wrap mt-6 px-4">
        <!-- Бічна навігація -->
        <aside class="w-full md:w-1/4 pr-0 md:pr-6">
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <div class="flex items-center mb-4 pb-4 border-b border-gray-200">
                    <div class="bg-indigo-100 p-3 rounded-full mr-4">
                        <i class="fas fa-user-shield text-indigo-600"></i>
                    </div>
                    <div>
                        <p class="font-semibold"><?php echo htmlspecialchars($currentUser['name']); ?></p>
                        <p class="text-sm text-gray-500">Адміністратор системи</p>
                    </div>
                </div>
                
                <ul class="space-y-2">
                    <li>
                        <a href="dashboard.php" class="flex items-center p-2 bg-indigo-100 text-indigo-700 rounded font-medium">
                            <i class="fas fa-tachometer-alt w-5 mr-2"></i>
                            <span>Панель керування</span>
                        </a>
                    </li>
                    <li>
                        <a href="users.php" class="flex items-center p-2 text-gray-700 hover:bg-indigo-50 rounded font-medium">
                            <i class="fas fa-users w-5 mr-2"></i>
                            <span>Користувачі</span>
                        </a>
                    </li>
                    <li>
                        <a href="cameras.php" class="flex items-center p-2 text-gray-700 hover:bg-indigo-50 rounded font-medium">
                            <i class="fas fa-video w-5 mr-2"></i>
                            <span>Камери спостереження</span>
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
                            <span>Закупівлі</span>
                        </a>
                    </li>
                    
                    <li>
                        <a href="temperature.php" class="flex items-center p-2 text-gray-700 hover:bg-indigo-50 rounded font-medium">
                            <i class="fas fa-thermometer-half w-5 mr-2"></i>
                            <span>Мониторинг СКАДА</span>
                        </a>
                    </li>
                    <li>
                        <a href="reports.php" class="flex items-center p-2 text-gray-700 hover:bg-indigo-50 rounded font-medium">
                            <i class="fas fa-chart-bar w-5 mr-2"></i>
                            <span>Звіти</span>
                        </a>
                    </li>
                    <li>
                        <a href="settings.php" class="flex items-center p-2 text-gray-700 hover:bg-indigo-50 rounded font-medium">
                            <i class="fas fa-cog w-5 mr-2"></i>
                            <span>Налаштування</span>
                        </a>
                    </li>
                </ul>
            </div>
        </aside>
        
        <!-- Основний контент -->
        <main class="w-full md:w-3/4">
            <!-- Картки з короткою статистикою -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-indigo-100 p-3 rounded-full mr-4">
                            <i class="fas fa-users text-indigo-500"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Користувачів</p>
                            <p class="text-2xl font-bold"><?php echo $userStats['total_users']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-3 rounded-full mr-4">
                            <i class="fas fa-boxes text-green-500"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Товарів на складі</p>
                            <p class="text-2xl font-bold"><?php echo $inventoryStats['total_products']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-red-100 p-3 rounded-full mr-4">
                            <i class="fas fa-exclamation-triangle text-red-500"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Сповіщення</p>
                            <p class="text-2xl font-bold"><?php echo count($systemAlerts); ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-3 rounded-full mr-4">
                            <i class="fas fa-video text-blue-500"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Активні камери</p>
                            <p class="text-2xl font-bold"><?php echo count(array_filter($cameraList, function($c) { return $c['status'] === 'active'; })); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Система сповіщень -->
            <?php if (!empty($systemAlerts)): ?>
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-lg font-semibold mb-4">Системні сповіщення</h2>
                <div class="space-y-3">
                    <?php foreach ($systemAlerts as $alert): ?>
                        <div class="flex items-start p-3 bg-red-50 rounded-lg border border-red-200">
                            <div class="text-red-500 mr-3 mt-0.5">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <div>
                                <h3 class="font-medium text-red-800"><?php echo htmlspecialchars($alert['title']); ?></h3>
                                <p class="text-sm text-red-700 mt-1"><?php echo htmlspecialchars($alert['message']); ?></p>
                                <div class="flex justify-between items-center mt-2">
                                    <span class="text-xs text-red-600"><?php echo date('d.m.Y H:i', strtotime($alert['created_at'])); ?></span>
                                    <button class="text-xs text-indigo-600 hover:text-indigo-800" onclick="dismissAlert(<?php echo $alert['id']; ?>)">
                                        Позначити як вирішене
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Основні блоки з даними -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Камери спостереження (превью) -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-lg font-semibold mb-4">Камери спостереження</h2>
                    <?php if (empty($cameraList)): ?>
                        <p class="text-gray-500 text-center py-6">Камери не налаштовані.</p>
                    <?php else: ?>
                        <div class="grid grid-cols-2 gap-4">
                            <?php foreach (array_slice($cameraList, 0, 4) as $camera): ?>
                                <div class="relative rounded-lg overflow-hidden bg-gray-200">
                                    <!-- Тут у реальній системі був би iframe або зображення з камери -->
                                    <div class="aspect-w-16 aspect-h-9">
                                        <div class="w-full h-full flex items-center justify-center bg-gray-800 text-gray-400">
                                            <i class="fas fa-video fa-2x"></i>
                                        </div>
                                    </div>
                                    <div class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-60 text-white text-xs p-2">
                                        <?php echo htmlspecialchars($camera['name']); ?>
                                        <?php if ($camera['status'] === 'active'): ?>
                                            <span class="inline-block w-2 h-2 rounded-full bg-green-500 ml-2"></span>
                                        <?php else: ?>
                                            <span class="inline-block w-2 h-2 rounded-full bg-red-500 ml-2"></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-4 text-center">
                            <a href="cameras.php" class="inline-block px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                                Переглянути всі камери
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Розподіл користувачів за ролями -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-lg font-semibold mb-4">Розподіл користувачів за ролями</h2>
                    <div class="h-64">
                        <canvas id="userRolesChart"></canvas>
                    </div>
                </div>
                
                <!-- Активність користувачів -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-lg font-semibold mb-4">Найактивніші користувачі</h2>
                    <?php if (empty($activeUsers)): ?>
                        <p class="text-gray-500 text-center py-6">Дані про активність відсутні.</p>
                    <?php else: ?>
                        <div>
                            <?php foreach ($activeUsers as $index => $user): ?>
                                <div class="mb-4">
                                    <div class="flex justify-between mb-1">
                                        <span class="text-sm font-medium text-gray-700">
                                            <?php echo ($index + 1) . '. ' . htmlspecialchars($user['name']); ?>
                                            <span class="text-xs text-gray-500">(<?php echo $user['role_name']; ?>)</span>
                                        </span>
                                        <span class="text-sm text-gray-500">
                                            <?php echo $user['action_count']; ?> дій
                                        </span>
                                    </div>
                                    <div class="relative pt-1">
                                        <div class="overflow-hidden h-2 mb-1 text-xs flex rounded bg-gray-200">
                                            <div style="width:<?php echo min(100, ($user['action_count'] / $activeUsers[0]['action_count']) * 100); ?>%" 
                                                class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-indigo-500">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Недавні транзакції -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-lg font-semibold mb-4">Недавні транзакції по складу</h2>
                    <?php if (empty($recentTransactions)): ?>
                        <p class="text-gray-500 text-center py-6">Транзакції відсутні.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дата</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Товар</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Тип</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Користувач</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($recentTransactions as $transaction): ?>
                                        <tr>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('d.m.Y H:i', strtotime($transaction['created_at'])); ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($transaction['product_name']); ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <?php if ($transaction['transaction_type'] == 'in'): ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                        Надходження (<?php echo $transaction['quantity']; ?> <?php echo $transaction['unit']; ?>)
                                                    </span>
                                                <?php else: ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                        Витрата (<?php echo $transaction['quantity']; ?> <?php echo $transaction['unit']; ?>)
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($transaction['user_name']); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4 text-right">
                            <a href="warehouse.php" class="text-sm text-indigo-600 hover:text-indigo-800">
                                Всі транзакції <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Швидкі дії -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-semibold mb-4">Швидкі дії</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <a href="users.php?action=create" class="flex flex-col items-center p-4 bg-indigo-50 rounded-lg hover:bg-indigo-100">
                        <div class="bg-indigo-100 p-3 rounded-full mb-3">
                            <i class="fas fa-user-plus text-indigo-600 text-xl"></i>
                        </div>
                        <span class="text-sm font-medium text-indigo-800">Додати користувача</span>
                    </a>
                    <a href="cameras.php?action=create" class="flex flex-col items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100">
                        <div class="bg-blue-100 p-3 rounded-full mb-3">
                            <i class="fas fa-video-plus text-blue-600 text-xl"></i>
                        </div>
                        <span class="text-sm font-medium text-blue-800">Додати камеру</span>
                    </a>
                    <a href="reports.php" class="flex flex-col items-center p-4 bg-green-50 rounded-lg hover:bg-green-100">
                        <div class="bg-green-100 p-3 rounded-full mb-3">
                            <i class="fas fa-chart-line text-green-600 text-xl"></i>
                        </div>
                        <span class="text-sm font-medium text-green-800">Створити звіт</span>
                    </a>
                    <a href="backup.php" class="flex flex-col items-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100">
                        <div class="bg-purple-100 p-3 rounded-full mb-3">
                            <i class="fas fa-database text-purple-600 text-xl"></i>
                        </div>
                        <span class="text-sm font-medium text-purple-800">Резервне копіювання</span>
                    </a>
                </div>
            </div>
        </main>
    </div>
    
    <footer class="bg-white p-4 mt-8 border-t border-gray-200">
        <div class="container mx-auto text-center text-gray-500 text-sm">
            &copy; <?php echo date('Y'); ?> Винне виробництво. Система автоматизації процесів.
        </div>
    </footer>
    
    <!-- JavaScript -->
    <script>
        // Графік розподілу користувачів за ролями
        var roleCtx = document.getElementById('userRolesChart').getContext('2d');
        var roleLabels = [
            'Адміністратори', 
            'Начальники складу', 
            'Менеджери із закупівель',
            'Постачальники'
        ];
        var roleData = [
            <?php echo $userStats['admin_count']; ?>,
            <?php echo $userStats['warehouse_count']; ?>,
            <?php echo $userStats['purchasing_count']; ?>,
            <?php echo $userStats['supplier_count']; ?>
        ];
        var roleColors = [
            'rgba(79, 70, 229, 0.7)',
            'rgba(16, 185, 129, 0.7)',
            'rgba(245, 158, 11, 0.7)',
            'rgba(239, 68, 68, 0.7)'
        ];
        
        var userRolesChart = new Chart(roleCtx, {
            type: 'doughnut',
            data: {
                labels: roleLabels,
                datasets: [{
                    data: roleData,
                    backgroundColor: roleColors,
                    borderColor: 'white',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Функция для отклонения оповещения
        function dismissAlert(alertId) {
            if (confirm('Ви впевненні?')) {
                fetch('dismiss_alert.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'alert_id=' + alertId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Перезагрузить страницу или удалить элемент из DOM
                        location.reload();
                    } else {
                        alert('Помилка: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Помилка при оновленні');
                });
            }
        }
    </script>
</body>
</html>