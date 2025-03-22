<?php
// views/warehouse/dashboard.php
// Панель управления для начальника склада

// Подключение контроллера авторизации и контроллера склада
require_once '../../controllers/AuthController.php';
require_once '../../controllers/WarehouseController.php';

$authController = new AuthController();
$warehouseController = new WarehouseController();

// Проверка авторизации и роли
if (!$authController->isLoggedIn() || !$authController->checkRole('warehouse')) {
    header('Location: ../../index.php');
    exit;
}

// Получение данных для дашборда
$currentUser = $authController->getCurrentUser();
$inventorySummary = $warehouseController->getInventorySummary();
$recentTransactions = $warehouseController->getRecentTransactions(5);
$lowStockItems = $warehouseController->getLowStockItems();
$topMovingItems = $warehouseController->getTopMovingItems(5);

// Формирование данных для графиков
$categoryCounts = [];
foreach ($inventorySummary as $item) {
    $category = $item['category'];
    if (!isset($categoryCounts[$category])) {
        $categoryCounts[$category] = 0;
    }
    $categoryCounts[$category] += $item['quantity'];
}

$chartData = [
    'labels' => array_keys($categoryCounts),
    'data' => array_values($categoryCounts)
];

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель управления склада - Винное производство</title>
    <!-- Подключение Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js для графиков -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Иконки -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Верхняя навигационная панель -->
    <nav class="bg-purple-800 text-white p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center">
                <i class="fas fa-wine-bottle text-2xl mr-3"></i>
                <h1 class="text-xl font-bold">Винное производство</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span><?php echo htmlspecialchars($currentUser['name']); ?> (Начальник склада)</span>
                <a href="../../controllers/logout.php" class="bg-purple-700 hover:bg-purple-600 py-2 px-4 rounded text-sm">
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
                    <div class="bg-purple-100 p-3 rounded-full mr-4">
                        <i class="fas fa-user text-purple-600"></i>
                    </div>
                    <div>
                        <p class="font-semibold"><?php echo htmlspecialchars($currentUser['name']); ?></p>
                        <p class="text-sm text-gray-500">Начальник склада</p>
                    </div>
                </div>
                
                <ul class="space-y-2">
                    <li>
                        <a href="dashboard.php" class="flex items-center p-2 bg-purple-100 text-purple-700 rounded font-medium">
                            <i class="fas fa-tachometer-alt w-5 mr-2"></i>
                            <span>Панель управления</span>
                        </a>
                    </li>
                    <li>
                        <a href="inventory.php" class="flex items-center p-2 text-gray-700 hover:bg-purple-50 rounded font-medium">
                            <i class="fas fa-boxes w-5 mr-2"></i>
                            <span>Инвентаризация</span>
                        </a>
                    </li>
                    <li>
                        <a href="receive.php" class="flex items-center p-2 text-gray-700 hover:bg-purple-50 rounded font-medium">
                            <i class="fas fa-truck-loading w-5 mr-2"></i>
                            <span>Приём товаров</span>
                        </a>
                    </li>
                    <li>
                        <a href="issue.php" class="flex items-center p-2 text-gray-700 hover:bg-purple-50 rounded font-medium">
                            <i class="fas fa-dolly w-5 mr-2"></i>
                            <span>Выдача товаров</span>
                        </a>
                    </li>
                    <li>
                        <a href="transactions.php" class="flex items-center p-2 text-gray-700 hover:bg-purple-50 rounded font-medium">
                            <i class="fas fa-exchange-alt w-5 mr-2"></i>
                            <span>История транзакций</span>
                        </a>
                    </li>
                </ul>
            </div>
        </aside>
        
        <!-- Основной контент -->
        <main class="w-full md:w-3/4">
            <!-- Карточки с краткой статистикой -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-3 rounded-full mr-4">
                            <i class="fas fa-boxes text-blue-500"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Всего наименований</p>
                            <p class="text-2xl font-bold"><?php echo count($inventorySummary); ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-3 rounded-full mr-4">
                            <i class="fas fa-wine-bottle text-green-500"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Сырьё (кг)</p>
                            <p class="text-2xl font-bold"><?php echo $categoryCounts['raw_material'] ?? 0; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-red-100 p-3 rounded-full mr-4">
                            <i class="fas fa-exclamation-triangle text-red-500"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Наименований с низким запасом</p>
                            <p class="text-2xl font-bold"><?php echo count($lowStockItems); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Основные блоки с данными -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- График по категориям -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-lg font-semibold mb-4">Распределение запасов по категориям</h2>
                    <div>
                        <canvas id="inventoryChart" width="400" height="300"></canvas>
                    </div>
                </div>
                
                <!-- Товары с низким запасом -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-lg font-semibold mb-4">Товары с низким запасом</h2>
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
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Мин. запас</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($lowStockItems as $item): ?>
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
                                                <?php echo $item['min_stock'] . ' ' . $item['unit']; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Последние транзакции -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-lg font-semibold mb-4">Последние транзакции</h2>
                    <?php if (empty($recentTransactions)): ?>
                        <p class="text-gray-500 text-center py-6">Транзакции отсутствуют.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дата</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Товар</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Тип</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Кол-во</th>
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
                                                        Приход
                                                    </span>
                                                <?php else: ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                        Расход
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $transaction['quantity'] . ' ' . $transaction['unit']; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4 text-right">
                            <a href="transactions.php" class="text-sm text-purple-600 hover:text-purple-800">
                                Показать все транзакции <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Топ движущихся товаров -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-lg font-semibold mb-4">Топ активных товаров</h2>
                    <?php if (empty($topMovingItems)): ?>
                        <p class="text-gray-500 text-center py-6">Данные отсутствуют.</p>
                    <?php else: ?>
                        <div>
                            <?php foreach ($topMovingItems as $index => $item): ?>
                                <div class="mb-4">
                                    <div class="flex justify-between mb-1">
                                        <span class="text-sm font-medium text-gray-700">
                                            <?php echo ($index + 1) . '. ' . htmlspecialchars($item['name']); ?>
                                        </span>
                                        <span class="text-sm text-gray-500">
                                            <?php echo $item['transaction_count']; ?> транзакций
                                        </span>
                                    </div>
                                    <div class="relative pt-1">
                                        <div class="overflow-hidden h-2 mb-1 text-xs flex rounded bg-gray-200">
                                            <div style="width:<?php echo min(100, ($item['transaction_count'] / $topMovingItems[0]['transaction_count']) * 100); ?>%" 
                                                class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-purple-500">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <footer class="bg-white p-4 mt-8 border-t border-gray-200">
        <div class="container mx-auto text-center text-gray-500 text-sm">
            &copy; <?php echo date('Y'); ?> Винное производство. Система автоматизации процессов.
        </div>
    </footer>
    
    <!-- JavaScript для инициализации графиков -->
    <script>
        // График по категориям
        var ctx = document.getElementById('inventoryChart').getContext('2d');
        var inventoryChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_map(function($cat) {
                    $labels = [
                        'raw_material' => 'Сырьё',
                        'packaging' => 'Упаковка',
                        'finished_product' => 'Готовая продукция'
                    ];
                    return $labels[$cat] ?? $cat;
                }, $chartData['labels'])); ?>,
                datasets: [{
                    data: <?php echo json_encode($chartData['data']); ?>,
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                        'rgba(255, 159, 64, 0.7)'
                    ],
                    borderColor: [
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)'
                    ],
                    borderWidth: 1
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
    </script>
</body>
</html>