<?php
// views/warehouse/transactions.php
// Сторінка для перегляду історії транзакцій складу

// Підключення контролерів
require_once '../../controllers/AuthController.php';
require_once '../../controllers/WarehouseController.php';

$authController = new AuthController();
$warehouseController = new WarehouseController();

// Перевірка авторизації та ролі
if (!$authController->isLoggedIn() || !$authController->checkRole(['warehouse', 'admin'])) {
    header('Location: ../../index.php');
    exit;
}

// Отримання даних для сторінки
$currentUser = $authController->getCurrentUser();

// Фільтри для транзакцій
$filterType = isset($_GET['type']) ? $_GET['type'] : '';
$filterProduct = isset($_GET['product_id']) ? $_GET['product_id'] : '';
$filterDateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filterDateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$filterUser = isset($_GET['user_id']) ? $_GET['user_id'] : '';
$filterReferenceType = isset($_GET['reference_type']) ? $_GET['reference_type'] : '';

// Застосування фільтрів для отримання списку транзакцій
$transactions = $warehouseController->getFilteredTransactions(
    $filterType,
    $filterProduct,
    $filterDateFrom,
    $filterDateTo,
    $filterUser,
    $filterReferenceType
);

// Отримання списку продуктів для фільтру
$products = $warehouseController->getInventorySummary();

// Отримання списку користувачів для фільтру
$users = $warehouseController->getAllUsers();

// Отримання зведеної інформації для статистики
$transactionStats = $warehouseController->getTransactionStatistics(
    $filterDateFrom ?: date('Y-m-d', strtotime('-30 days')),
    $filterDateTo ?: date('Y-m-d')
);

// Категорії для відображення
$categories = [
    'raw_material' => 'Сировина',
    'packaging' => 'Упаковка',
    'finished_product' => 'Готова продукція'
];

// Типи транзакцій для відображення
$transactionTypes = [
    'in' => 'Надходження',
    'out' => 'Видаток'
];

// Типи посилань для відображення
$referenceTypes = [
    'order' => 'Замовлення',
    'production' => 'Виробництво',
    'adjustment' => 'Коригування'
];

