<?php
// views/warehouse/inventory.php
// Страница для просмотра и управления инвентарем на складе

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
$inventorySummary = $warehouseController->getInventorySummary();
$lowStockItems = $warehouseController->getLowStockItems();

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

// Обработка действий добавления/редактирования товара
$message = '';
$error = '';
$editProduct = null;

// Если это запрос на просмотр деталей или редактирование, получаем данные продукта
if (isset($_GET['action']) && ($_GET['action'] == 'view' || $_GET['action'] == 'edit') && isset($_GET['id'])) {
    $productId = $_GET['id'];
    $editProduct = $warehouseController->getProductDetails($productId);
    
    // Если просмотр деталей, получаем историю транзакций
    if ($_GET['action'] == 'view') {
        $productTransactions = $warehouseController->getProductTransactions($productId);
    }
}

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Добавление нового товара
    if (isset($_POST['add_product'])) {
        $name = trim($_POST['name'] ?? '');
        $category = $_POST['category'] ?? '';
        $quantity = (int)($_POST['quantity'] ?? 0);
        $unit = trim($_POST['unit'] ?? '');
        $minStock = (int)($_POST['min_stock'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        
        if (empty($name) || empty($category) || empty($unit)) {
            $error = "Необходимо заполнить обязательные поля";
        } else {
            $result = $warehouseController->addProduct($name, $category, $quantity, $unit, $minStock, $description);
            if ($result['success']) {
                $message = $result['message'];
                // Перезагружаем список товаров
                $inventorySummary = $warehouseController->getInventorySummary();
            } else {
                $error = $result['message'];
            }
        }
    }
    
    // Обновление существующего товара
    if (isset($_POST['update_product'])) {
        $productId = $_POST['product_id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $category = $_POST['category'] ?? '';
        $unit = trim($_POST['unit'] ?? '');
        $minStock = (int)($_POST['min_stock'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        
        if (empty($productId) || empty($name) || empty($category) || empty($unit)) {
            $error = "Необходимо заполнить обязательные поля";
        } else {
            $result = $warehouseController->updateProduct($productId, $name, $category, $unit, $minStock, $description);
            if ($result['success']) {
                $message = $result['message'];
                // Перезагружаем список товаров
                $inventorySummary = $warehouseController->getInventorySummary();
                // Обновляем данные товара
                $editProduct = $warehouseController->getProductDetails($productId);
            } else {
                $error = $result['message'];
            }
        }
    }
    
    // Добавление транзакции (приход/расход)
    if (isset($_POST['add_transaction'])) {
        $productId = $_POST['product_id'] ?? '';
        $quantity = (int)($_POST['quantity'] ?? 0);
        $transactionType = $_POST['transaction_type'] ?? '';
        $notes = trim($_POST['notes'] ?? '');
        
        if (empty($productId) || $quantity <= 0 || empty($transactionType)) {
            $error = "Необходимо заполнить все поля корректно";
        } else {
            $result = $warehouseController->addTransaction(
                $productId, 
                $quantity, 
                $transactionType, 
                0, // referenceId (нет привязки к заказу)
                'adjustment', // referenceType
                $notes, 
                $currentUser['id']
            );
            
            if ($result['success']) {
                $message = $result['message'];
                // Перезагружаем данные
                $inventorySummary = $warehouseController->getInventorySummary();
                $editProduct = $warehouseController->getProductDetails($productId);
                $productTransactions = $warehouseController->getProductTransactions($productId);
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Определяем названия категорий для отображения
$categoryNames = [
    'raw_material' => 'Сырьё',
    'packaging' => 'Упаковка',
    'finished_product' => 'Готовая продукция'
];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление инвентарем - Винное производство</title>
    <!-- Подключение Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
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
                        <a href="inventory.php" class="flex items-center p-2 bg-purple-100 text-purple-700 rounded font-medium">
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
            
            <!-- Блок с товарами с низким запасом -->
            <div class="bg-white rounded-lg shadow-md p-4">
                <h3 class="font-semibold text-lg mb-3">Товары с низким запасом</h3>
                
                <?php if (empty($lowStockItems)): ?>
                <p class="text-green-600 text-center py-2">Все товары имеют достаточный запас</p>
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
        
        <!-- Основной контент -->
        <main class="w-full md:w-3/4">
            <?php if (isset($_GET['action']) && $_GET['action'] == 'view' && $editProduct): ?>
            <!-- Режим просмотра деталей товара -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold text-gray-800">
                        <a href="inventory.php" class="text-purple-600 hover:text-purple-800 mr-2">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                        Детали товара
                    </h2>
                    <div>
                        <a href="?action=edit&id=<?php echo $editProduct['id']; ?>" class="bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded mr-2">
                            <i class="fas fa-edit mr-1"></i> Редактировать
                        </a>
                        <button id="showTransactionForm" class="bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded">
                            <i class="fas fa-plus mr-1"></i> Новая транзакция
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
                
                <!-- Форма для добавления транзакции -->
                <div id="transactionForm" class="hidden bg-gray-50 p-6 rounded-lg mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Добавление транзакции</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="product_id" value="<?php echo $editProduct['id']; ?>">
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="transaction_type" class="block text-sm font-medium text-gray-700">Тип транзакции</label>
                                <select id="transaction_type" name="transaction_type" required 
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                                    <option value="in">Приход</option>
                                    <option value="out">Расход</option>
                                </select>
                            </div>
                            <div>
                                <label for="quantity" class="block text-sm font-medium text-gray-700">Количество</label>
                                <input type="number" id="quantity" name="quantity" required min="1" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                            </div>
                            <div class="md:col-span-3">
                                <label for="notes" class="block text-sm font-medium text-gray-700">Примечание</label>
                                <textarea id="notes" name="notes" rows="2" 
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm"></textarea>
                            </div>
                        </div>
                        
                        <div class="mt-6 flex justify-end space-x-3">
                            <button type="button" id="cancelTransactionForm" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Отмена
                            </button>
                            <button type="submit" name="add_transaction" class="bg-purple-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-purple-700">
                                Добавить транзакцию
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Информация о товаре -->
                <div class="bg-gray-50 p-6 rounded-lg mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Основная информация</h3>
                            <dl class="space-y-3">
                                <div class="grid grid-cols-3 gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Название:</dt>
                                    <dd class="text-sm text-gray-900 col-span-2"><?php echo htmlspecialchars($editProduct['name']); ?></dd>
                                </div>
                                <div class="grid grid-cols-3 gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Категория:</dt>
                                    <dd class="text-sm text-gray-900 col-span-2"><?php echo $categoryNames[$editProduct['category']] ?? $editProduct['category']; ?></dd>
                                </div>
                                <div class="grid grid-cols-3 gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Единица измерения:</dt>
                                    <dd class="text-sm text-gray-900 col-span-2"><?php echo htmlspecialchars($editProduct['unit']); ?></dd>
                                </div>
                                <div class="grid grid-cols-3 gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Описание:</dt>
                                    <dd class="text-sm text-gray-900 col-span-2">
                                        <?php echo htmlspecialchars($editProduct['description'] ?: 'Нет описания'); ?>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Запасы</h3>
                            <dl class="space-y-3">
                                <div class="grid grid-cols-3 gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Текущий запас:</dt>
                                    <dd class="text-sm text-gray-900 col-span-2">
                                        <span class="font-semibold <?php echo $editProduct['quantity'] <= $editProduct['min_stock'] ? 'text-red-600' : 'text-green-600'; ?>">
                                            <?php echo $editProduct['quantity'] . ' ' . $editProduct['unit']; ?>
                                        </span>
                                    </dd>
                                </div>
                                <div class="grid grid-cols-3 gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Минимальный запас:</dt>
                                    <dd class="text-sm text-gray-900 col-span-2"><?php echo $editProduct['min_stock'] . ' ' . $editProduct['unit']; ?></dd>
                                </div>
                                <div class="grid grid-cols-3 gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Статус запаса:</dt>
                                    <dd class="text-sm col-span-2">
                                        <?php if ($editProduct['quantity'] <= $editProduct['min_stock']): ?>
                                        <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">
                                            Низкий запас
                                        </span>
                                        <?php else: ?>
                                        <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                            Достаточный запас
                                        </span>
                                        <?php endif; ?>
                                    </dd>
                                </div>
                                <div class="grid grid-cols-3 gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Дата создания:</dt>
                                    <dd class="text-sm text-gray-900 col-span-2"><?php echo date('d.m.Y H:i', strtotime($editProduct['created_at'])); ?></dd>
                                </div>
                                <div class="grid grid-cols-3 gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Последнее обновление:</dt>
                                    <dd class="text-sm text-gray-900 col-span-2"><?php echo date('d.m.Y H:i', strtotime($editProduct['updated_at'])); ?></dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
                
                <!-- История транзакций -->
                <h3 class="text-lg font-medium text-gray-900 mb-4">История транзакций</h3>
                <?php if (empty($productTransactions)): ?>
                <p class="text-gray-500 text-center py-6">Транзакции отсутствуют</p>
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
                                    Количество
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Пользователь
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Примечание
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
                                        Приход
                                    </span>
                                    <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                        Расход
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
            <!-- Режим редактирования товара -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold text-gray-800">
                        <a href="?action=view&id=<?php echo $editProduct['id']; ?>" class="text-purple-600 hover:text-purple-800 mr-2">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                        Редактирование товара
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
                
                <!-- Форма редактирования товара -->
                <form method="POST" action="">
                    <input type="hidden" name="product_id" value="<?php echo $editProduct['id']; ?>">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Название товара</label>
                            <input type="text" id="name" name="name" required
                                   value="<?php echo htmlspecialchars($editProduct['name']); ?>"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="category" class="block text-sm font-medium text-gray-700">Категория</label>
                            <select id="category" name="category" required 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                                <option value="raw_material" <?php echo $editProduct['category'] == 'raw_material' ? 'selected' : ''; ?>>Сырьё</option>
                                <option value="packaging" <?php echo $editProduct['category'] == 'packaging' ? 'selected' : ''; ?>>Упаковка</option>
                                <option value="finished_product" <?php echo $editProduct['category'] == 'finished_product' ? 'selected' : ''; ?>>Готовая продукция</option>
                            </select>
                        </div>
                        <div>
                            <label for="unit" class="block text-sm font-medium text-gray-700">Единица измерения</label>
                            <input type="text" id="unit" name="unit" required
                                   value="<?php echo htmlspecialchars($editProduct['unit']); ?>"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="min_stock" class="block text-sm font-medium text-gray-700">Минимальный запас</label>
                            <input type="number" id="min_stock" name="min_stock" required min="0"
                                   value="<?php echo $editProduct['min_stock']; ?>"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                        </div>
                        <div class="md:col-span-2">
                            <label for="description" class="block text-sm font-medium text-gray-700">Описание</label>
                            <textarea id="description" name="description" rows="3" 
                                     class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm"><?php echo htmlspecialchars($editProduct['description']); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end space-x-3">
                        <a href="?action=view&id=<?php echo $editProduct['id']; ?>" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Отмена
                        </a>
                        <button type="submit" name="update_product" class="bg-purple-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-purple-700">
                            Сохранить изменения
                        </button>
                    </div>
                </form>
            </div>
            
            <?php else: ?>
            <!-- Режим просмотра списка товаров -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold text-gray-800">Управление инвентарем</h2>
                    <button id="showAddProductForm" class="bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded">
                        <i class="fas fa-plus mr-1"></i> Добавить товар
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
                
                <!-- Форма добавления товара -->
                <div id="addProductForm" class="hidden bg-gray-50 p-6 rounded-lg mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Добавление нового товара</h3>
                    <form method="POST" action="">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">Название товара</label>
                                <input type="text" id="name" name="name" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="category" class="block text-sm font-medium text-gray-700">Категория</label>
                                <select id="category" name="category" required 
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                                    <option value="raw_material">Сырьё</option>
                                    <option value="packaging">Упаковка</option>
                                    <option value="finished_product">Готовая продукция</option>
                                </select>
                            </div>
                            <div>
                                <label for="quantity" class="block text-sm font-medium text-gray-700">Начальное количество</label>
                                <input type="number" id="quantity" name="quantity" required min="0"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="unit" class="block text-sm font-medium text-gray-700">Единица измерения</label>
                                <input type="text" id="unit" name="unit" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="min_stock" class="block text-sm font-medium text-gray-700">Минимальный запас</label>
                                <input type="number" id="min_stock" name="min_stock" required min="0"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="description" class="block text-sm font-medium text-gray-700">Описание</label>
                                <textarea id="description" name="description" rows="2" 
                                         class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm"></textarea>
                            </div>
                        </div>
                        
                        <div class="mt-6 flex justify-end space-x-3">
                            <button type="button" id="cancelAddProduct" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Отмена
                            </button>
                            <button type="submit" name="add_product" class="bg-purple-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-purple-700">
                                Добавить товар
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Фильтры и поиск -->
                <div class="mb-6 flex flex-wrap items-center justify-between space-y-3 md:space-y-0">
                    <div class="flex space-x-2">
                        <a href="inventory.php" class="<?php echo empty($categoryFilter) ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800'; ?> px-3 py-1 rounded-full text-xs">
                            Все категории
                        </a>
                        <a href="?category=raw_material" class="<?php echo $categoryFilter === 'raw_material' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800'; ?> px-3 py-1 rounded-full text-xs">
                            Сырьё
                        </a>
                        <a href="?category=packaging" class="<?php echo $categoryFilter === 'packaging' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800'; ?> px-3 py-1 rounded-full text-xs">
                            Упаковка
                        </a>
                        <a href="?category=finished_product" class="<?php echo $categoryFilter === 'finished_product' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800'; ?> px-3 py-1 rounded-full text-xs">
                            Готовая продукция
                        </a>
                    </div>
                    <form method="GET" action="" class="flex max-w-xs">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" 
                               placeholder="Поиск товаров..." 
                               class="block w-full rounded-l-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                        <button type="submit" class="inline-flex items-center px-4 rounded-r-md border border-l-0 border-gray-300 bg-gray-50 text-gray-500 sm:text-sm">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
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
                                            Низкий запас
                                        </span>
                                        <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            В наличии
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="?action=view&id=<?php echo $item['id']; ?>" class="text-purple-600 hover:text-purple-900 mr-3">
                                            Детали
                                        </a>
                                        <a href="?action=edit&id=<?php echo $item['id']; ?>" class="text-indigo-600 hover:text-indigo-900">
                                            Редактировать
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
            &copy; <?php echo date('Y'); ?> Винное производство. Система автоматизации процессов.
        </div>
    </footer>
    
    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Форма добавления товара
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
            
            // Форма добавления транзакции
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