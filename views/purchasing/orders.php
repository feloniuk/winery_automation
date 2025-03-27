<?php
// views/purchasing/orders.php
// Сторінка перегляду та управління замовленнями для менеджера із закупівель

// Підключення контролерів
require_once '../../controllers/AuthController.php';
require_once '../../controllers/PurchasingController.php';

$authController = new AuthController();
$purchasingController = new PurchasingController();

// Перевірка авторизації та ролі
if (!$authController->isLoggedIn() || !$authController->checkRole(['purchasing', 'admin'])) {
    header('Location: ../../index.php');
    exit;
}

// Отримання даних для сторінки
$currentUser = $authController->getCurrentUser();
$suppliers = $purchasingController->getActiveSuppliers();

// Фільтрація за статусом
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$ordersList = [];

if ($statusFilter) {
    $ordersList = $purchasingController->getOrdersByStatus($statusFilter);
} else {
    // Якщо статус не вказано, показуємо всі активні замовлення (pending і approved)
    $pendingOrders = $purchasingController->getOrdersByStatus('pending');
    $approvedOrders = $purchasingController->getOrdersByStatus('approved');
    $ordersList = array_merge($pendingOrders, $approvedOrders);
    
    // Сортування за датою (спочатку нові)
    usort($ordersList, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
}

// Обробка дій
$message = '';
$error = '';

// Обробка відправки форми
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Схвалення замовлення
    if (isset($_POST['approve_order'])) {
        $orderId = $_POST['order_id'] ?? '';
        
        if (empty($orderId)) {
            $error = "Ідентифікатор замовлення не вказано";
        } else {
            $result = $purchasingController->updateOrderStatus($orderId, 'approved');
            if ($result['success']) {
                $message = $result['message'];
                // Перезавантажуємо список замовлень
                if ($statusFilter) {
                    $ordersList = $purchasingController->getOrdersByStatus($statusFilter);
                } else {
                    // Якщо статус не вказано, показуємо всі активні замовлення
                    $pendingOrders = $purchasingController->getOrdersByStatus('pending');
                    $approvedOrders = $purchasingController->getOrdersByStatus('approved');
                    $ordersList = array_merge($pendingOrders, $approvedOrders);
                    
                    // Сортування за датою (спочатку нові)
                    usort($ordersList, function($a, $b) {
                        return strtotime($b['created_at']) - strtotime($a['created_at']);
                    });
                }
            } else {
                $error = $result['message'];
            }
        }
    }
    
    // Відхилення замовлення
    if (isset($_POST['reject_order'])) {
        $orderId = $_POST['order_id'] ?? '';
        
        if (empty($orderId)) {
            $error = "Ідентифікатор замовлення не вказано";
        } else {
            $result = $purchasingController->updateOrderStatus($orderId, 'rejected');
            if ($result['success']) {
                $message = $result['message'];
                // Перезавантажуємо список замовлень
                if ($statusFilter) {
                    $ordersList = $purchasingController->getOrdersByStatus($statusFilter);
                } else {
                    // Якщо статус не вказано, показуємо всі активні замовлення
                    $pendingOrders = $purchasingController->getOrdersByStatus('pending');
                    $approvedOrders = $purchasingController->getOrdersByStatus('approved');
                    $ordersList = array_merge($pendingOrders, $approvedOrders);
                    
                    // Сортування за датою (спочатку нові)
                    usort($ordersList, function($a, $b) {
                        return strtotime($b['created_at']) - strtotime($a['created_at']);
                    });
                }
            } else {
                $error = $result['message'];
            }
        }
    }
    
    // Створення нового замовлення
    if (isset($_POST['create_order'])) {
        $supplierId = $_POST['supplier_id'] ?? '';
        $items = [];
        
        // Збираємо інформацію про товари
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'product_id_') === 0 && !empty($value)) {
                $index = substr($key, strlen('product_id_'));
                $quantity = (int)($_POST['quantity_' . $index] ?? 0);
                $price = (float)($_POST['price_' . $index] ?? 0);
                
                if ($quantity > 0 && $price > 0) {
                    $items[] = [
                        'product_id' => $value,
                        'quantity' => $quantity,
                        'price' => $price
                    ];
                }
            }
        }
        
        if (empty($supplierId)) {
            $error = "Необхідно вибрати постачальника";
        } elseif (empty($items)) {
            $error = "Необхідно додати хоча б один товар до замовлення";
        } else {
            $result = $purchasingController->createOrder($supplierId, $currentUser['id'], $items);
            if ($result['success']) {
                $message = $result['message'];
                // Перезавантажуємо список замовлень
                if ($statusFilter) {
                    $ordersList = $purchasingController->getOrdersByStatus($statusFilter);
                } else {
                    // Якщо статус не вказано, показуємо всі активні замовлення
                    $pendingOrders = $purchasingController->getOrdersByStatus('pending');
                    $approvedOrders = $purchasingController->getOrdersByStatus('approved');
                    $ordersList = array_merge($pendingOrders, $approvedOrders);
                    
                    // Сортування за датою (спочатку нові)
                    usort($ordersList, function($a, $b) {
                        return strtotime($b['created_at']) - strtotime($a['created_at']);
                    });
                }
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Отримання деталей замовлення, якщо вказано ID
$orderDetails = null;
if (isset($_GET['view']) && !empty($_GET['view'])) {
    $orderId = $_GET['view'];
    $orderDetails = $purchasingController->getOrderWithItems($orderId);
}

// Визначаємо переклади статусів для відображення
$statusTranslations = [
    'pending' => 'Очікує підтвердження',
    'approved' => 'Підтверджено',
    'rejected' => 'Відхилено',
    'received' => 'Отримано'
];

// Визначаємо класи стилів для статусів
$statusClasses = [
    'pending' => 'bg-yellow-100 text-yellow-800',
    'approved' => 'bg-green-100 text-green-800',
    'rejected' => 'bg-red-100 text-red-800',
    'received' => 'bg-blue-100 text-blue-800'
];
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управління замовленнями - Винне виробництво</title>
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
                <span><?php echo htmlspecialchars($currentUser['name']); ?> (<?php echo $currentUser['role'] === 'admin' ? 'Адміністратор' : 'Менеджер із закупівель'; ?>)</span>
                <a href="../../controllers/logout.php" class="bg-teal-700 hover:bg-teal-600 py-2 px-4 rounded text-sm">
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
                    <div class="bg-teal-100 p-3 rounded-full mr-4">
                        <i class="fas fa-user text-teal-600"></i>
                    </div>
                    <div>
                        <p class="font-semibold"><?php echo htmlspecialchars($currentUser['name']); ?></p>
                        <p class="text-sm text-gray-500"><?php echo $currentUser['role'] === 'admin' ? 'Адміністратор' : 'Менеджер із закупівель'; ?></p>
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
                        <a href="orders.php" class="flex items-center p-2 bg-teal-100 text-teal-700 rounded font-medium">
                            <i class="fas fa-shopping-cart w-5 mr-2"></i>
                            <span>Замовлення</span>
                        </a>
                    </li>
                    <li>
                        <a href="inventory.php" class="flex items-center p-2 text-gray-700 hover:bg-teal-50 rounded font-medium">
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
                    <?php if ($currentUser['role'] === 'admin'): ?>
                    <li>
                        <a href="../admin/dashboard.php" class="flex items-center p-2 text-gray-700 hover:bg-teal-50 rounded font-medium">
                            <i class="fas fa-user-shield w-5 mr-2"></i>
                            <span>Панель адміністратора</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <!-- Фільтр замовлень -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <h3 class="font-semibold text-lg mb-3">Фільтр замовлень</h3>
                <ul class="space-y-2">
                    <li>
                        <a href="orders.php" class="<?php echo empty($statusFilter) ? 'text-teal-600 font-medium' : 'text-gray-600'; ?> hover:text-teal-800 block py-1">
                            Всі активні замовлення
                        </a>
                    </li>
                    <li>
                        <a href="?status=pending" class="<?php echo $statusFilter === 'pending' ? 'text-teal-600 font-medium' : 'text-gray-600'; ?> hover:text-teal-800 block py-1">
                            Очікують підтвердження
                        </a>
                    </li>
                    <li>
                        <a href="?status=approved" class="<?php echo $statusFilter === 'approved' ? 'text-teal-600 font-medium' : 'text-gray-600'; ?> hover:text-teal-800 block py-1">
                            Підтверджені
                        </a>
                    </li>
                    <li>
                        <a href="?status=rejected" class="<?php echo $statusFilter === 'rejected' ? 'text-teal-600 font-medium' : 'text-gray-600'; ?> hover:text-teal-800 block py-1">
                            Відхилені
                        </a>
                    </li>
                    <li>
                        <a href="?status=received" class="<?php echo $statusFilter === 'received' ? 'text-teal-600 font-medium' : 'text-gray-600'; ?> hover:text-teal-800 block py-1">
                            Отримані
                        </a>
                    </li>
                </ul>
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <button id="showCreateOrderForm" class="w-full flex justify-center items-center px-4 py-2 bg-teal-600 text-white rounded-md hover:bg-teal-700">
                        <i class="fas fa-plus mr-2"></i> Створити нове замовлення
                    </button>
                </div>
            </div>
        </aside>
        
        <!-- Основний контент -->
        <main class="w-full md:w-3/4">
            <?php if (isset($_GET['view']) && $orderDetails): ?>
                <!-- Режим перегляду деталей замовлення -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-semibold text-gray-800">
                            <a href="orders.php" class="text-teal-600 hover:text-teal-800 mr-2">
                                <i class="fas fa-arrow-left"></i>
                            </a>
                            Замовлення #<?php echo $orderDetails['order']['id']; ?>
                        </h2>
                        <div>
                            <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full <?php echo $statusClasses[$orderDetails['order']['status']]; ?>">
                                <?php echo $statusTranslations[$orderDetails['order']['status']]; ?>
                            </span>
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
                                    <dt class="text-sm font-medium text-gray-500">Статус:</dt>
                                    <dd class="text-sm col-span-2">
                                        <span class="px-2 py-1 text-xs rounded-full <?php echo $statusClasses[$orderDetails['order']['status']]; ?>">
                                            <?php echo $statusTranslations[$orderDetails['order']['status']]; ?>
                                        </span>
                                    </dd>
                                </div>
                                <div class="grid grid-cols-3 gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Дата створення:</dt>
                                    <dd class="text-sm text-gray-900 col-span-2"><?php echo date('d.m.Y H:i', strtotime($orderDetails['order']['created_at'])); ?></dd>
                                </div>
                                <div class="grid grid-cols-3 gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Останнє оновлення:</dt>
                                    <dd class="text-sm text-gray-900 col-span-2"><?php echo date('d.m.Y H:i', strtotime($orderDetails['order']['updated_at'])); ?></dd>
                                </div>
                                <div class="grid grid-cols-3 gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Створив:</dt>
                                    <dd class="text-sm text-gray-900 col-span-2"><?php echo htmlspecialchars($orderDetails['order']['created_by_name']); ?></dd>
                                </div>
                            </dl>
                        </div>
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-3">Інформація про постачальника</h3>
                            <dl class="space-y-2">
                                <div class="grid grid-cols-3 gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Компанія:</dt>
                                    <dd class="text-sm text-gray-900 col-span-2"><?php echo htmlspecialchars($orderDetails['order']['company_name']); ?></dd>
                                </div>
                                <div class="grid grid-cols-3 gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Сума замовлення:</dt>
                                    <dd class="text-sm text-gray-900 col-span-2"><?php echo number_format($orderDetails['order']['total_amount'], 2, ',', ' ') . ' ₴'; ?></dd>
                                </div>
                                
                                <!-- Дії з замовленням -->
                                <?php if ($orderDetails['order']['status'] === 'pending'): ?>
                                <div class="col-span-3 mt-4 flex space-x-3">
                                    <form method="POST" action="" class="flex-1">
                                        <input type="hidden" name="order_id" value="<?php echo $orderDetails['order']['id']; ?>">
                                        <button type="submit" name="approve_order" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded">
                                            <i class="fas fa-check mr-1"></i> Підтвердити замовлення
                                        </button>
                                    </form>
                                    <form method="POST" action="" class="flex-1">
                                        <input type="hidden" name="order_id" value="<?php echo $orderDetails['order']['id']; ?>">
                                        <button type="submit" name="reject_order" class="w-full bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded">
                                            <i class="fas fa-times mr-1"></i> Відхилити замовлення
                                        </button>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </dl>
                        </div>
                    </div>
                    
                    <!-- Елементи замовлення -->
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Елементи замовлення</h3>
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
                                <?php $totalAmount = 0; ?>
                                <?php foreach ($orderDetails['items'] as $item): ?>
                                <?php $itemTotal = $item['quantity'] * $item['price']; $totalAmount += $itemTotal; ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($item['product_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php 
                                        $categoryNames = [
                                            'raw_material' => 'Сировина',
                                            'packaging' => 'Упаковка',
                                            'finished_product' => 'Готова продукція'
                                        ];
                                        echo $categoryNames[$item['category']] ?? $item['category']; 
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $item['quantity'] . ' ' . $item['unit']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo number_format($item['price'], 2, ',', ' ') . ' ₴'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo number_format($itemTotal, 2, ',', ' ') . ' ₴'; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="bg-gray-50">
                                    <td colspan="4" class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-right">
                                        Разом:
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                        <?php echo number_format($totalAmount, 2, ',', ' ') . ' ₴'; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Повідомлення постачальнику -->
                    <div class="mt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-3">Зв'язок з постачальником</h3>
                        <a href="messages.php?compose=1&supplier_id=<?php echo $orderDetails['order']['supplier_id']; ?>&order_id=<?php echo $orderDetails['order']['id']; ?>" 
                           class="inline-flex items-center px-4 py-2 bg-teal-600 text-white rounded-md hover:bg-teal-700">
                            <i class="fas fa-envelope mr-2"></i> Написати постачальнику
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Режим списку замовлень -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-semibold text-gray-800">
                            <?php
                            if ($statusFilter === 'pending') {
                                echo 'Замовлення, що очікують підтвердження';
                            } elseif ($statusFilter === 'approved') {
                                echo 'Підтверджені замовлення';
                            } elseif ($statusFilter === 'rejected') {
                                echo 'Відхилені замовлення';
                            } elseif ($statusFilter === 'received') {
                                echo 'Отримані замовлення';
                            } else {
                                echo 'Активні замовлення';
                            }
                            ?>
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
                    
                    <!-- Форма створення нового замовлення -->
                    <div id="createOrderForm" class="hidden bg-gray-50 p-6 rounded-lg mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Створення нового замовлення</h3>
                        <form method="POST" action="" id="newOrderForm">
                            <!-- Вибір постачальника -->
                            <div class="mb-4">
                                <label for="supplier_id" class="block text-sm font-medium text-gray-700 mb-2">Постачальник</label>
                                <select id="supplier_id" name="supplier_id" required
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 sm:text-sm">
                                    <option value="">Виберіть постачальника</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['id']; ?>"><?php echo htmlspecialchars($supplier['company_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Товари для замовлення -->
                            <div class="mb-4">
                                <h4 class="font-medium text-gray-900 mb-2">Товари</h4>
                                <div id="orderItems">
                                    <div class="grid grid-cols-12 gap-2 mb-2 items-center">
                                        <div class="col-span-5">
                                        <label class="block text-xs font-medium text-gray-700">Товар</label>
                                        </div>
                                        <div class="col-span-3">
                                            <label class="block text-xs font-medium text-gray-700">Кількість</label>
                                        </div>
                                        <div class="col-span-3">
                                            <label class="block text-xs font-medium text-gray-700">Ціна за од. (₴)</label>
                                        </div>
                                        <div class="col-span-1">
                                            <label class="block text-xs font-medium text-gray-700">&nbsp;</label>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-12 gap-2 mb-2 items-center orderItemRow">
                                        <div class="col-span-5">
                                            <select name="product_id_0" required
                                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 sm:text-sm">
                                                <option value="">Виберіть товар</option>
                                                <optgroup label="Сировина">
                                                    <?php 
                                                    $rawMaterials = $purchasingController->getProductsByCategory('raw_material');
                                                    foreach ($rawMaterials as $product): 
                                                    ?>
                                                    <option value="<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?></option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                                <optgroup label="Упаковка">
                                                    <?php 
                                                    $packaging = $purchasingController->getProductsByCategory('packaging');
                                                    foreach ($packaging as $product): 
                                                    ?>
                                                    <option value="<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?></option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            </select>
                                        </div>
                                        <div class="col-span-3">
                                            <input type="number" name="quantity_0" required min="1" placeholder="К-сть"
                                                  class="block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 sm:text-sm">
                                        </div>
                                        <div class="col-span-3">
                                            <input type="number" name="price_0" required min="0.01" step="0.01" placeholder="Ціна"
                                                  class="block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 sm:text-sm">
                                        </div>
                                        <div class="col-span-1">
                                            <button type="button" class="text-red-600 hover:text-red-800 disabled:text-gray-400"
                                                    onclick="removeOrderItem(this)" disabled>
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <button type="button" class="text-teal-600 hover:text-teal-800" onclick="addOrderItem()">
                                        <i class="fas fa-plus mr-1"></i> Додати товар
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mt-6 flex justify-end space-x-3">
                                <button type="button" id="cancelCreateOrder" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Скасувати
                                </button>
                                <button type="submit" name="create_order" class="bg-teal-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-teal-700">
                                    Створити замовлення
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Таблиця замовлень -->
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
                                        Дата
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Сума
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
                                <?php if (empty($ordersList)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                        Замовлення не знайдені
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($ordersList as $order): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo $order['id']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($order['company_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('d.m.Y', strtotime($order['created_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo number_format($order['total_amount'], 2, ',', ' '); ?> ₴
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClasses[$order['status']]; ?>">
                                                <?php echo $statusTranslations[$order['status']]; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="?view=<?php echo $order['id']; ?>" class="text-teal-600 hover:text-teal-900 mr-3">
                                                Деталі
                                            </a>
                                            
                                            <?php if ($order['status'] === 'pending'): ?>
                                            <form method="POST" action="" class="inline-block mr-3">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <button type="submit" name="approve_order" class="text-green-600 hover:text-green-900">
                                                    Підтвердити
                                                </button>
                                            </form>
                                            <form method="POST" action="" class="inline-block">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <button type="submit" name="reject_order" class="text-red-600 hover:text-red-900">
                                                    Відхилити
                                                </button>
                                            </form>
                                            <?php endif; ?>
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
            // Форма створення замовлення
            const showCreateOrderFormBtn = document.getElementById('showCreateOrderForm');
            const cancelCreateOrderBtn = document.getElementById('cancelCreateOrder');
            const createOrderForm = document.getElementById('createOrderForm');
            
            if (showCreateOrderFormBtn && cancelCreateOrderBtn && createOrderForm) {
                showCreateOrderFormBtn.addEventListener('click', function() {
                    createOrderForm.classList.remove('hidden');
                    showCreateOrderFormBtn.classList.add('hidden');
                });
                
                cancelCreateOrderBtn.addEventListener('click', function() {
                    createOrderForm.classList.add('hidden');
                    showCreateOrderFormBtn.classList.remove('hidden');
                });
            }
        });
        
        // Лічильник для унікальних імен полів товару
        let itemCounter = 1;
        
        // Додавання товару в замовлення
        function addOrderItem() {
            const orderItems = document.getElementById('orderItems');
            const template = document.querySelector('.orderItemRow').cloneNode(true);
            
            // Оновлюємо імена полів
            template.querySelector('select').name = 'product_id_' + itemCounter;
            template.querySelector('input[type="number"][placeholder="К-сть"]').name = 'quantity_' + itemCounter;
            template.querySelector('input[type="number"][placeholder="Ціна"]').name = 'price_' + itemCounter;
            
            // Очищаємо значення
            template.querySelector('select').selectedIndex = 0;
            template.querySelector('input[type="number"][placeholder="К-сть"]').value = '';
            template.querySelector('input[type="number"][placeholder="Ціна"]').value = '';
            
            // Включаємо кнопку видалення
            template.querySelector('button').disabled = false;
            
            // Збільшуємо лічильник
            itemCounter++;
            
            // Додаємо елемент у форму
            orderItems.appendChild(template);
        }
        
        // Видалення товару з замовлення
        function removeOrderItem(button) {
            const row = button.closest('.orderItemRow');
            row.remove();
        }
    </script>
</body>
</html>