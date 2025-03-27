<?php
// views/supplier/dashboard.php
// Панель керування для постачальника

// Підключення контролерів
require_once '../../controllers/AuthController.php';
require_once '../../controllers/SupplierController.php';

$authController = new AuthController();
$supplierController = new SupplierController();

// Перевірка авторизації та ролі
if (!$authController->isLoggedIn() || !$authController->checkRole('supplier')) {
    header('Location: ../../index.php');
    exit;
}

// Отримання даних для дашборду
$currentUser = $authController->getCurrentUser();
$supplierId = $supplierController->getSupplierIdByUserId($currentUser['id']);

if (!$supplierId) {
    // Якщо дані постачальника не знайдені, відображаємо помилку
    $error = "Помилка: дані постачальника не знайдені. Зверніться до адміністратора.";
} else {
    // Отримуємо дані для відображення
    $supplierInfo = $supplierController->getSupplierInfo($supplierId);
    $pendingOrders = $supplierController->getOrdersByStatus($supplierId, 'pending');
    $approvedOrders = $supplierController->getOrdersByStatus($supplierId, 'approved');
    $recentOrders = $supplierController->getRecentOrders($supplierId, 5);
    $unreadMessages = $supplierController->getUnreadMessages($currentUser['id']);
    $salesStats = $supplierController->getSupplierSalesStats($supplierId);
}

