<?php
// views/warehouse/dashboard.php
// Панель управління для начальника складу

// Підключення контролера авторизації та контролера складу
require_once '../../controllers/AuthController.php';
require_once '../../controllers/WarehouseController.php';

$authController = new AuthController();
$warehouseController = new WarehouseController();

// Перевірка авторизації та ролі
if (!$authController->isLoggedIn() || !$authController->checkRole('warehouse')) {
    header('Location: ../../index.php');
    exit;
}

// Отримання даних для дашборду
$currentUser = $authController->getCurrentUser();
$inventorySummary = $warehouseController->getInventorySummary();
$recentTransactions = $warehouseController->getRecentTransactions(5);
$lowStockItems = $warehouseController->getLowStockItems();
$topMovingItems = $warehouseController->getTopMovingItems(5);

// Формування даних для графіків
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
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель управління складу - Винне виробництво</title>
    <!-- Підключення Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js для графіків -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Іконки -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Верхня навігаційна панель -->
    <nav class="bg-purple-800 text-white p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center">
                <i class="fas fa-wine-bottle text-2xl mr-3"></i>
                <h1 class="text-xl font-bold">Винне виробництво</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span><?php echo htmlspecialchars($currentUser['name']); ?> (Начальник складу)</span>
                <a href="../../controllers/logout.php" class="bg-purple-700 hover:bg-purple-600 py-2 px-4 rounded text-sm">
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
                    <div class="bg-purple-100 p-3 rounded-full mr-4">
                        <i class="fas fa-user text-purple-600"></i>
                    </div>
                    <div>
                        <p class="font-semibold"><?php echo htmlspecialchars($currentUser['name']); ?></p>
                        <p class="text-sm text-gray-500">Начальник складу</p>
                    </div>
                </div>
                
                <ul class="space-y-2">
                    <li>
                        <a href="dashboard.php" class="flex items-center p-2 bg-purple-100 text-purple-700 rounded font-medium">
                            <i class="fas fa-tachometer-alt w-5 mr-2"></i>
                            <span>Панель управління</span>
                        </a>
                    </li>
                    <li>
                        <a href="inventory.php" class="flex items-center p-2 text-gray-700 hover:bg-purple-50 rounded font-medium">
                            <i class="fas fa-boxes w-5 mr-2"></i>
                            <span>Інвентаризація</span>
                        </a>
                    </li>
                    <li>
                        <a href="receive.php" class="flex items-center p-2 text-gray-700 hover:bg-purple-50 rounded font-medium">
                            <i class="fas fa-truck-loading w-5 mr-2"></i>
                            <span>Прийом товарів</span>
                        </a>
                    </li>
                    <li>
                        <a href="issue.php" class="flex items-center p-2 text-gray-700 hover:bg-purple-50 rounded font-medium">
                            <i class="fas fa-dolly w-5 mr-2"></i>
                            <span>Видача товарів</span>
                        </a>
                    </li>
                    <li>
                        <a href="transactions.php" class="flex items-center p-2 text-gray-700 hover:bg-purple-50 rounded font-medium">
                            <i class="fas fa-exchange-alt w-5 mr-2"></i>
                            <span>Історія транзакцій</span>
                        </a>
                    </li>
                </ul>
            </div>
        </aside>
        
        <!-- Основний контент -->
        <main class="w-full md:w-3/4">
            <!-- Картки з короткою статистикою -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-3 rounded-full mr-4">
                            <i class="fas fa-boxes text-blue-500"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Всього найменувань</p>
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
                            <p class="text-sm text-gray-500">Сировина (кг)</p>
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
                            <p class="text-sm text-gray-500">Найменувань з низьким запасом</p>
                            <p class="text-2xl font-bold"><?php echo count($lowStockItems); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Основні блоки з даними -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Графік за категоріями -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-lg font-semibold mb-4">Розподіл запасів за категоріями</h2>
                    <div>
                        <canvas id="inventoryChart" width="400" height="300"></canvas>
                    </div>
                </div>
                
                <!-- Товари з низьким запасом -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-lg font-semibold mb-4">Товари з низьким запасом</h2>
                    <?php if (empty($lowStockItems)): ?>
                        <p class="text-gray-500 text-center py-6">Всі товари мають достатній запас.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Товар</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Категорія</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">К-ть</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Мін. запас</th>
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
                                                        'raw_material' => 'Сировина',
                                                        'packaging' => 'Упаковка',
                                                        'finished_product' => 'Готова продукція'
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
                
                <!-- Останні транзакції -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-lg font-semibold mb-4">Останні транзакції</h2>
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
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">К-ть</th>
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
                                                        Надходження
                                                    </span>
                                                <?php else: ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                        Видаток
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
                                Показати всі транзакції <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Топ рухомих товарів -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-lg font-semibold mb-4">Топ активних товарів</h2>
                    <?php if (empty($topMovingItems)): ?>
                        <p class="text-gray-500 text-center py-6">Дані відсутні.</p>
                    <?php else: ?>
                        <div>
                            <?php foreach ($topMovingItems as $index => $item): ?>
                                <div class="mb-4">
                                    <div class="flex justify-between mb-1">
                                        <span class="text-sm font-medium text-gray-700">
                                            <?php echo ($index + 1) . '. ' . htmlspecialchars($item['name']); ?>
                                        </span>
                                        <span class="text-sm text-gray-500">
                                            <?php echo $item['transaction_count']; ?> транзакцій
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
            &copy; <?php echo date('Y'); ?> Винне виробництво. Система автоматизації процесів.
        </div>
    </footer>
    
    <!-- JavaScript для ініціалізації графіків -->
    <script>
        // Графік за категоріями
        var ctx = document.getElementById('inventoryChart').getContext('2d');
        var inventoryChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_map(function($cat) {
                    $labels = [
                        'raw_material' => 'Сировина',
                        'packaging' => 'Упаковка',
                        'finished_product' => 'Готова продукція'
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