?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Історія транзакцій - Винне виробництво</title>
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
                <span><?php echo htmlspecialchars($currentUser['name']); ?> (<?php echo $currentUser['role'] === 'admin' ? 'Адміністратор' : 'Начальник складу'; ?>)</span>
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
                        <p class="text-sm text-gray-500"><?php echo $currentUser['role'] === 'admin' ? 'Адміністратор' : 'Начальник складу'; ?></p>
                    </div>
                </div>
                
                <ul class="space-y-2">
                    <li>
                        <a href="dashboard.php" class="flex items-center p-2 text-gray-700 hover:bg-purple-50 rounded font-medium">
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
                        <a href="transactions.php" class="flex items-center p-2 bg-purple-100 text-purple-700 rounded font-medium">
                            <i class="fas fa-exchange-alt w-5 mr-2"></i>
                            <span>Історія транзакцій</span>
                        </a>
                    </li>
                    <?php if ($currentUser['role'] === 'admin'): ?>
                    <li>
                        <a href="../admin/dashboard.php" class="flex items-center p-2 text-gray-700 hover:bg-purple-50 rounded font-medium">
                            <i class="fas fa-user-shield w-5 mr-2"></i>
                            <span>Панель адміністратора</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <!-- Блок статистики транзакцій -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <h3 class="font-semibold text-lg mb-3">Статистика транзакцій</h3>
                <?php if (!empty($transactionStats)): ?>
                <ul class="space-y-3">
                    <li class="flex justify-between">
                        <span class="text-gray-600">Всього транзакцій:</span>
                        <span class="font-semibold"><?php echo $transactionStats['total_count']; ?></span>
                    </li>
                    <li class="flex justify-between">
                        <span class="text-gray-600">Надходження:</span>
                        <span class="font-semibold"><?php echo $transactionStats['in_count']; ?></span>
                    </li>
                    <li class="flex justify-between">
                        <span class="text-gray-600">Видаток:</span>
                        <span class="font-semibold"><?php echo $transactionStats['out_count']; ?></span>
                    </li>
                    <li class="border-t border-gray-200 pt-3 mt-2">
                        <div class="flex justify-between">
                            <span class="text-gray-600">За замовленнями:</span>
                            <span class="font-semibold"><?php echo $transactionStats['order_count']; ?></span>
                        </div>
                    </li>
                    <li class="flex justify-between">
                        <span class="text-gray-600">За виробництвом:</span>
                        <span class="font-semibold"><?php echo $transactionStats['production_count']; ?></span>
                    </li>
                    <li class="flex justify-between">
                        <span class="text-gray-600">Коригування:</span>
                        <span class="font-semibold"><?php echo $transactionStats['adjustment_count']; ?></span>
                    </li>
                </ul>
                <?php else: ?>
                <p class="text-gray-500 text-center py-6">Немає даних для відображення</p>
                <?php endif; ?>
            </div>
            
            <!-- Швидкі посилання -->
            <div class="bg-white rounded-lg shadow-md p-4">
                <h3 class="font-semibold text-lg mb-3">Швидкі фільтри</h3>
                <ul class="space-y-2">
                    <li>
                        <a href="transactions.php" class="text-purple-600 hover:text-purple-800 block py-1">
                            <i class="fas fa-redo-alt mr-1"></i> Скинути всі фільтри
                        </a>
                    </li>
                    <li>
                        <a href="?type=in" class="text-gray-600 hover:text-purple-800 block py-1">
                            <i class="fas fa-arrow-down mr-1"></i> Тільки надходження
                        </a>
                    </li>
                    <li>
                        <a href="?type=out" class="text-gray-600 hover:text-purple-800 block py-1">
                            <i class="fas fa-arrow-up mr-1"></i> Тільки видаток
                        </a>
                    </li>
                    <li>
                        <a href="?date_from=<?php echo date('Y-m-d', strtotime('-7 days')); ?>" class="text-gray-600 hover:text-purple-800 block py-1">
                            <i class="fas fa-calendar-week mr-1"></i> За останній тиждень
                        </a>
                    </li>
                    <li>
                        <a href="?date_from=<?php echo date('Y-m-01'); ?>&date_to=<?php echo date('Y-m-t'); ?>" class="text-gray-600 hover:text-purple-800 block py-1">
                            <i class="fas fa-calendar-alt mr-1"></i> За поточний місяць
                        </a>
                    </li>
                </ul>
            </div>
        </aside>
        
        <!-- Основний контент -->
        <main class="w-full md:w-3/4">
            <!-- Фільтри транзакцій -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">Фільтри транзакцій</h2>
                
                <form method="GET" action="" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Тип транзакції</label>
                            <select id="type" name="type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                                <option value="">Всі типи</option>
                                <?php foreach ($transactionTypes as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo $filterType === $value ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="product_id" class="block text-sm font-medium text-gray-700 mb-1">Товар</label>
                            <select id="product_id" name="product_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                                <option value="">Всі товари</option>
                                <?php foreach ($products as $product): ?>
                                <option value="<?php echo $product['id']; ?>" <?php echo $filterProduct == $product['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($product['name']); ?> (<?php echo $categories[$product['category']] ?? $product['category']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="reference_type" class="block text-sm font-medium text-gray-700 mb-1">Тип операції</label>
                            <select id="reference_type" name="reference_type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                                <option value="">Всі операції</option>
                                <?php foreach ($referenceTypes as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo $filterReferenceType === $value ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">Дата з</label>
                            <input type="date" id="date_from" name="date_from" value="<?php echo $filterDateFrom; ?>"
                                  class="block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                        </div>
                        
                        <div>
                            <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">Дата по</label>
                            <input type="date" id="date_to" name="date_to" value="<?php echo $filterDateTo; ?>"
                                  class="block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                        </div>
                        
                        <div>
                            <label for="user_id" class="block text-sm font-medium text-gray-700 mb-1">Користувач</label>
                            <select id="user_id" name="user_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                                <option value="">Всі користувачі</option>
                                <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo $filterUser == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['name']); ?> (<?php echo $user['role']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <a href="transactions.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Скинути
                        </a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-purple-600 hover:bg-purple-700">
                            Застосувати фільтри
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Графік руху товарів -->
            <?php if (!empty($transactionStats['chart_data'])): ?>
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Динаміка руху товарів</h2>
                <div class="h-64">
                    <canvas id="transactionsChart"></canvas>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Таблиця транзакцій -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-800">Історія транзакцій</h2>
                    <?php if (!empty($transactions)): ?>
                    <div class="text-sm text-gray-500">
                        Знайдено: <?php echo count($transactions); ?> транзакцій
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if (empty($transactions)): ?>
                <div class="text-center py-6">
                    <p class="text-gray-500">Транзакції не знайдені</p>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    ID
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Дата
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Товар
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Тип
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Кількість
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Операція
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Користувач
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $transaction['id']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('d.m.Y H:i', strtotime($transaction['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($transaction['product_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
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
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $transaction['quantity'] . ' ' . $transaction['unit']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php
                                    $referenceTypeLabels = [
                                        'order' => 'Замовлення',
                                        'production' => 'Виробництво',
                                        'adjustment' => 'Коригування'
                                    ];
                                    echo $referenceTypeLabels[$transaction['reference_type']] ?? $transaction['reference_type'];
                                    
                                    if (!empty($transaction['reference_id']) && $transaction['reference_id'] > 0) {
                                        echo ' #' . $transaction['reference_id'];
                                    }
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($transaction['user_name']); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <footer class="bg-white p-4 mt-8 border-t border-gray-200">
        <div class="container mx-auto text-center text-gray-500 text-sm">
            &copy; <?php echo date('Y'); ?> Винне виробництво. Система автоматизації процесів.
        </div>
    </footer>
    
    <!-- JavaScript для графіків -->
    <?php if (!empty($transactionStats['chart_data'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Графік транзакцій
            var ctx = document.getElementById('transactionsChart').getContext('2d');
            var transactionsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($transactionStats['chart_data']['labels']); ?>,
                    datasets: [
                        {
                            label: 'Надходження',
                            data: <?php echo json_encode($transactionStats['chart_data']['in_data']); ?>,
                            backgroundColor: 'rgba(16, 185, 129, 0.2)',
                            borderColor: 'rgba(16, 185, 129, 1)',
                            borderWidth: 2,
                            tension: 0.3
                        },
                        {
                            label: 'Видаток',
                            data: <?php echo json_encode($transactionStats['chart_data']['out_data']); ?>,
                            backgroundColor: 'rgba(59, 130, 246, 0.2)',
                            borderColor: 'rgba(59, 130, 246, 1)',
                            borderWidth: 2,
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>