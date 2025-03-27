<?php
// views/warehouse/receive.php
// Сторінка для прийому товарів на склад

// Підключення контролерів
require_once '../../controllers/AuthController.php';
require_once '../../controllers/WarehouseController.php';
require_once '../../controllers/PurchasingController.php';

$authController = new AuthController();
$warehouseController = new WarehouseController();
$purchasingController = new PurchasingController(); // Для отримання інформації про замовлення

// Перевірка авторизації та ролі
if (!$authController->isLoggedIn() || !$authController->checkRole(['warehouse', 'admin'])) {
    header('Location: ../../index.php');
    exit;
}

// Отримання даних для сторінки
$currentUser = $authController->getCurrentUser();
$categories = [
    'raw_material' => 'Сировина',
    'packaging' => 'Упаковка',
    'finished_product' => 'Готова продукція'
];

// Отримання списку продуктів для вибору
$products = $warehouseController->getInventorySummary();

// Отримання списку замовлень, готових до прийому (підтверджені, але ще не отримані)
$pendingOrders = $purchasingController->getOrdersByStatus('approved');

// Обробка форми прийому товару
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Прийом замовлення
    if (isset($_POST['receive_order'])) {
        $orderId = $_POST['order_id'] ?? '';
        
        if (empty($orderId)) {
            $error = "Ідентифікатор замовлення не вказано";
        } else {
            $result = $warehouseController->receiveOrderItems($orderId, $currentUser['id']);
            if ($result['success']) {
                $message = $result['message'];
                // Оновлюємо список замовлень
                $pendingOrders = $purchasingController->getOrdersByStatus('approved');
                // Оновлюємо список товарів
                $products = $warehouseController->getInventorySummary();
            } else {
                $error = $result['message'];
            }
        }
    }
    
    // Прийом товарів без замовлення
    if (isset($_POST['receive_items'])) {
        $success = true;
        $transactionResults = [];
        
        // Обробка кожного товару з форми
        foreach ($_POST['product_id'] as $index => $productId) {
            if (!empty($productId) && isset($_POST['quantity'][$index]) && $_POST['quantity'][$index] > 0) {
                $quantity = (int)$_POST['quantity'][$index];
                $notes = $_POST['notes'][$index] ?? '';
                $source = $_POST['source'][$index] ?? '';
                
                // Формуємо примітку з вказанням джерела
                $fullNotes = "Джерело: " . $source . ". " . $notes;
                
                // Створюємо транзакцію (надходження товару)
                $result = $warehouseController->addTransaction(
                    $productId,
                    $quantity,
                    'in', // тип - надходження
                    0, // немає посилання на замовлення
                    'adjustment', // тип посилання
                    $fullNotes,
                    $currentUser['id']
                );
                
                if ($result['success']) {
                    $productDetails = $warehouseController->getProductDetails($productId);
                    $transactionResults[] = [
                        'success' => true,
                        'product_name' => $productDetails['name'],
                        'quantity' => $quantity,
                        'unit' => $productDetails['unit'],
                        'message' => $result['message']
                    ];
                } else {
                    $success = false;
                    $productDetails = $warehouseController->getProductDetails($productId);
                    $transactionResults[] = [
                        'success' => false,
                        'product_name' => $productDetails ? $productDetails['name'] : 'Невідомий товар',
                        'quantity' => $quantity,
                        'unit' => $productDetails ? $productDetails['unit'] : '',
                        'message' => $result['message']
                    ];
                }
            }
        }
        
        if ($success) {
            $message = "Товари успішно прийнято на склад";
        } else {
            $error = "Виникли помилки при прийомі деяких товарів";
        }
        
        // Оновлюємо список товарів
        $products = $warehouseController->getInventorySummary();
    }
}

// Отримання деталей замовлення, якщо вказано ID
$orderDetails = null;
if (isset($_GET['order_id']) && !empty($_GET['order_id'])) {
    $orderId = $_GET['order_id'];
    $orderDetails = $purchasingController->getOrderWithItems($orderId);
}

