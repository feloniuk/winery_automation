<?php
// views/warehouse/inventory.php
// Сторінка для перегляду та керування інвентарем на складі

// Підключення контролерів
require_once '../../controllers/AuthController.php';
require_once '../../controllers/WarehouseController.php';
require_once '../../controllers/PurchasingController.php';

$authController = new AuthController();
$warehouseController = new WarehouseController();
$purchasingController = new PurchasingController();

// Перевірка авторизації та ролі
if (!$authController->isLoggedIn() || !$authController->checkRole(['warehouse', 'admin', 'purchasing'])) {
    header('Location: ../../index.php');
    exit;
}

// Отримання даних для сторінки
$currentUser = $authController->getCurrentUser();
$inventorySummary = $warehouseController->getInventorySummary();
$lowStockItems = $warehouseController->getLowStockItems();
// Отримання даних для дашборду
$currentUser = $authController->getCurrentUser();
$pendingOrders = $purchasingController->getOrdersByStatus('pending');
$approvedOrders = $purchasingController->getOrdersByStatus('approved');
$receivedOrders = $purchasingController->getRecentReceivedOrders(5);
$lowStockItems = $purchasingController->getLowStockItems();
$suppliers = $purchasingController->getActiveSuppliers();

// Підрахунок важливих метрик
$totalActiveOrders = count($pendingOrders) + count($approvedOrders);
$totalActiveSuppliers = count($suppliers);
$totalLowStockItems = count($lowStockItems);
$ordersThisMonth = $purchasingController->getOrdersCountForPeriod(date('Y-m-01'), date('Y-m-t'));
$totalSpendingThisMonth = $purchasingController->getTotalSpendingForPeriod(date('Y-m-01'), date('Y-m-t'));

// Отримання даних для графіка замовлень за місяцями
$ordersByMonth = $purchasingController->getOrderCountByMonth(6);

// Фільтрація за категорією
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';
if ($categoryFilter) {
    $inventorySummary = array_filter($inventorySummary, function($item) use ($categoryFilter) {
        return $item['category'] === $categoryFilter;
    });
}

// Пошук за назвою
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($searchTerm) {
    $inventorySummary = array_filter($inventorySummary, function($item) use ($searchTerm) {
        return stripos($item['name'], $searchTerm) !== false;
    });
}

// Обробка дій додавання/редагування товару
$message = '';
$error = '';
$editProduct = null;

// Якщо це запит на перегляд деталей або редагування, отримуємо дані продукту
if (isset($_GET['action']) && ($_GET['action'] == 'view' || $_GET['action'] == 'edit') && isset($_GET['id'])) {
    $productId = $_GET['id'];
    $editProduct = $warehouseController->getProductDetails($productId);
    
    // Якщо перегляд деталей, отримуємо історію транзакцій
    if ($_GET['action'] == 'view') {
        $productTransactions = $warehouseController->getProductTransactions($productId);
    }
}

