<?php
// views/admin/warehouse.php
// Сторінка для перегляду та управління складом для адміністратора

// Підключення контролерів
require_once '../../controllers/AuthController.php';
require_once '../../controllers/WarehouseController.php';
require_once '../../controllers/AdminController.php';

$authController = new AuthController();
$warehouseController = new WarehouseController();
$adminController = new AdminController();

// Перевірка авторизації та ролі
if (!$authController->isLoggedIn() || !$authController->checkRole('admin')) {
    header('Location: ../../index.php');
    exit;
}

// Отримання даних для сторінки
$currentUser = $authController->getCurrentUser();
$inventorySummary = $warehouseController->getInventorySummary();
$lowStockItems = $warehouseController->getLowStockItems();
$recentTransactions = $warehouseController->getRecentTransactions(10);
$topMovingItems = $warehouseController->getTopMovingItems(5);

// Фільтрація за категорією
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';
if ($categoryFilter) {
    $inventorySummary = array_filter($inventorySummary, function($item) use ($categoryFilter) {
        return $item['category'] === $categoryFilter;
    });
}

// Пошук за ім'ям
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($searchTerm) {
    $inventorySummary = array_filter($inventorySummary, function($item) use ($searchTerm) {
        return stripos($item['name'], $searchTerm) !== false;
    });
}

// Категорії для відображення
$categories = [
    'raw_material' => 'Сировина',
    'packaging' => 'Упаковка',
    'finished_product' => 'Готова продукція'
];

// Формування даних для графіків
$categoryCounts = [];
foreach ($inventorySummary as $item) {
    $category = $item['category'];
    if (!isset($categoryCounts[$category])) {
        $categoryCounts[$category] = 0;
    }
    $categoryCounts[$category] += $item['quantity'];
}

// Обробка форми для зміни товару
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Оновлення мінімального запасу товару
    if (isset($_POST['update_min_stock'])) {
        $productId = $_POST['product_id'] ?? '';
        $minStock = (int)($_POST['min_stock'] ?? 0);
        
        if (empty($productId)) {
            $error = "Не вказано ідентифікатор товару";
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
                    $message = "Мінімальний запас товару успішно оновлено";
                    // Оновлюємо список товарів
                    $inventorySummary = $warehouseController->getInventorySummary();
                    $lowStockItems = $warehouseController->getLowStockItems();
                } else {
                    $error = $result['message'];
                }
            } else {
                $error = "Товар не знайдено";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управління складом - Панель адміністратора</title>
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
    
    <!-- Бічна панель та основний контент -->
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
                        <a href="dashboard.php" class="flex items-center p-2 text-gray-700 hover:bg-indigo-50 rounded font-medium">
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
                        <a href="warehouse.php" class="flex items-center p-2 bg-indigo-100 text-indigo-700 rounded font-medium">
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
            
            <!-- Блок з товарами з низьким запасом -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <h3 class="font-semibold text-lg mb-3">Товари з низьким запасом</h3>
                
                <?php if (empty($lowStockItems)): ?>
                <p class="text-green-600 text-center py-2">Всі товари мають достатній запас</p>
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
                        Показати всі (<?php echo count($lowStockItems); ?>)
                    </a>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <!-- Статистика за категоріями -->
            <div class="bg-white rounded-lg shadow-md p-4">
                <h3 class="font-semibold text-lg mb-3">Запаси за категоріями</h3>
                <canvas id="categoriesChart" class="mb-2"></canvas>
                
                <?php foreach ($categories as $code => $name): ?>
                <?php $amount = $categoryCounts[$code] ?? 0; ?>
                <div class="flex justify-between items-center mb-2">
                    <span class="text-gray-600"><?php echo $name; ?></span>
                    <span class="font-semibold"><?php echo $amount; ?> од.</span>
                </div>
                <?php endforeach; ?>
            </div>
        </aside>
        
        <!-- Основний контент -->
        <main class="w-full md:w-3/4">
            <!-- Заголовок та фільтри -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold text-gray-800">Управління складом</h2>
                    <a href="../warehouse/inventory.php" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded">
                        <i class="fas fa-edit mr-1"></i> Редагувати інвентар
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
                
                <!-- Фільтри та пошук -->
                <div class="mb-6 flex flex-wrap items-center justify-between space-y-3 md:space-y-0">
                    <div class="flex space-x-2">
                        <a href="warehouse.php" class="<?php echo empty($categoryFilter) ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-800'; ?> px-3 py-1 rounded-full text-xs">
                            Всі категорії
                        </a>
                        <a href="?category=raw_material" class="<?php echo $categoryFilter === 'raw_material' ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-800'; ?> px-3 py-1 rounded-full text-xs">
                            Сировина
                        </a>
                        <a href="?category=packaging" class="<?php echo $categoryFilter === 'packaging' ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-800'; ?> px-3 py-1 rounded-full text-xs">
                            Упаковка
                        </a>
                        <a href="?category=finished_product" class="<?php echo $categoryFilter === 'finished_product' ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-800'; ?> px-3 py-1 rounded-full text-xs">
                            Готова продукція
                        </a>
                    </div>
                    <form method="GET" action="" class="flex max-w-xs">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" 
                               placeholder="Пошук товарів..." 
                               class="block w-full rounded-l-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <button type="submit" class="inline-flex items-center px-4 rounded-r-md border border-l-0 border-gray-300 bg-gray-50 text-gray-500 sm:text-sm">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
                
                <!-- Інформація про кількість товарів -->
                <div class="text-sm text-gray-500 mb-4">
                    Знайдено: <?php echo count($inventorySummary); ?> товарів
                </div>
                
                <!-- Таблиця товарів -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Назва
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Категорія
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Запас
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Мін. запас
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Статус
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Дії
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($inventorySummary)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                    <?php 
                                    if ($categoryFilter || $searchTerm) {
                                        echo 'Товари не знайдені. Спробуйте змінити фільтри.';
                                    } else {
                                        echo 'Товари відсутні. Додайте перший товар.';
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
                                        
                                        <!-- Прихована форма для редагування -->
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
                                            Низький запас
                                        </span>
                                        <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            В наявності
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="../warehouse/inventory.php?action=view&id=<?php echo $item['id']; ?>" class="text-indigo-600 hover:text-indigo-900">
                                            Деталі
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Список останніх транзакцій -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-800">Останні транзакції</h2>
                    <a href="../warehouse/transactions.php" class="text-indigo-600 hover:text-indigo-800 text-sm">
                        Всі транзакції <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                
                <?php if (empty($recentTransactions)): ?>
                <div class="text-center py-6">
                    <p class="text-gray-500">Транзакції відсутні</p>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дата</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Товар</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Тип</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Кількість</th>
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
                                                Надходження
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                Витрата
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
            &copy; <?php echo date('Y'); ?> Винне виробництво. Система автоматизації процесів.
        </div>
    </footer>
    
    <!-- JavaScript для графіків та інтерактивності -->
    <script>
        // Графік за категоріями
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
        
        // Функції для керування редагуванням мінімального запасу
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