?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель постачальника - Винне виробництво</title>
    <!-- Підключення Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js для графіків -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Іконки -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Верхня навігаційна панель -->
    <nav class="bg-amber-800 text-white p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center">
                <i class="fas fa-wine-bottle text-2xl mr-3"></i>
                <h1 class="text-xl font-bold">Винне виробництво</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span><?php echo htmlspecialchars($currentUser['name']); ?> (Постачальник)</span>
                <a href="../../controllers/logout.php" class="bg-amber-700 hover:bg-amber-600 py-2 px-4 rounded text-sm">
                    <i class="fas fa-sign-out-alt mr-1"></i> Вийти
                </a>
            </div>
        </div>
    </nav>
    
    <?php if (isset($error)): ?>
    <div class="container mx-auto mt-6 px-4">
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
            <p><?php echo $error; ?></p>
        </div>
    </div>
    <?php else: ?>
    
    <!-- Бічна панель і основний вміст -->
    <div class="container mx-auto flex flex-wrap mt-6 px-4">
        <!-- Бічна навігація -->
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
                        <a href="dashboard.php" class="flex items-center p-2 bg-amber-100 text-amber-700 rounded font-medium">
                            <i class="fas fa-tachometer-alt w-5 mr-2"></i>
                            <span>Панель керування</span>
                        </a>
                    </li>
                    <li>
                        <a href="orders.php" class="flex items-center p-2 text-gray-700 hover:bg-amber-50 rounded font-medium">
                            <i class="fas fa-shopping-cart w-5 mr-2"></i>
                            <span>Замовлення</span>
                            <?php if (count($pendingOrders) > 0): ?>
                            <span class="ml-auto bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                <?php echo count($pendingOrders); ?>
                            </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="products.php" class="flex items-center p-2 text-gray-700 hover:bg-amber-50 rounded font-medium">
                            <i class="fas fa-boxes w-5 mr-2"></i>
                            <span>Мої товари</span>
                        </a>
                    </li>
                    <li>
                        <a href="messages.php" class="flex items-center p-2 text-gray-700 hover:bg-amber-50 rounded font-medium">
                            <i class="fas fa-envelope w-5 mr-2"></i>
                            <span>Повідомлення</span>
                            <?php if (count($unreadMessages) > 0): ?>
                            <span class="ml-auto bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                <?php echo count($unreadMessages); ?>
                            </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="profile.php" class="flex items-center p-2 text-gray-700 hover:bg-amber-50 rounded font-medium">
                            <i class="fas fa-user-cog w-5 mr-2"></i>
                            <span>Мій профіль</span>
                        </a>
                    </li>
                </ul>
            </div>
        </aside>
        
        <!-- Основний вміст -->
        <main class="w-full md:w-3/4">
            <!-- Картки з короткою статистикою -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-yellow-100 p-3 rounded-full mr-4">
                            <i class="fas fa-clock text-yellow-500"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Очікують підтвердження</p>
                            <p class="text-2xl font-bold"><?php echo count($pendingOrders); ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-3 rounded-full mr-4">
                            <i class="fas fa-check-circle text-green-500"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Підтверджені замовлення</p>
                            <p class="text-2xl font-bold"><?php echo count($approvedOrders); ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-3 rounded-full mr-4">
                            <i class="fas fa-envelope text-blue-500"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Непрочитані повідомлення</p>
                            <p class="text-2xl font-bold"><?php echo count($unreadMessages); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Основні блоки з даними -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Графік продажів по місяцях -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-lg font-semibold mb-4">Статистика замовлень по місяцях</h2>
                    <div>
                        <canvas id="salesChart" width="400" height="300"></canvas>
                    </div>
                </div>
                
                <!-- Замовлення, що очікують підтвердження -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-lg font-semibold mb-4">Замовлення, що потребують уваги</h2>
                    <?php if (empty($pendingOrders)): ?>
                        <p class="text-gray-500 text-center py-6">Немає замовлень, що потребують вашої уваги.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дата</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Сума</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Статус</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дія</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($pendingOrders as $order): ?>
                                        <tr>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo $order['id']; ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('d.m.Y', strtotime($order['created_at'])); ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo number_format($order['total_amount'], 2, ',', ' '); ?> ₴
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                    Очікує підтвердження
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <a href="order_details.php?id=<?php echo $order['id']; ?>" class="text-amber-600 hover:text-amber-900">
                                                    Деталі
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4 text-right">
                            <a href="orders.php" class="text-sm text-amber-600 hover:text-amber-800">
                                Усі замовлення <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Останні замовлення -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-lg font-semibold mb-4">Останні замовлення</h2>
                    <?php if (empty($recentOrders)): ?>
                        <p class="text-gray-500 text-center py-6">У вас поки немає замовлень.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дата</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Сума</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Статус</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($recentOrders as $order): ?>
                                        <tr>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo $order['id']; ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('d.m.Y', strtotime($order['created_at'])); ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo number_format($order['total_amount'], 2, ',', ' '); ?> ₴
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <?php
                                                $statusClasses = [
                                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                                    'approved' => 'bg-green-100 text-green-800',
                                                    'rejected' => 'bg-red-100 text-red-800',
                                                    'received' => 'bg-blue-100 text-blue-800'
                                                ];
                                                $statusTexts = [
                                                    'pending' => 'Очікує підтвердження',
                                                    'approved' => 'Підтверджено',
                                                    'rejected' => 'Відхилено',
                                                    'received' => 'Отримано'
                                                ];
                                                $statusClass = $statusClasses[$order['status']] ?? 'bg-gray-100 text-gray-800';
                                                $statusText = $statusTexts[$order['status']] ?? $order['status'];
                                                ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                                    <?php echo $statusText; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4 text-right">
                            <a href="orders.php" class="text-sm text-amber-600 hover:text-amber-800">
                                Історія замовлень <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Непрочитані повідомлення -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-lg font-semibold mb-4">Непрочитані повідомлення</h2>
                    <?php if (empty($unreadMessages)): ?>
                        <p class="text-gray-500 text-center py-6">У вас немає непрочитаних повідомлень.</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($unreadMessages as $message): ?>
                                <div class="bg-blue-50 rounded-lg p-4 border border-blue-100">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h3 class="font-medium text-blue-900"><?php echo htmlspecialchars($message['subject'] ?: 'Без теми'); ?></h3>
                                            <p class="text-sm text-gray-500 mt-1">Від: <?php echo htmlspecialchars($message['sender_name']); ?></p>
                                        </div>
                                        <span class="text-xs text-gray-500">
                                            <?php echo date('d.m.Y H:i', strtotime($message['created_at'])); ?>
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-700 mt-2">
                                        <?php echo nl2br(htmlspecialchars(substr($message['message'], 0, 100) . (strlen($message['message']) > 100 ? '...' : ''))); ?>
                                    </p>
                                    <div class="mt-2 text-right">
                                        <a href="view_message.php?id=<?php echo $message['id']; ?>" class="text-sm text-amber-600 hover:text-amber-800">
                                            Читати повністю
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-4 text-right">
                            <a href="messages.php" class="text-sm text-amber-600 hover:text-amber-800">
                                Усі повідомлення <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Інформація про компанію -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-semibold mb-4">Інформація про компанію</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-sm text-gray-500">Назва компанії</dt>
                                <dd class="font-medium"><?php echo htmlspecialchars($supplierInfo['company_name']); ?></dd>
                            </div>
                            <div>
                                <dt class="text-sm text-gray-500">Контактна особа</dt>
                                <dd class="font-medium"><?php echo htmlspecialchars($supplierInfo['contact_person']); ?></dd>
                            </div>
                            <div>
                                <dt class="text-sm text-gray-500">Email</dt>
                                <dd class="font-medium"><?php echo htmlspecialchars($currentUser['email']); ?></dd>
                            </div>
                        </dl>
                    </div>
                    <div>
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-sm text-gray-500">Телефон</dt>
                                <dd class="font-medium"><?php echo htmlspecialchars($supplierInfo['phone']); ?></dd>
                            </div>
                            <div>
                                <dt class="text-sm text-gray-500">Адреса</dt>
                                <dd class="font-medium"><?php echo htmlspecialchars($supplierInfo['address']); ?></dd>
                            </div>
                            <div>
                                <dt class="text-sm text-gray-500">Статус</dt>
                                <dd class="font-medium">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        Активний
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
                <div class="mt-6 text-right">
                    <a href="profile.php" class="text-sm text-amber-600 hover:text-amber-800">
                        Редагувати інформацію <i class="fas fa-arrow-right ml-1"></i>
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
        // Графік продажів по місяцях
        var ctx = document.getElementById('salesChart').getContext('2d');
        var salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($salesStats, 'month_name')); ?>,
                datasets: [{
                    label: 'Сума замовлень (₴)',
                    data: <?php echo json_encode(array_column($salesStats, 'total_amount')); ?>,
                    backgroundColor: 'rgba(217, 119, 6, 0.2)',
                    borderColor: 'rgba(217, 119, 6, 1)',
                    borderWidth: 2,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString() + ' ₴';
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.raw.toLocaleString() + ' ₴';
                            }
                        }
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>