// Обробка відправки форми
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Додавання нового товару
    if (isset($_POST['add_product'])) {
        $name = trim($_POST['name'] ?? '');
        $category = $_POST['category'] ?? '';
        $quantity = (int)($_POST['quantity'] ?? 0);
        $unit = trim($_POST['unit'] ?? '');
        $minStock = (int)($_POST['min_stock'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        
        if (empty($name) || empty($category) || empty($unit)) {
            $error = "Необхідно заповнити обов'язкові поля";
        } else {
            $result = $warehouseController->addProduct($name, $category, $quantity, $unit, $minStock, $description);
            if ($result['success']) {
                $message = $result['message'];
                // Перезавантажуємо список товарів
                $inventorySummary = $warehouseController->getInventorySummary();
            } else {
                $error = $result['message'];
            }
        }
    }
    
    // Оновлення існуючого товару
    if (isset($_POST['update_product'])) {
        $productId = $_POST['product_id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $category = $_POST['category'] ?? '';
        $unit = trim($_POST['unit'] ?? '');
        $minStock = (int)($_POST['min_stock'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        
        if (empty($productId) || empty($name) || empty($category) || empty($unit)) {
            $error = "Необхідно заповнити обов'язкові поля";
        } else {
            $result = $warehouseController->updateProduct($productId, $name, $category, $unit, $minStock, $description);
            if ($result['success']) {
                $message = $result['message'];
                // Перезавантажуємо список товарів
                $inventorySummary = $warehouseController->getInventorySummary();
                // Оновлюємо дані товару
                $editProduct = $warehouseController->getProductDetails($productId);
            } else {
                $error = $result['message'];
            }
        }
    }
    
    // Додавання транзакції (прихід/розхід)
    if (isset($_POST['add_transaction'])) {
        $productId = $_POST['product_id'] ?? '';
        $quantity = (int)($_POST['quantity'] ?? 0);
        $transactionType = $_POST['transaction_type'] ?? '';
        $notes = trim($_POST['notes'] ?? '');
        
        if (empty($productId) || $quantity <= 0 || empty($transactionType)) {
            $error = "Необхідно коректно заповнити всі поля";
        } else {
            $result = $warehouseController->addTransaction(
                $productId, 
                $quantity, 
                $transactionType, 
                0, // referenceId (немає прив'язки до замовлення)
                'adjustment', // referenceType
                $notes, 
                $currentUser['id']
            );
            
            if ($result['success']) {
                $message = $result['message'];
                // Перезавантажуємо дані
                $inventorySummary = $warehouseController->getInventorySummary();
                $editProduct = $warehouseController->getProductDetails($productId);
                $productTransactions = $warehouseController->getProductTransactions($productId);
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Визначаємо назви категорій для відображення
$categoryNames = [
    'raw_material' => 'Сировина',
    'packaging' => 'Упаковка',
    'finished_product' => 'Готова продукція'
];
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управління інвентарем - Винне виробництво</title>
    <!-- Підключення Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Іконки -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Верхня навігаційна панель -->
    <nav class="bg-teal-800 text-white p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center">
                <i class="fas fa-wine-bottle text-2xl mr-3"></i>
                <h1 class="text-xl font-bold">Винне виробництво</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span><?php echo htmlspecialchars($currentUser['name']); ?> (<?php echo $currentUser['role'] === 'admin' ? 'Адміністратор' : 'Начальник складу'; ?>)</span>
                <a href="../../controllers/logout.php" class="bg-teal-700 hover:bg-teal-600 py-2 px-4 rounded text-sm">
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
                    <div class="bg-teal-100 p-3 rounded-full mr-4">
                        <i class="fas fa-user text-teal-600"></i>
                    </div>
                    <div>
                        <p class="font-semibold"><?php echo htmlspecialchars($currentUser['name']); ?></p>
                        <p class="text-sm text-gray-500"><?php echo $currentUser['role'] === 'admin' ? 'Адміністратор' : 'Начальник складу'; ?></p>
                    </div>
                </div>
                
                <ul class="space-y-2">
                    <li>
                        <a href="dashboard.php" class="flex items-center p-2 text-gray-700 hover:bg-teal-50 rounded font-medium">
                            <i class="fas fa-tachometer-alt w-5 mr-2"></i>
                            <span>Панель керування</span>
                        </a>
                    </li>
                    <li>
                        <a href="suppliers.php" class="flex items-center p-2 text-gray-700 hover:bg-teal-50 rounded font-medium">
                            <i class="fas fa-truck w-5 mr-2"></i>
                            <span>Постачальники</span>
                        </a>
                    </li>
                    <li>
                        <a href="orders.php" class="flex items-center p-2 text-gray-700 hover:bg-teal-50 rounded font-medium">
                            <i class="fas fa-shopping-cart w-5 mr-2"></i>
                            <span>Замовлення</span>
                        </a>
                    </li>
                    <li>
                        <a href="inventory.php" class="flex items-center p-2 bg-teal-100 text-teal-700 rounded font-medium">
                            <i class="fas fa-boxes w-5 mr-2"></i>
                            <span>Залишки на складі</span>
                        </a>
                    </li>
                    <li>
                        <a href="messages.php" class="flex items-center p-2 text-gray-700 hover:bg-teal-50 rounded font-medium">
                            <i class="fas fa-envelope w-5 mr-2"></i>
                            <span>Повідомлення</span>
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
            
            <!-- Блок з товарами з низьким запасом -->
            <div class="bg-white rounded-lg shadow-md p-4">
                <h3 class="font-semibold text-lg mb-3">Товари з низьким запасом</h3>
                
                <?php if (empty($lowStockItems)): ?>
                <p class="text-green-600 text-center py-2">Всі товари мають достатній запас</p>
                <?php else: ?>
                <ul class="space-y-2">
                    <?php foreach ($lowStockItems as $item): ?>
                    <li class="p-2 hover:bg-red-50 rounded">
                        <a href="?action=view&id=<?php echo $item['id']; ?>" class="text-red-600 hover:text-red-800 flex justify-between items-center">
                            <span><?php echo htmlspecialchars($item['name']); ?></span>
                            <span class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded-full">
                                <?php echo $item['quantity'] . ' ' . $item['unit']; ?>
                            </span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </aside>
        
        <!-- Основний контент -->
        <main class="w-full md:w-3/4">
            <?php if (isset($_GET['action']) && $_GET['action'] == 'view' && $editProduct): ?>
            <!-- Режим перегляду деталей товару -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold text-gray-800">
                        <a href="inventory.php" class="text-purple-600 hover:text-purple-800 mr-2">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                        Деталі товару
                    </h2>
                    <div>
                        <a href="?action=edit&id=<?php echo $editProduct['id']; ?>" class="bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded mr-2">
                            <i class="fas fa-edit mr-1"></i> Редагувати
                        </a>
                        <button id="showTransactionForm" class="bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded">
                            <i class="fas fa-plus mr-1"></i> Нова транзакція
                        </button>
                    </div>
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
                
                <!-- Форма для додавання транзакції -->
                <div id="transactionForm" class="hidden bg-gray-50 p-6 rounded-lg mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Додавання транзакції</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="product_id" value="<?php echo $editProduct['id']; ?>">
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="transaction_type" class="block text-sm font-medium text-gray-700">Тип транзакції</label>
                                <select id="transaction_type" name="transaction_type" required 
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                                    <option value="in">Прихід</option>
                                    <option value="out">Розхід</option>
                                </select>
                            </div>
                            <div>
                                <label for="quantity" class="block text-sm font-medium text-gray-700">Кількість</label>
                                <input type="number" id="quantity" name="quantity" required min="1" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                            </div>
                            <div class="md:col-span-3">
                                <label for="notes" class="block text-sm font-medium text-gray-700">Примітка</label>
                                <textarea id="notes" name="notes" rows="2" 
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm"></textarea>
                            </div>
                        </div>
                        
                        <div class="mt-6 flex justify-end space-x-3">
                            <button type="button" id="cancelTransactionForm" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Скасувати
                            </button>
                            <button type="submit" name="add_transaction" class="bg-purple-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-purple-700">
                                Додати транзакцію
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Інформація про товар -->
                <div class="bg-gray-50 p-6 rounded-lg mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Основна інформація</h3>
                            <dl class="space-y-3">
                                <div class="grid grid-cols-3 gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Назва:</dt>
                                    <dd class="text-sm text-gray-900 col-span-2"><?php echo htmlspecialchars($editProduct['name']); ?></dd>
                                </div>
                                <div class="grid grid-cols-3 gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Категорія:</dt>
                                    <dd class="text-sm text-gray-900 col-span-2"><?php echo $categoryNames[$editProduct['category']] ?? $editProduct['category']; ?></dd>
                                </div>
                                <div class="grid grid-cols-3 gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Одиниця виміру:</dt>
                                    <dd class="text-sm text-gray-900 col-span-2"><?php echo htmlspecialchars($editProduct['unit']); ?></dd>
                                </div>
                                <div class="grid grid-cols-3 gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Опис:</dt>
                                    <dd class="text-sm text-gray-900 col-span-2">
                                        <?php echo htmlspecialchars($editProduct['description'] ?: 'Опис відсутній'); ?>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Запаси</h3>
                            <dl class="space-y-3">
                                <div class="grid grid-cols-3 gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Поточний запас:</dt>
                                    <dd class="text-sm text-gray-900 col-span-2">
                                        <span class="font-semibold <?php echo $editProduct['quantity'] <= $editProduct['min_stock'] ? 'text-red-600' : 'text-green-600'; ?>">
                                            <?php echo $editProduct['quantity'] . ' ' . $editProduct['unit']; ?>
                                        </span>
                                    </dd>
                                </div>
                                <div class="grid grid-cols-3 gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Мінімальний запас:</dt>
                                    <dd class="text-sm text-gray-900 col-span-2"><?php echo $editProduct['min_stock'] . ' ' . $editProduct['unit']; ?></dd>
                                </div>
                                <div class="grid grid-cols-3 gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Статус запасу:</dt>
                                    <dd class="text-sm col-span-2">
                                        <?php if ($editProduct['quantity'] <= $editProduct['min_stock']): ?>
                                        <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">
                                            Низький запас
                                        </span>
                                        <?php else: ?>
                                        <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                            Достатній запас
                                        </span>
                                        <?php endif; ?>
                                    </dd>
                                </div>
                                <div class="grid grid-cols-3 gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Дата створення:</dt>
                                    <dd class="text-sm text-gray-900 col-span-2"><?php echo date('d.m.Y H:i', strtotime($editProduct['created_at'])); ?></dd>
                                </div>
                                <div class="grid grid-cols-3 gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Останнє оновлення:</dt>
                                    <dd class="text-sm text-gray-900 col-span-2"><?php echo date('d.m.Y H:i', strtotime($editProduct['updated_at'])); ?></dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
                
                <!-- Історія транзакцій -->
                <h3 class="text-lg font-medium text-gray-900 mb-4">Історія транзакцій</h3>
                <?php if (empty($productTransactions)): ?>
                <p class="text-gray-500 text-center py-6">Транзакції відсутні</p>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Дата
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Тип
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Кількість
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Користувач
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Примітка
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($productTransactions as $transaction): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('d.m.Y H:i', strtotime($transaction['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($transaction['transaction_type'] == 'in'): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        Прихід
                                    </span>
                                    <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                        Розхід
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo $transaction['quantity'] . ' ' . $editProduct['unit']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($transaction['user_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($transaction['notes'] ?: '-'); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
            <?php elseif (isset($_GET['action']) && $_GET['action'] == 'edit' && $editProduct): ?>
            <!-- Режим редагування товару -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold text-gray-800">
                        <a href="?action=view&id=<?php echo $editProduct['id']; ?>" class="text-purple-600 hover:text-purple-800 mr-2">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                        Редагування товару
                    </h2>
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
                
                <!-- Форма редагування товару -->
                <form method="POST" action="">
                    <input type="hidden" name="product_id" value="<?php echo $editProduct['id']; ?>">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Назва товару</label>
                            <input type="text" id="name" name="name" required
                                   value="<?php echo htmlspecialchars($editProduct['name']); ?>"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="category" class="block text-sm font-medium text-gray-700">Категорія</label>
                            <select id="category" name="category" required 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                                <option value="raw_material" <?php echo $editProduct['category'] == 'raw_material' ? 'selected' : ''; ?>>Сировина</option>
                                <option value="packaging" <?php echo $editProduct['category'] == 'packaging' ? 'selected' : ''; ?>>Упаковка</option>
                                <option value="finished_product" <?php echo $editProduct['category'] == 'finished_product' ? 'selected' : ''; ?>>Готова продукція</option>
                            </select>
                        </div>
                        <div>
                            <label for="unit" class="block text-sm font-medium text-gray-700">Одиниця виміру</label>
                            <input type="text" id="unit" name="unit" required
                                   value="<?php echo htmlspecialchars($editProduct['unit']); ?>"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="min_stock" class="block text-sm font-medium text-gray-700">Мінімальний запас</label>
                            <input type="number" id="min_stock" name="min_stock" required min="0"
                                   value="<?php echo $editProduct['min_stock']; ?>"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                        </div>
                        <div class="md:col-span-2">
                            <label for="description" class="block text-sm font-medium text-gray-700">Опис</label>
                            <textarea id="description" name="description" rows="3" 
                                     class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm"><?php echo htmlspecialchars($editProduct['description']); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end space-x-3">
                        <a href="?action=view&id=<?php echo $editProduct['id']; ?>" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Скасувати
                        </a>
                        <button type="submit" name="update_product" class="bg-purple-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-purple-700">
                            Зберегти зміни
                        </button>
                    </div>
                </form>
            </div>
            
            <?php else: ?>
            <!-- Режим перегляду списку товарів -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold text-gray-800">Управління інвентарем</h2>
                    <button id="showAddProductForm" class="bg-teal-600 hover:bg-teal-700 text-white py-2 px-4 rounded">
                        <i class="fas fa-plus mr-1"></i> Додати товар
                    </button>
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
                
                <!-- Форма додавання товару -->
                <div id="addProductForm" class="hidden bg-gray-50 p-6 rounded-lg mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Додавання нового товару</h3>
                    <form method="POST" action="">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">Назва товару</label>
                                <input type="text" id="name" name="name" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="category" class="block text-sm font-medium text-gray-700">Категорія</label>
                                <select id="category" name="category" required 
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                                    <option value="raw_material">Сировина</option>
                                    <option value="packaging">Упаковка</option>
                                    <option value="finished_product">Готова продукція</option>
                                </select>
                            </div>
                            <div>
                                <label for="quantity" class="block text-sm font-medium text-gray-700">Початкова кількість</label>
                                <input type="number" id="quantity" name="quantity" required min="0"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="unit" class="block text-sm font-medium text-gray-700">Одиниця виміру</label>
                                <input type="text" id="unit" name="unit" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="min_stock" class="block text-sm font-medium text-gray-700">Мінімальний запас</label>
                                <input type="number" id="min_stock" name="min_stock" required min="0"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="description" class="block text-sm font-medium text-gray-700">Опис</label>
                                <textarea id="description" name="description" rows="2" 
                                         class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm"></textarea>
                            </div>
                        </div>
                        
                        <div class="mt-6 flex justify-end space-x-3">
                            <button type="button" id="cancelAddProduct" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Скасувати
                            </button>
                            <button type="submit" name="add_product" class="bg-purple-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-purple-700">
                                Додати товар
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Фільтри і пошук -->
                <div class="mb-6 flex flex-wrap items-center justify-between space-y-3 md:space-y-0">
                    <div class="flex space-x-2">
                        <a href="inventory.php" class="<?php echo empty($categoryFilter) ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800'; ?> px-3 py-1 rounded-full text-xs">
                            Всі категорії
                        </a>
                        <a href="?category=raw_material" class="<?php echo $categoryFilter === 'raw_material' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800'; ?> px-3 py-1 rounded-full text-xs">
                            Сировина
                        </a>
                        <a href="?category=packaging" class="<?php echo $categoryFilter === 'packaging' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800'; ?> px-3 py-1 rounded-full text-xs">
                            Упаковка
                        </a>
                        <a href="?category=finished_product" class="<?php echo $categoryFilter === 'finished_product' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800'; ?> px-3 py-1 rounded-full text-xs">
                            Готова продукція
                        </a>
                    </div>
                    <form method="GET" action="" class="flex max-w-xs">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" 
                               placeholder="Пошук товарів..." 
                               class="block w-full rounded-l-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                        <button type="submit" class="inline-flex items-center px-4 rounded-r-md border border-l-0 border-gray-300 bg-gray-50 text-gray-500 sm:text-sm">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
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
                                        <?php echo $categoryNames[$item['category']] ?? $item['category']; ?>
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
                                            Низький запас
                                        </span>
                                        <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            В наявності
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="?action=view&id=<?php echo $item['id']; ?>" class="text-purple-600 hover:text-purple-900 mr-3">
                                            Деталі
                                        </a>
                                        <a href="?action=edit&id=<?php echo $item['id']; ?>" class="text-indigo-600 hover:text-indigo-900">
                                            Редагувати
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
    
    <footer class="bg-white p-4 mt-8 border-t border-gray-200">
        <div class="container mx-auto text-center text-gray-500 text-sm">
            &copy; <?php echo date('Y'); ?> Винне виробництво. Система автоматизації процесів.
        </div>
    </footer>
    
    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Форма додавання товару
            const showAddProductFormBtn = document.getElementById('showAddProductForm');
            const cancelAddProductBtn = document.getElementById('cancelAddProduct');
            const addProductForm = document.getElementById('addProductForm');
            
            if (showAddProductFormBtn && cancelAddProductBtn && addProductForm) {
                showAddProductFormBtn.addEventListener('click', function() {
                    addProductForm.classList.remove('hidden');
                    showAddProductFormBtn.classList.add('hidden');
                });
                
                cancelAddProductBtn.addEventListener('click', function() {
                    addProductForm.classList.add('hidden');
                    showAddProductFormBtn.classList.remove('hidden');
                });
            }
            
            // Форма додавання транзакції
            const showTransactionFormBtn = document.getElementById('showTransactionForm');
            const cancelTransactionFormBtn = document.getElementById('cancelTransactionForm');
            const transactionForm = document.getElementById('transactionForm');
            
            if (showTransactionFormBtn && cancelTransactionFormBtn && transactionForm) {
                showTransactionFormBtn.addEventListener('click', function() {
                    transactionForm.classList.remove('hidden');
                    showTransactionFormBtn.classList.add('hidden');
                });
                
                cancelTransactionFormBtn.addEventListener('click', function() {
                    transactionForm.classList.add('hidden');
                    showTransactionFormBtn.classList.remove('hidden');
                });
            }
        });
    </script>
</body>
</html>