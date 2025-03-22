<?php
// views/warehouse/transactions.php
// Страница для просмотра истории транзакций склада

// Подключение контроллеров
require_once '../../controllers/AuthController.php';
require_once '../../controllers/WarehouseController.php';

$authController = new AuthController();
$warehouseController = new WarehouseController();

// Проверка авторизации и роли
if (!$authController->isLoggedIn() || !$authController->checkRole(['warehouse', 'admin'])) {
    header('Location: ../../index.php');
    exit;
}

// Получение данных для страницы
$currentUser = $authController->getCurrentUser();

// Фильтры для транзакций
$filterType = isset($_GET['type']) ? $_GET['type'] : '';
$filterProduct = isset($_GET['product_id']) ? $_GET['product_id'] : '';
$filterDateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filterDateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$filterUser = isset($_GET['user_id']) ? $_GET['user_id'] : '';
$filterReferenceType = isset($_GET['reference_type']) ? $_GET['reference_type'] : '';

// Применение фильтров для получения списка транзакций
$transactions = $warehouseController->getFilteredTransactions(
    $filterType,
    $filterProduct,
    $filterDateFrom,
    $filterDateTo,
    $filterUser,
    $filterReferenceType
);

// Получение списка продуктов для фильтра
$products = $warehouseController->getInventorySummary();

// Получение списка пользователей для фильтра
$users = $warehouseController->getAllUsers();

// Получение сводной информации для статистики
$transactionStats = $warehouseController->getTransactionStatistics(
    $filterDateFrom ?: date('Y-m-d', strtotime('-30 days')),
    $filterDateTo ?: date('Y-m-d')
);

// Категории для отображения
$categories = [
    'raw_material' => 'Сырьё',
    'packaging' => 'Упаковка',
    'finished_product' => 'Готовая продукция'
];

// Типы транзакций для отображения
$transactionTypes = [
    'in' => 'Приход',
    'out' => 'Расход'
];

