<?php
// views/purchasing/dashboard.php
// Панель управления для менеджера по закупкам

// Подключение контроллеров
require_once '../../controllers/AuthController.php';
require_once '../../controllers/PurchasingController.php';

$authController = new AuthController();
$purchasingController = new PurchasingController();

// Проверка авторизации и роли
if (!$authController->isLoggedIn() || !$authController->checkRole('purchasing')) {
    header('Location: ../../index.php');
    exit;
}

// Получение данных для дашборда
$currentUser = $authController->getCurrentUser();
$pendingOrders = $purchasingController->getOrdersByStatus('pending');
$approvedOrders = $purchasingController->getOrdersByStatus('approved');
$receivedOrders = $purchasingController->getRecentReceivedOrders(5);
$lowStockItems = $purchasingController->getLowStockItems();
$suppliers = $purchasingController->getActiveSuppliers();

// Подсчет важных метрик
$totalActiveOrders = count($pendingOrders) + count($approvedOrders);
$totalActiveSuppliers = count($suppliers);
$totalLowStockItems = count($lowStockItems);
$ordersThisMonth = $purchasingController->getOrdersCountForPeriod(date('Y-m-01'), date('Y-m-t'));
$totalSpendingThisMonth = $purchasingController->getTotalSpendingForPeriod(date('Y-m-01'), date('Y-m-t'));

