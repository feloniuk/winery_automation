<?php
// views/admin/reports.php
// Страница для генерации и просмотра отчетов для администратора

// Подключение контроллеров
require_once '../../controllers/AuthController.php';
require_once '../../controllers/AdminController.php';
require_once '../../controllers/WarehouseController.php';
require_once '../../controllers/PurchasingController.php';

$authController = new AuthController();
$adminController = new AdminController();
$warehouseController = new WarehouseController();
$purchasingController = new PurchasingController();

// Проверка авторизации и роли
if (!$authController->isLoggedIn() || !$authController->checkRole('admin')) {
    header('Location: ../../index.php');
    exit;
}

// Получение данных для страницы
$currentUser = $authController->getCurrentUser();

// Получение данных для отчетов
$userStats = $adminController->getUserStatistics();
$inventoryStats = $adminController->getInventoryStatistics();
$recentTransactions = $warehouseController->getRecentTransactions(10);
$lowStockItems = $warehouseController->getLowStockItems();
$topMovingItems = $warehouseController->getTopMovingItems(5);

// Данные для отчета по месяцам
$ordersByMonth = $purchasingController->getOrderCountByMonth(6);

// Обработка запроса на генерацию отчета
$reportType = isset($_GET['report']) ? $_GET['report'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

$reportData = [];
$reportTitle = '';

if ($reportType) {
    switch ($reportType) {
        case 'inventory':
            $reportTitle = 'Отчет по инвентарю';
            $reportData = $warehouseController->getInventorySummary();
            break;
        
        case 'low_stock':
            $reportTitle = 'Отчет по товарам с низким запасом';
            $reportData = $lowStockItems;
            break;
            
        case 'transactions':
            $reportTitle = 'Отчет по транзакциям за период';
            // В реальной системе здесь был бы запрос за указанный период
            $reportData = $recentTransactions;
            break;
            
        case 'orders':
            $reportTitle = 'Отчет по заказам за период';
            // В реальной системе здесь был бы запрос за указанный период
            $orderCount = $purchasingController->getOrdersCountForPeriod($dateFrom, $dateTo);
            $totalSpending = $purchasingController->getTotalSpendingForPeriod($dateFrom, $dateTo);
            $reportData = [
                'order_count' => $orderCount,
                'total_spending' => $totalSpending,
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ];
            break;
    }
}

// Форматы для экспорта отчетов
$exportFormats = ['pdf', 'excel', 'csv'];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отчеты - Панель администратора</title>
    <!-- Подключение Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js для графиков -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        <a href="users.php" class="flex items-center p-2 text-gray-700 hover:bg-indigo-50 rounded font-medium">
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
                        <a href="reports.php" class="flex items-center p-2 bg-indigo-100 text-indigo-700 rounded font-medium">
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
            
            <!-- Блок с быстрыми действиями -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <h3 class="font-semibold text-lg mb-3">Быстрые действия</h3>
                <ul class="space-y-2">
                    <li>
                        <a href="?report=inventory" class="flex items-center p-2 text-gray-700 hover:bg-indigo-50 rounded">
                            <i class="fas fa-boxes w-5 mr-2 text-indigo-600"></i>
                            <span>Отчет по инвентарю</span>
                        </a>
                    </li>
                    <li>
                        <a href="?report=low_stock" class="flex items-center p-2 text-gray-700 hover:bg-indigo-50 rounded">
                            <i class="fas fa-exclamation-triangle w-5 mr-2 text-yellow-600"></i>
                            <span>Отчет по низкому запасу</span>
                        </a>
                    </li>
                    <li>
                        <a href="?report=transactions" class="flex items-center p-2 text-gray-700 hover:bg-indigo-50 rounded">
                            <i class="fas fa-exchange-alt w-5 mr-2 text-green-600"></i>
                            <span>Отчет по транзакциям</span>
                        </a>
                    </li>
                    <li>
                        <a href="?report=orders" class="flex items-center p-2 text-gray-700 hover:bg-indigo-50 rounded">
                            <i class="fas fa-file-invoice-dollar w-5 mr-2 text-blue-600"></i>
                            <span>Отчет по заказам</span>
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Статистика по складу -->
            <div class="bg-white rounded-lg shadow-md p-4">
                <h3 class="font-semibold text-lg mb-3">Сводка по складу</h3>
                <ul class="space-y-3">
                    <li class="flex justify-between">
                        <span class="text-gray-600">Всего товаров:</span>
                        <span class="font-semibold"><?php echo $inventoryStats['total_products']; ?></span>
                    </li>
                    <li class="flex justify-between">
                        <span class="text-gray-600">Сырьё:</span>
                        <span class="font-semibold"><?php echo $inventoryStats['raw_material_count']; ?></span>
                    </li>
                    <li class="flex justify-between">
                        <span class="text-gray-600">Упаковка:</span>
                        <span class="font-semibold"><?php echo $inventoryStats['packaging_count']; ?></span>
                    </li>
                    <li class="flex justify-between">
                        <span class="text-gray-600">Готовая продукция:</span>
                        <span class="font-semibold"><?php echo $inventoryStats['finished_product_count']; ?></span>
                    </li>
                    <li class="flex justify-between text-red-600">
                        <span>Низкий запас:</span>
                        <span class="font-semibold"><?php echo $inventoryStats['low_stock_count']; ?></span>
                    </li>
                </ul>
            </div>
        </aside>
        
        <!-- Основной контент -->
        <main class="w-full md:w-3/4">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">Отчеты</h2>
                
                <!-- Форма выбора периода для отчетов -->
                <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Выберите период для отчета</h3>
                    <form method="GET" action="" class="flex flex-wrap items-end space-x-4">
                        <input type="hidden" name="report" value="<?php echo htmlspecialchars($reportType); ?>">
                        
                        <div>
                            <label for="date_from" class="block text-sm font-medium text-gray-700">Дата начала</label>
                            <input type="date" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>" 
                                   class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        
                        <div>
                            <label for="date_to" class="block text-sm font-medium text-gray-700">Дата окончания</label>
                            <input type="date" id="date_to" name="date_to" value="<?php echo $dateTo; ?>" 
                                   class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        
                        <div>
                            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded">
                                Применить
                            </button>
                        </div>
                    </form>
                </div>
                
                <?php if ($reportType): ?>
                <!-- Содержимое отчета -->
                <div class="mb-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-medium text-gray-900"><?php echo $reportTitle; ?></h3>
                        
                        <!-- Кнопки экспорта -->
                        <div class="flex space-x-2">
                            <?php foreach ($exportFormats as $format): ?>
                            <a href="#" onclick="alert('Экспорт в <?php echo strtoupper($format); ?> будет доступен в полной версии системы')" 
                               class="bg-gray-200 hover:bg-gray-300 text-gray-800 py-1 px-3 rounded text-sm">
                                <?php echo strtoupper($format); ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <?php if ($reportType === 'inventory'): ?>
                    <!-- Отчет по инвентарю -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Название
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Категория
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Запас
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Мин. запас
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Статус
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($reportData as $item): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php 
                                        $categories = [
                                            'raw_material' => 'Сырьё',
                                            'packaging' => 'Упаковка',
                                            'finished_product' => 'Готовая продукция'
                                        ];
                                        echo $categories[$item['category']] ?? $item['category']; 
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $item['quantity'] . ' ' . $item['unit']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $item['min_stock'] . ' ' . $item['unit']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($item['quantity'] <= $item['min_stock']): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                            Низкий запас
                                        </span>
                                        <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            В наличии
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- График распределения товаров по категориям -->
                    <div class="mt-6">
                        <h4 class="text-lg font-medium text-gray-800 mb-2">Распределение товаров по категориям</h4>
                        <div class="bg-gray-50 p-4 rounded">
                            <canvas id="categoriesChart" height="200"></canvas>
                        </div>
                    </div>
                    
                    <?php elseif ($reportType === 'low_stock'): ?>
                    <!-- Отчет по товарам с низким запасом -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Название
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Категория
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Текущий запас
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Мин. запас
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Нехватка
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($reportData as $item): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php 
                                        $categories = [
                                            'raw_material' => 'Сырьё',
                                            'packaging' => 'Упаковка',
                                            'finished_product' => 'Готовая продукция'
                                        ];
                                        echo $categories[$item['category']] ?? $item['category']; 
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $item['quantity'] . ' ' . $item['unit']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $item['min_stock'] . ' ' . $item['unit']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-medium">
                                        <?php echo ($item['min_stock'] - $item['quantity']) . ' ' . $item['unit']; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php elseif ($reportType === 'transactions'): ?>
                    <!-- Отчет по транзакциям -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дата</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Товар</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Тип</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Кол-во</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Пользователь</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if ($reportData) foreach ($reportData as $transaction): ?>
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
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($transaction['user_name']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php elseif ($reportType === 'orders'): ?>
                    <!-- Отчет по заказам -->
                    <div class="mb-6">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                            <div class="bg-white p-4 rounded-lg shadow">
                                <p class="text-sm text-gray-500 mb-1">Период отчета</p>
                                <p class="text-lg font-semibold">
                                    <?php echo date('d.m.Y', strtotime($reportData['period']['from'])); ?> - 
                                    <?php echo date('d.m.Y', strtotime($reportData['period']['to'])); ?>
                                </p>
                            </div>
                            <div class="bg-white p-4 rounded-lg shadow">
                                <p class="text-sm text-gray-500 mb-1">Количество заказов</p>
                                <p class="text-lg font-semibold"><?php echo $reportData['order_count']; ?></p>
                            </div>
                            <div class="bg-white p-4 rounded-lg shadow">
                                <p class="text-sm text-gray-500 mb-1">Общая сумма заказов</p>
                                <p class="text-lg font-semibold"><?php echo number_format($reportData['total_spending'], 2, ',', ' '); ?> ₽</p>
                            </div>
                        </div>
                        
                        <!-- График заказов по месяцам -->
                        <div class="bg-white p-4 rounded-lg shadow">
                            <h4 class="text-lg font-medium text-gray-800 mb-2">Динамика заказов по месяцам</h4>
                            <div class="h-64">
                                <canvas id="ordersChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                </div>
                <?php else: ?>
                <!-- Если отчет не выбран, показываем доступные отчеты -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-gray-50 p-6 rounded-lg shadow hover:shadow-md transition-shadow">
                        <div class="mb-4">
                            <i class="fas fa-boxes text-4xl text-indigo-600"></i>
                        </div>
                        <h3 class="text-xl font-semibold mb-2">Отчет по инвентарю</h3>
                        <p class="text-gray-600 mb-4">Полный отчет о текущем состоянии инвентаря с разбивкой по категориям.</p>
                        <a href="?report=inventory" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded">
                            Сформировать отчет
                        </a>
                    </div>
                    
                    <div class="bg-gray-50 p-6 rounded-lg shadow hover:shadow-md transition-shadow">
                        <div class="mb-4">
                            <i class="fas fa-exclamation-triangle text-4xl text-yellow-600"></i>
                        </div>
                        <h3 class="text-xl font-semibold mb-2">Отчет по низкому запасу</h3>
                        <p class="text-gray-600 mb-4">Отчет о товарах с низким запасом, требующих пополнения.</p>
                        <a href="?report=low_stock" class="inline-block bg-yellow-600 hover:bg-yellow-700 text-white py-2 px-4 rounded">
                            Сформировать отчет
                        </a>
                    </div>
                    
                    <div class="bg-gray-50 p-6 rounded-lg shadow hover:shadow-md transition-shadow">
                        <div class="mb-4">
                            <i class="fas fa-exchange-alt text-4xl text-green-600"></i>
                        </div>
                        <h3 class="text-xl font-semibold mb-2">Отчет по транзакциям</h3>
                        <p class="text-gray-600 mb-4">Детальный отчет о всех транзакциях (приход/расход) за выбранный период.</p>
                        <a href="?report=transactions" class="inline-block bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded">
                            Сформировать отчет
                        </a>
                    </div>
                    
                    <div class="bg-gray-50 p-6 rounded-lg shadow hover:shadow-md transition-shadow">
                        <div class="mb-4">
                            <i class="fas fa-file-invoice-dollar text-4xl text-blue-600"></i>
                        </div>
                        <h3 class="text-xl font-semibold mb-2">Отчет по заказам</h3>
                        <p class="text-gray-600 mb-4">Статистика заказов и закупок за выбранный период.</p>
                        <a href="?report=orders" class="inline-block bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded">
                            Сформировать отчет
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Блок с быстрой статистикой -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Общая статистика</h2>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <div class="bg-indigo-50 p-4 rounded-lg">
                        <div class="flex items-center">
                            <div class="bg-indigo-100 p-3 rounded-full mr-4">
                                <i class="fas fa-users text-indigo-600"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Пользователей</p>
                                <p class="text-2xl font-bold"><?php echo $userStats['total_users']; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg">
                        <div class="flex items-center">
                            <div class="bg-green-100 p-3 rounded-full mr-4">
                                <i class="fas fa-boxes text-green-600"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Товаров на складе</p>
                                <p class="text-2xl font-bold"><?php echo $inventoryStats['total_products']; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <div class="flex items-center">
                            <div class="bg-blue-100 p-3 rounded-full mr-4">
                                <i class="fas fa-exchange-alt text-blue-600"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Транзакций за неделю</p>
                                <p class="text-2xl font-bold"><?php echo count((array)$recentTransactions); ?>+</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-red-50 p-4 rounded-lg">
                        <div class="flex items-center">
                            <div class="bg-red-100 p-3 rounded-full mr-4">
                                <i class="fas fa-exclamation-triangle text-red-600"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Товары с низким запасом</p>
                                <p class="text-2xl font-bold"><?php echo count($lowStockItems); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Топ активных товаров -->
                <div class="mb-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Наиболее активные товары</h3>
                    <div class="bg-gray-50 p-4 rounded">
                        <?php if (!empty($topMovingItems)): ?>
                            <?php foreach ($topMovingItems as $index => $item): ?>
                                <div class="mb-3">
                                    <div class="flex justify-between items-center mb-1">
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
                                                class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-indigo-500">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-gray-500 text-center py-2">Недостаточно данных</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <footer class="bg-white p-4 mt-8 border-t border-gray-200">
        <div class="container mx-auto text-center text-gray-500 text-sm">
            &copy; <?php echo date('Y'); ?> Винное производство. Система автоматизации процессов.
        </div>
    </footer>
    
    <!-- JavaScript для графиков -->
    <script>
        <?php if ($reportType === 'inventory'): ?>
        // График распределения товаров по категориям
        var categoriesData = {};
        <?php 
        $categoryData = [];
        foreach ($reportData as $item) {
            $categoryName = [
                'raw_material' => 'Сырьё',
                'packaging' => 'Упаковка',
                'finished_product' => 'Готовая продукция'
            ][$item['category']] ?? $item['category'];
            
            if (!isset($categoryData[$categoryName])) {
                $categoryData[$categoryName] = 0;
            }
            $categoryData[$categoryName]++;
        }
        ?>
        
        var ctxCategories = document.getElementById('categoriesChart').getContext('2d');
        var categoriesChart = new Chart(ctxCategories, {
            type: 'pie',
            data: {
                labels: [<?php echo "'" . implode("', '", array_keys($categoryData)) . "'"; ?>],
                datasets: [{
                    data: [<?php echo implode(", ", array_values($categoryData)); ?>],
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)'
                    ],
                    borderColor: [
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
        <?php endif; ?>
        
        <?php if ($reportType === 'orders'): ?>
        // График заказов по месяцам
        var ctxOrders = document.getElementById('ordersChart').getContext('2d');
        var ordersChart = new Chart(ctxOrders, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($ordersByMonth, 'month_name')); ?>,
                datasets: [{
                    label: 'Количество заказов',
                    data: <?php echo json_encode(array_column($ordersByMonth, 'count')); ?>,
                    backgroundColor: 'rgba(79, 70, 229, 0.2)',
                    borderColor: 'rgba(79, 70, 229, 1)',
                    borderWidth: 2,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>