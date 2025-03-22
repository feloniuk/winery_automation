<?php
// views/admin/warehouse.php
// Страница для просмотра и управления складом для администратора

// Подключение контроллеров
require_once '../../controllers/AuthController.php';
require_once '../../controllers/WarehouseController.php';
require_once '../../controllers/AdminController.php';

$authController = new AuthController();
$warehouseController = new WarehouseController();
$adminController = new AdminController();

// Проверка авторизации и роли
if (!$authController->isLoggedIn() || !$authController->checkRole('admin')) {
    header('Location: ../../index.php');
    exit;
}

// Получение данных для страницы
$currentUser = $authController->getCurrentUser();
$inventorySummary = $warehouseController->getInventorySummary();
$lowStockItems = $warehouseController->getLowStockItems();
$recentTransactions = $warehouseController->getRecentTransactions(10);
$topMovingItems = $warehouseController->getTopMovingItems(5);

// Фильтрация по категории
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';
if ($categoryFilter) {
    $inventorySummary = array_filter($inventorySummary, function($item) use ($categoryFilter) {
        return $item['category'] === $categoryFilter;
    });
}

// Поиск по имени
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($searchTerm) {
    $inventorySummary = array_filter($inventorySummary, function($item) use ($searchTerm) {
        return stripos($item['name'], $searchTerm) !== false;
    });
}

// Категории для отображения
$categories = [
    'raw_material' => 'Сырьё',
    'packaging' => 'Упаковка',
    'finished_product' => 'Готовая продукция'
];

// Формирование данных для графиков
$categoryCounts = [];
foreach ($inventorySummary as $item) {
    $category = $item['category'];
    if (!isset($categoryCounts[$category])) {
        $categoryCounts[$category] = 0;
    }
    $categoryCounts[$category] += $item['quantity'];
}