// Получение данных для графика заказов по месяцам
$ordersByMonth = $purchasingController->getOrderCountByMonth(6);

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель управления закупками - Винное производство</title>
    <!-- Подключение Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js для графиков -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Иконки -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Верхняя навигационная панель -->
    <nav class="bg-teal-800 text-white p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center">
                <i class="fas fa-wine-bottle text-2xl mr-3"></i>
                <h1 class="text-xl font-bold">Винное производство</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span><?php echo htmlspecialchars($currentUser['name']); ?> (Менеджер по закупкам)</span>
                <a href="../../controllers/logout.php" class="bg-teal-700 hover:bg-teal-600 py-2 px-4 rounded text-sm">
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
                    <div class="bg-teal-100 p-3 rounded-full mr-4">
                        <i class="fas fa-user text-teal-600"></i>
                    </div>
                    <div>
                        <p class="font-semibold"><?php echo htmlspecialchars($currentUser['name']); ?></p>
                        <p class="text-sm text-gray-500">Менеджер по закупкам</p>
                    </div>
                </div>
                
                <ul class="space-y-2">
                    <li>
                        <a href="dashboard.php" class="flex items-center p-2 bg-teal-100 text-teal-700 rounded font-medium">
                            <i class="fas fa-tachometer-alt w-5 mr-2"></i>
                            <span>Панель управления</span>
                        </a>
                    </li>
                    <li>
                        <a href="suppliers.php" class="flex items-center p-2 text-gray-700 hover:bg-teal-50 rounded font-medium">
                            <i class="fas fa-truck w-5 mr-2"></i>
                            <span>Поставщики</span>
                        </a>
                    </li>
                    <li>
                        <a href="orders.php" class="flex items-center p-2 text-gray-700 hover:bg-teal-50 rounded font-medium">
                            <i class="fas fa-shopping-cart w-5 mr-2"></i>
                            <span>Заказы</span>
                        </a>
                    </li>
                    <li>
                        <a href="inventory.php" class="flex items-center p-2 text-gray-700 hover:bg-teal-50 rounded font-medium">
                            <i class="fas fa-boxes w-5 mr-2"></i>
                            <span>Остатки на складе</span>
                        </a>
                    </li>
                    <li>
                        <a href="messages.php" class="flex items-center p-2 text-gray-700 hover:bg-teal-50 rounded font-medium">
                            <i class="fas fa-envelope w-5 mr-2"></i>
                            <span>Сообщения</span>
                            <?php 
                            $unreadCount = $purchasingController->getUnreadMessagesCount($currentUser['id']);
                            if ($unreadCount > 0): 
                            ?>
                            <span class="ml-auto bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                <?php echo $unreadCount; ?>
                            </span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>
        </aside>
        
        <!-- Основной контент -->
        <main class="w-full md:w-3/4">
            <!-- Карточки с краткой статистикой -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-3 rounded-full mr-4">
                            <i class="fas fa-shopping-cart text-blue-500"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Активные заказы</p>
                            <p class="text-2xl font-bold"><?php echo $totalActiveOrders; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-3 rounded-full mr-4">
                            <i class="fas fa-truck-loading text-green-500"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Активные поставщики</p>
                            <p class="text-2xl font-bold"><?php echo $totalActiveSuppliers; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-yellow-100 p-3 rounded-full mr-4">
                            <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Товары с низким запасом</p>
                            <p class="text-2xl font-bold"><?php echo $totalLowStockItems; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-purple-100 p-3 rounded-full mr-4">
                            <i class="fas fa-calendar-alt text-purple-500"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Заказов в этом месяце</p>
                            <p class="text-2xl font-bold"><?php echo $ordersThisMonth; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Основные блоки с данными -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- График заказов по месяцам -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-lg font-semibold mb-4">Динамика заказов</h2>
                    <div>
                        <canvas id="ordersChart" width="400" height="300"></canvas>
                    </div>
                </div>
                
                <!-- Товары с низким запасом -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-lg font-semibold mb-4">Срочные заказы (низкий запас)</h2>
                    <?php if (empty($lowStockItems)): ?>
                        <p class="text-gray-500 text-center py-6">Все товары имеют достаточный запас.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Товар</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Категория</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Кол-во</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Действие</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach (array_slice($lowStockItems, 0, 5) as $item): ?>
                                        <tr>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($item['name']); ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <?php 
                                                    $categories = [
                                                        'raw_material' => 'Сырьё',
                                                        'packaging' => 'Упаковка',
                                                        'finished_product' => 'Готовая продукция'
                                                    ];
                                                    echo $categories[$item['category']] ?? $item['category']; 
                                                ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                    <?php echo $item['quantity'] . ' ' . $item['unit']; ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <a href="create_order.php?product_id=<?php echo $item['id']; ?>" class="text-teal-600 hover:text-teal-900">
                                                    Создать заказ
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (count($lowStockItems) > 5): ?>
                            <div class="mt-4 text-right">
                                <a href="low_stock.php" class="text-sm text-teal-600 hover:text-teal-800">
                                    Показать все (<?php echo count($lowStockItems); ?>) <i class="fas fa-arrow-right ml-1"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Заказы, ожидающие подтверждения -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-lg font-semibold mb-4">Заказы, ожидающие подтверждения</h2>
                    <?php if (empty($pendingOrders)): ?>
                        <p class="text-gray-500 text-center py-6">Нет заказов, ожидающих подтверждения.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Поставщик</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дата</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Сумма</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Действие</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($pendingOrders as $order): ?>
                                        <tr>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo $order['id']; ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($order['company_name']); ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('d.m.Y', strtotime($order['created_at'])); ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo number_format($order['total_amount'], 2, ',', ' '); ?> ₴
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <a href="order_details.php?id=<?php echo $order['id']; ?>" class="text-teal-600 hover:text-teal-900 mr-3">
                                                    Детали
                                                </a>
                                                <a href="#" onclick="approveOrder(<?php echo $order['id']; ?>)" class="text-green-600 hover:text-green-900">
                                                    Подтвердить
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4 text-right">
                            <a href="orders.php?status=pending" class="text-sm text-teal-600 hover:text-teal-800">
                                Управление заказами <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Последние полученные заказы -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-lg font-semibold mb-4">Последние полученные заказы</h2>
                    <?php if (empty($receivedOrders)): ?>
                        <p class="text-gray-500 text-center py-6">Нет полученных заказов.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Поставщик</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дата получения</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Сумма</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($receivedOrders as $order): ?>
                                        <tr>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo $order['id']; ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($order['company_name']); ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('d.m.Y', strtotime($order['updated_at'])); ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo number_format($order['total_amount'], 2, ',', ' '); ?> ₴
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4 text-right">
                            <a href="orders.php?status=received" class="text-sm text-teal-600 hover:text-teal-800">
                                История заказов <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Дополнительная аналитика -->
            <div class="mt-6 bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-semibold mb-4">Ежемесячные расходы на закупки</h2>
                <div class="flex items-center justify-between mb-6">
                    <div class="bg-gray-100 p-4 rounded-lg flex-1 mx-2">
                        <p class="text-sm text-gray-500">Текущий месяц</p>
                        <p class="text-2xl font-bold"><?php echo number_format($totalSpendingThisMonth, 2, ',', ' '); ?> ₴</p>
                    </div>
                    <div class="bg-gray-100 p-4 rounded-lg flex-1 mx-2">
                        <p class="text-sm text-gray-500">Средний расход за заказ</p>
                        <p class="text-2xl font-bold">
                            <?php 
                            $avgOrderAmount = $ordersThisMonth > 0 ? $totalSpendingThisMonth / $ordersThisMonth : 0;
                            echo number_format($avgOrderAmount, 2, ',', ' '); 
                            ?> ₴
                        </p>
                    </div>
                    <div class="bg-gray-100 p-4 rounded-lg flex-1 mx-2">
                        <p class="text-sm text-gray-500">Прогноз на следующий месяц</p>
                        <p class="text-2xl font-bold">
                            <?php 
                            // Простой прогноз - среднее за последние 3 месяца
                            $forecastAmount = $totalSpendingThisMonth * 1.1; // Предположим рост на 10%
                            echo number_format($forecastAmount, 2, ',', ' '); 
                            ?> ₴
                        </p>
                    </div>
                </div>
                
                <div class="mt-4 text-right">
                    <a href="reports.php" class="text-sm text-teal-600 hover:text-teal-800">
                        Подробный отчет по закупкам <i class="fas fa-arrow-right ml-1"></i>
                    </a>
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
        // График заказов по месяцам
        var ctx = document.getElementById('ordersChart').getContext('2d');
        var ordersChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($ordersByMonth, 'month_name')); ?>,
                datasets: [{
                    label: 'Количество заказов',
                    data: <?php echo json_encode(array_column($ordersByMonth, 'count')); ?>,
                    backgroundColor: 'rgba(20, 184, 166, 0.2)',
                    borderColor: 'rgba(20, 184, 166, 1)',
                    borderWidth: 2,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        precision: 0
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        
        // Функция для подтверждения заказа
        function approveOrder(orderId) {
            if (confirm('Вы уверены, что хотите подтвердить заказ #' + orderId + '?')) {
                window.location.href = 'approve_order.php?id=' + orderId;
            }
        }
    </script>
</body>
</html>