// Джерела постачань для випадаючого списку
$sources = [
    'production' => 'Власне виробництво',
    'return' => 'Повернення від клієнта',
    'supplier' => 'Постачальник (без замовлення)',
    'adjustment' => 'Коригування інвентаря',
    'other' => 'Інше'
];
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Прийом товарів - Винне виробництво</title>
    <!-- Підключення Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
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
                        <a href="receive.php" class="flex items-center p-2 bg-purple-100 text-purple-700 rounded font-medium">
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
            
            <!-- Блок з інструкціями -->
            <div class="bg-white rounded-lg shadow-md p-4">
                <h3 class="font-semibold text-lg mb-3">Інструкції</h3>
                <div class="text-sm text-gray-600 space-y-2">
                    <p><strong>Прийом за замовленням:</strong></p>
                    <p>1. Виберіть замовлення зі списку.</p>
                    <p>2. Перевірте вміст замовлення.</p>
                    <p>3. Натисніть "Прийняти замовлення".</p>
                    
                    <p class="mt-4"><strong>Прийом без замовлення:</strong></p>
                    <p>1. Заповніть форму прийому, вказавши товар, кількість і джерело.</p>
                    <p>2. За необхідності додайте примітки.</p>
                    <p>3. Натисніть "Прийняти товари" для завершення процесу.</p>
                    
                    <div class="mt-4 p-2 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800">
                        <p class="font-medium">Примітка:</p>
                        <p>Пріоритетним способом є прийом за замовленням для збереження чіткого обліку.</p>
                    </div>
                </div>
            </div>
        </aside>
        
        <!-- Основний контент -->
        <main class="w-full md:w-3/4">
            <!-- Повідомлення про успіх або помилку -->
            <?php if ($message): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p class="font-medium"><?php echo $message; ?></p>
                
                <?php if (!empty($transactionResults)): ?>
                <ul class="mt-2 list-disc list-inside">
                    <?php foreach ($transactionResults as $result): ?>
                        <?php if ($result['success']): ?>
                        <li>
                            <?php echo htmlspecialchars($result['product_name']); ?> - 
                            <?php echo $result['quantity'] . ' ' . $result['unit']; ?>
                        </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p class="font-medium"><?php echo $error; ?></p>
                
                <?php if (!empty($transactionResults)): ?>
                <ul class="mt-2 list-disc list-inside">
                    <?php foreach ($transactionResults as $result): ?>
                        <?php if (!$result['success']): ?>
                        <li>
                            <?php echo htmlspecialchars($result['product_name']); ?> - 
                            <?php echo $result['message']; ?>
                        </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($orderDetails)): ?>
            <!-- Режим перегляду і прийому замовлення -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold text-gray-800">
                        <a href="receive.php" class="text-purple-600 hover:text-purple-800 mr-2">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                        Прийом замовлення #<?php echo $orderDetails['order']['id']; ?>
                    </h2>
                    <div>
                        <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                            Підтверджено
                        </span>
                    </div>
                </div>
                
                <!-- Інформація про замовлення -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-3">Інформація про замовлення</h3>
                        <dl class="space-y-2">
                            <div class="grid grid-cols-3 gap-4">
                                <dt class="text-sm font-medium text-gray-500">ID замовлення:</dt>
                                <dd class="text-sm text-gray-900 col-span-2"><?php echo $orderDetails['order']['id']; ?></dd>
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <dt class="text-sm font-medium text-gray-500">Постачальник:</dt>
                                <dd class="text-sm text-gray-900 col-span-2"><?php echo htmlspecialchars($orderDetails['order']['company_name']); ?></dd>
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <dt class="text-sm font-medium text-gray-500">Дата створення:</dt>
                                <dd class="text-sm text-gray-900 col-span-2"><?php echo date('d.m.Y H:i', strtotime($orderDetails['order']['created_at'])); ?></dd>
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <dt class="text-sm font-medium text-gray-500">Менеджер:</dt>
                                <dd class="text-sm text-gray-900 col-span-2"><?php echo htmlspecialchars($orderDetails['order']['created_by_name']); ?></dd>
                            </div>
                        </dl>
                    </div>
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-3">Сума замовлення</h3>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="text-sm text-gray-500 mb-1">Загальна сума замовлення:</div>
                            <div class="text-3xl font-bold text-gray-900 mb-4">
                                <?php echo number_format($orderDetails['order']['total_amount'], 2, ',', ' '); ?> ₴
                            </div>
                            
                            <form method="POST" action="">
                                <input type="hidden" name="order_id" value="<?php echo $orderDetails['order']['id']; ?>">
                                <button type="submit" name="receive_order" class="w-full bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded">
                                    <i class="fas fa-truck-loading mr-1"></i> Прийняти все замовлення
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Елементи замовлення -->
                <h3 class="text-lg font-medium text-gray-900 mb-3">Товари в замовленні</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Товар
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Категорія
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Кількість
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Ціна за од.
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Сума
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($orderDetails['items'] as $item): ?>
                            <?php $itemTotal = $item['quantity'] * $item['price']; ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($item['product_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $categories[$item['category']] ?? $item['category']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $item['quantity'] . ' ' . $item['unit']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo number_format($item['price'], 2, ',', ' ') . ' ₴'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo number_format($itemTotal, 2, ',', ' ') . ' ₴'; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            
            <!-- Замовлення, готові до прийому -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">Замовлення, готові до прийому</h2>
                
                <?php if (empty($pendingOrders)): ?>
                <div class="text-center py-6">
                    <p class="text-gray-500">Немає замовлень, що очікують прийому</p>
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
                                    Постачальник
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Дата підтвердження
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Сума
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Дії
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($pendingOrders as $order): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo $order['id']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($order['company_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('d.m.Y', strtotime($order['updated_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo number_format($order['total_amount'], 2, ',', ' '); ?> ₴
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="?order_id=<?php echo $order['id']; ?>" class="text-purple-600 hover:text-purple-900 mr-3">
                                        Перегляд
                                    </a>
                                    <form method="POST" action="" class="inline">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <button type="submit" name="receive_order" class="text-green-600 hover:text-green-900">
                                            Прийняти
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Форма прийому товарів без замовлення -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold text-gray-800">Прийом товарів без замовлення</h2>
                </div>
                
                <form method="POST" action="" id="receiveForm">
                    <div class="mb-6 p-6 bg-gray-50 rounded-lg">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Форма прийому товарів</h3>
                        
                        <div id="receiveItems">
                            <div class="mb-2 pb-2 border-b border-gray-200">
                                <div class="grid grid-cols-12 gap-2 mb-2 items-center">
                                    <div class="col-span-4">
                                        <label class="block text-xs font-medium text-gray-700">Товар</label>
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-xs font-medium text-gray-700">Поточний запас</label>
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-xs font-medium text-gray-700">Кількість</label>
                                    </div>
                                    <div class="col-span-3">
                                        <label class="block text-xs font-medium text-gray-700">Джерело</label>
                                    </div>
                                    <div class="col-span-1">
                                        <label class="block text-xs text-transparent">Дія</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="receiveItemRow space-y-4">
                                <div class="grid grid-cols-12 gap-2 items-center">
                                    <div class="col-span-4">
                                        <select name="product_id[]" required class="product-select block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                                            <option value="">Виберіть товар</option>
                                            <?php foreach ($products as $product): ?>
                                            <option value="<?php echo $product['id']; ?>" 
                                                   data-stock="<?php echo $product['quantity']; ?>"
                                                   data-unit="<?php echo htmlspecialchars($product['unit']); ?>">
                                                <?php echo htmlspecialchars($product['name']); ?> 
                                                (<?php echo $categories[$product['category']] ?? $product['category']; ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-span-2">
                                        <span class="current-stock text-sm text-gray-500">-</span>
                                    </div>
                                    <div class="col-span-2">
                                        <div class="flex items-center">
                                            <input type="number" name="quantity[]" required min="1" placeholder="К-ть"
                                                  class="block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                                            <span class="unit ml-1 text-sm text-gray-500"></span>
                                        </div>
                                    </div>
                                    <div class="col-span-3">
                                        <select name="source[]" required
                                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                                            <option value="">Виберіть джерело</option>
                                            <?php foreach ($sources as $code => $name): ?>
                                            <option value="<?php echo $code; ?>"><?php echo $name; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-span-1">
                                        <button type="button" class="text-red-600 hover:text-red-800 disabled:text-gray-400"
                                                onclick="removeItemRow(this)" disabled>
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="grid grid-cols-12 gap-2">
                                    <div class="col-span-11">
                                        <input type="text" name="notes[]" placeholder="Примітка (необов'язково)"
                                              class="block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                                    </div>
                                    <div class="col-span-1"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="button" id="addItemButton" class="text-purple-600 hover:text-purple-800">
                                <i class="fas fa-plus mr-1"></i> Додати ще товар
                            </button>
                        </div>
                        
                        <div class="mt-6 flex justify-end">
                            <button type="submit" name="receive_items" class="bg-purple-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-purple-700">
                                <i class="fas fa-truck-loading mr-1"></i> Прийняти товари
                            </button>
                        </div>
                    </div>
                </form>
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
            // Ініціалізація обробників подій для першого рядка
            initializeProductSelect(document.querySelector('.product-select'));
            
            // Кнопка додавання товару
            document.getElementById('addItemButton').addEventListener('click', addItemRow);
        });
        
        // Лічильник для унікальних ідентифікаторів рядків
        let rowCounter = 1;
        
        // Додавання нового рядка товару
        function addItemRow() {
            const itemsContainer = document.getElementById('receiveItems');
            const template = document.querySelector('.receiveItemRow').cloneNode(true);
            
            // Очищаємо значення полів
            const productSelect = template.querySelector('.product-select');
            productSelect.selectedIndex = 0;
            
            template.querySelector('input[name="quantity[]"]').value = '';
            template.querySelector('select[name="source[]"]').selectedIndex = 0;
            template.querySelector('input[name="notes[]"]').value = '';
            
            // Оновлюємо відображення поточного запасу та одиниць виміру
            template.querySelector('.current-stock').textContent = '-';
            template.querySelector('.unit').textContent = '';
            
            // Вмикаємо кнопку видалення
            template.querySelector('button[onclick="removeItemRow(this)"]').disabled = false;
            
            // Ініціалізуємо обробники для селекта товару
            initializeProductSelect(productSelect);
            
            // Додаємо в контейнер
            itemsContainer.appendChild(template);
        }
        
        // Видалення рядка товару
        function removeItemRow(button) {
            const row = button.closest('.receiveItemRow');
            row.remove();
        }
        
        // Ініціалізація селекта товару
        function initializeProductSelect(select) {
            select.addEventListener('change', function() {
                const row = this.closest('.receiveItemRow');
                const option = this.options[this.selectedIndex];
                
                if (option.value) {
                    const stock = option.getAttribute('data-stock');
                    const unit = option.getAttribute('data-unit');
                    
                    row.querySelector('.current-stock').textContent = stock + ' ' + unit;
                    row.querySelector('.unit').textContent = unit;
                } else {
                    row.querySelector('.current-stock').textContent = '-';
                    row.querySelector('.unit').textContent = '';
                }
            });
        }
    </script>
</body>
</html>