// Обработка формы для изменения товара
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Обновление минимального запаса товара
    if (isset($_POST['update_min_stock'])) {
        $productId = $_POST['product_id'] ?? '';
        $minStock = (int)($_POST['min_stock'] ?? 0);
        
        if (empty($productId)) {
            $error = "Не указан идентификатор товара";
        } else {
            $product = $warehouseController->getProductDetails($productId);
            if ($product) {
                $result = $warehouseController->updateProduct(
                    $productId,
                    $product['name'],
                    $product['category'],
                    $product['unit'],
                    $minStock,
                    $product['description']
                );
                
                if ($result['success']) {
                    $message = "Минимальный запас товара успешно обновлен";
                    // Обновляем список товаров
                    $inventorySummary = $warehouseController->getInventorySummary();
                    $lowStockItems = $warehouseController->getLowStockItems();
                } else {
                    $error = $result['message'];
                }
            } else {
                $error = "Товар не найден";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление складом - Админ-панель</title>
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
                        <a href="warehouse.php" class="flex items-center p-2 bg-indigo-100 text-indigo-700 rounded font-medium">
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
            
            <!-- Блок с товарами с низким запасом -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <h3 class="font-semibold text-lg mb-3">Товары с низким запасом</h3>
                
                <?php if (empty($lowStockItems)): ?>
                <p class="text-green-600 text-center py-2">Все товары имеют достаточный запас</p>
                <?php else: ?>
                <ul class="space-y-2">
                    <?php foreach (array_slice($lowStockItems, 0, 8) as $item): ?>
                    <li class="p-2 hover:bg-red-50 rounded">
                        <a href="?search=<?php echo urlencode($item['name']); ?>" class="flex justify-between items-center">
                            <span class="text-red-600"><?php echo htmlspecialchars($item['name']); ?></span>
                            <span class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded-full">
                                <?php echo $item['quantity'] . ' ' . $item['unit']; ?>
                            </span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php if (count($lowStockItems) > 8): ?>
                <div class="mt-2 text-center">
                    <a href="#" class="text-indigo-600 hover:text-indigo-800 text-sm">
                        Показать все (<?php echo count($lowStockItems); ?>)
                    </a>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <!-- Статистика по категориям -->
            <div class="bg-white rounded-lg shadow-md p-4">
                <h3 class="font-semibold text-lg mb-3">Запасы по категориям</h3>
                <canvas id="categoriesChart" class="mb-2"></canvas>
                
                <?php foreach ($categories as $code => $name): ?>
                <?php $amount = $categoryCounts[$code] ?? 0; ?>
                <div class="flex justify-between items-center mb-2">
                    <span class="text-gray-600"><?php echo $name; ?></span>
                    <span class="font-semibold"><?php echo $amount; ?> ед.</span>
                </div>
                <?php endforeach; ?>
            </div>
        </aside>
        
        <!-- Основной контент -->
        <main class="w-full md:w-3/4">
            <!-- Заголовок и фильтры -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold text-gray-800">Управление складом</h2>
                    <a href="../warehouse/inventory.php" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded">
                        <i class="fas fa-edit mr-1"></i> Редактировать инвентарь
                    </a>
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
                
                <!-- Фильтры и поиск -->
                <div class="mb-6 flex flex-wrap items-center justify-between space-y-3 md:space-y-0">
                    <div class="flex space-x-2">
                        <a href="warehouse.php" class="<?php echo empty($categoryFilter) ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-800'; ?> px-3 py-1 rounded-full text-xs">
                            Все категории
                        </a>
                        <a href="?category=raw_material" class="<?php echo $categoryFilter === 'raw_material' ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-800'; ?> px-3 py-1 rounded-full text-xs">
                            Сырьё
                        </a>
                        <a href="?category=packaging" class="<?php echo $categoryFilter === 'packaging' ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-800'; ?> px-3 py-1 rounded-full text-xs">
                            Упаковка
                        </a>
                        <a href="?category=finished_product" class="<?php echo $categoryFilter === 'finished_product' ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-800'; ?> px-3 py-1 rounded-full text-xs">
                            Готовая продукция
                        </a>
                    </div>
                    <form method="GET" action="" class="flex max-w-xs">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" 
                               placeholder="Поиск товаров..." 
                               class="block w-full rounded-l-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <button type="submit" class="inline-flex items-center px-4 rounded-r-md border border-l-0 border-gray-300 bg-gray-50 text-gray-500 sm:text-sm">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
                
                <!-- Информация о количестве товаров -->
                <div class="text-sm text-gray-500 mb-4">
                    Найдено: <?php echo count($inventorySummary); ?> товаров
                </div>
                
                <!-- Таблица товаров -->
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
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Действия
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($inventorySummary)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                    <?php 
                                    if ($categoryFilter || $searchTerm) {
                                        echo 'Товары не найдены. Попробуйте изменить фильтры.';
                                    } else {
                                        echo 'Товары отсутствуют. Добавьте первый товар.';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($inventorySummary as $item): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $categories[$item['category']] ?? $item['category']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $item['quantity'] . ' ' . $item['unit']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span id="min_stock_<?php echo $item['id']; ?>"><?php echo $item['min_stock']; ?></span> <?php echo $item['unit']; ?>
                                        <button onclick="showMinStockEdit(<?php echo $item['id']; ?>)" class="ml-2 text-indigo-600 hover:text-indigo-900">
                                            <i class="fas fa-pencil-alt"></i>
                                        </button>
                                        
                                        <!-- Скрытая форма для редактирования -->
                                        <form id="min_stock_form_<?php echo $item['id']; ?>" method="POST" action="" class="hidden mt-1">
                                            <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                            <div class="flex items-center">
                                                <input type="number" name="min_stock" value="<?php echo $item['min_stock']; ?>" min="0" 
                                                       class="block w-16 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                <button type="submit" name="update_min_stock" class="ml-2 bg-indigo-600 hover:bg-indigo-700 text-white py-1 px-2 rounded text-xs">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button" onclick="hideMinStockEdit(<?php echo $item['id']; ?>)" class="ml-1 bg-gray-200 hover:bg-gray-300 text-gray-800 py-1 px-2 rounded text-xs">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </form>
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
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="../warehouse/inventory.php?action=view&id=<?php echo $item['id']; ?>" class="text-indigo-600 hover:text-indigo-900">
                                            Детали
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Список последних транзакций -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-800">Последние транзакции</h2>
                    <a href="../warehouse/transactions.php" class="text-indigo-600 hover:text-indigo-800 text-sm">
                        Все транзакции <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                
                <?php if (empty($recentTransactions)): ?>
                <div class="text-center py-6">
                    <p class="text-gray-500">Транзакции отсутствуют</p>
                </div>
                <?php else: ?>
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
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
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
    
    <!-- JavaScript для графиков и интерактивности -->
    <script>
        // График по категориям
        var categoriesCtx = document.getElementById('categoriesChart').getContext('2d');
        var categoriesChart = new Chart(categoriesCtx, {
            type: 'doughnut',
            data: {
                labels: [
                    <?php 
                    foreach ($categories as $code => $name) {
                        if (isset($categoryCounts[$code]) && $categoryCounts[$code] > 0) {
                            echo "'$name', ";
                        }
                    }
                    ?>
                ],
                datasets: [{
                    data: [
                        <?php 
                        foreach ($categories as $code => $name) {
                            if (isset($categoryCounts[$code]) && $categoryCounts[$code] > 0) {
                                echo "{$categoryCounts[$code]}, ";
                            }
                        }
                        ?>
                    ],
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
                plugins: {
                    legend: {
                        display: false
                    }
                },
                responsive: true,
                maintainAspectRatio: true
            }
        });
        
        // График активности на складе
        // Генерируем случайные данные для демонстрации
        var activityLabels = [];
        var inData = [];
        var outData = [];
        
        // Последние 14 дней
        for (var i = 13; i >= 0; i--) {
            var date = new Date();
            date.setDate(date.getDate() - i);
            activityLabels.push(date.getDate() + '.' + (date.getMonth() + 1));
            
            // Генерируем случайные данные
            inData.push(Math.floor(Math.random() * 15) + 5);
            outData.push(Math.floor(Math.random() * 10) + 3);
        }
        
        var warehouseActivityCtx = document.getElementById('warehouseActivityChart').getContext('2d');
        var warehouseActivityChart = new Chart(warehouseActivityCtx, {
            type: 'line',
            data: {
                labels: activityLabels,
                datasets: [
                    {
                        label: 'Приход',
                        data: inData,
                        backgroundColor: 'rgba(16, 185, 129, 0.2)',
                        borderColor: 'rgba(16, 185, 129, 1)',
                        borderWidth: 2,
                        tension: 0.3
                    },
                    {
                        label: 'Расход',
                        data: outData,
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
        
        // Функции для управления редактированием минимального запаса
        function showMinStockEdit(productId) {
            document.getElementById('min_stock_' + productId).classList.add('hidden');
            document.getElementById('min_stock_form_' + productId).classList.remove('hidden');
        }
        
        function hideMinStockEdit(productId) {
            document.getElementById('min_stock_' + productId).classList.remove('hidden');
            document.getElementById('min_stock_form_' + productId).classList.add('hidden');
        }
    </script>
</body>
</html>