// Типы ссылок для отображения
$referenceTypes = [
    'order' => 'Заказ',
    'production' => 'Производство',
    'adjustment' => 'Корректировка'
];

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>История транзакций - Винное производство</title>
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
                <span><?php echo htmlspecialchars($currentUser['name']); ?> (<?php echo $currentUser['role'] === 'admin' ? 'Администратор' : 'Начальник склада'; ?>)</span>
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
                        <p class="text-sm text-gray-500"><?php echo $currentUser['role'] === 'admin' ? 'Администратор' : 'Начальник склада'; ?></p>
                    </div>
                </div>
                
                <ul class="space-y-2">
                    <li>
                        <a href="dashboard.php" class="flex items-center p-2 text-gray-700 hover:bg-purple-50 rounded font-medium">
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
                        <a href="transactions.php" class="flex items-center p-2 bg-purple-100 text-purple-700 rounded font-medium">
                            <i class="fas fa-exchange-alt w-5 mr-2"></i>
                            <span>История транзакций</span>
                        </a>
                    </li>
                    <?php if ($currentUser['role'] === 'admin'): ?>
                    <li>
                        <a href="../admin/dashboard.php" class="flex items-center p-2 text-gray-700 hover:bg-purple-50 rounded font-medium">
                            <i class="fas fa-user-shield w-5 mr-2"></i>
                            <span>Панель администратора</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <!-- Блок статистики транзакций -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <h3 class="font-semibold text-lg mb-3">Статистика транзакций</h3>
                <?php if (!empty($transactionStats)): ?>
                <ul class="space-y-3">
                    <li class="flex justify-between">
                        <span class="text-gray-600">Всего транзакций:</span>
                        <span class="font-semibold"><?php echo $transactionStats['total_count']; ?></span>
                    </li>
                    <li class="flex justify-between">
                        <span class="text-gray-600">Приход:</span>
                        <span class="font-semibold"><?php echo $transactionStats['in_count']; ?></span>
                    </li>
                    <li class="flex justify-between">
                        <span class="text-gray-600">Расход:</span>
                        <span class="font-semibold"><?php echo $transactionStats['out_count']; ?></span>
                    </li>
                    <li class="border-t border-gray-200 pt-3 mt-2">
                        <div class="flex justify-between">
                            <span class="text-gray-600">По заказам:</span>
                            <span class="font-semibold"><?php echo $transactionStats['order_count']; ?></span>
                        </div>
                    </li>
                    <li class="flex justify-between">
                        <span class="text-gray-600">По производству:</span>
                        <span class="font-semibold"><?php echo $transactionStats['production_count']; ?></span>
                    </li>
                    <li class="flex justify-between">
                        <span class="text-gray-600">Корректировки:</span>
                        <span class="font-semibold"><?php echo $transactionStats['adjustment_count']; ?></span>
                    </li>
                </ul>
                <?php else: ?>
                <p class="text-gray-500 text-center py-6">Нет данных для отображения</p>
                <?php endif; ?>
            </div>
            
            <!-- Быстрые ссылки -->
            <div class="bg-white rounded-lg shadow-md p-4">
                <h3 class="font-semibold text-lg mb-3">Быстрые фильтры</h3>
                <ul class="space-y-2">
                    <li>
                        <a href="transactions.php" class="text-purple-600 hover:text-purple-800 block py-1">
                            <i class="fas fa-redo-alt mr-1"></i> Сбросить все фильтры
                        </a>
                    </li>
                    <li>
                        <a href="?type=in" class="text-gray-600 hover:text-purple-800 block py-1">
                            <i class="fas fa-arrow-down mr-1"></i> Только приход
                        </a>
                    </li>
                    <li>
                        <a href="?type=out" class="text-gray-600 hover:text-purple-800 block py-1">
                            <i class="fas fa-arrow-up mr-1"></i> Только расход
                        </a>
                    </li>
                    <li>
                        <a href="?date_from=<?php echo date('Y-m-d', strtotime('-7 days')); ?>" class="text-gray-600 hover:text-purple-800 block py-1">
                            <i class="fas fa-calendar-week mr-1"></i> За последнюю неделю
                        </a>
                    </li>
                    <li>
                        <a href="?date_from=<?php echo date('Y-m-01'); ?>&date_to=<?php echo date('Y-m-t'); ?>" class="text-gray-600 hover:text-purple-800 block py-1">
                            <i class="fas fa-calendar-alt mr-1"></i> За текущий месяц
                        </a>
                    </li>
                </ul>
            </div>
        </aside>
        
        <!-- Основной контент -->
        <main class="w-full md:w-3/4">
            <!-- Фильтры транзакций -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">Фильтры транзакций</h2>
                
                <form method="GET" action="" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Тип транзакции</label>
                            <select id="type" name="type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                                <option value="">Все типы</option>
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
                                <option value="">Все товары</option>
                                <?php foreach ($products as $product): ?>
                                <option value="<?php echo $product['id']; ?>" <?php echo $filterProduct == $product['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($product['name']); ?> (<?php echo $categories[$product['category']] ?? $product['category']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="reference_type" class="block text-sm font-medium text-gray-700 mb-1">Тип операции</label>
                            <select id="reference_type" name="reference_type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                                <option value="">Все операции</option>
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
                            <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">Дата с</label>
                            <input type="date" id="date_from" name="date_from" value="<?php echo $filterDateFrom; ?>"
                                  class="block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                        </div>
                        
                        <div>
                            <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">Дата по</label>
                            <input type="date" id="date_to" name="date_to" value="<?php echo $filterDateTo; ?>"
                                  class="block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                        </div>
                        
                        <div>
                            <label for="user_id" class="block text-sm font-medium text-gray-700 mb-1">Пользователь</label>
                            <select id="user_id" name="user_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                                <option value="">Все пользователи</option>
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
                            Сбросить
                        </a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-purple-600 hover:bg-purple-700">
                            Применить фильтры
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- График движения товаров -->
            <?php if (!empty($transactionStats['chart_data'])): ?>
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Динамика движения товаров</h2>
                <div class="h-64">
                    <canvas id="transactionsChart"></canvas>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Таблица транзакций -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-800">История транзакций</h2>
                    <?php if (!empty($transactions)): ?>
                    <div class="text-sm text-gray-500">
                        Найдено: <?php echo count($transactions); ?> транзакций
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if (empty($transactions)): ?>
                <div class="text-center py-6">
                    <p class="text-gray-500">Транзакции не найдены</p>
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
                                    Количество
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Операция
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Пользователь
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
                                        Приход
                                    </span>
                                    <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                        Расход
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $transaction['quantity'] . ' ' . $transaction['unit']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php
                                    $referenceTypeLabels = [
                                        'order' => 'Заказ',
                                        'production' => 'Производство',
                                        'adjustment' => 'Корректировка'
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
            &copy; <?php echo date('Y'); ?> Винное производство. Система автоматизации процессов.
        </div>
    </footer>
    
    <!-- JavaScript для графиков -->
    <?php if (!empty($transactionStats['chart_data'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // График транзакций
            var ctx = document.getElementById('transactionsChart').getContext('2d');
            var transactionsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($transactionStats['chart_data']['labels']); ?>,
                    datasets: [
                        {
                            label: 'Приход',
                            data: <?php echo json_encode($transactionStats['chart_data']['in_data']); ?>,
                            backgroundColor: 'rgba(16, 185, 129, 0.2)',
                            borderColor: 'rgba(16, 185, 129, 1)',
                            borderWidth: 2,
                            tension: 0.3
                        },
                        {
                            label: 'Расход',
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