<?php
// views/supplier/orders.php
// Сторінка перегляду та обробки замовлень для постачальника

// Підключення контролерів
require_once '../../controllers/AuthController.php';
require_once '../../controllers/SupplierController.php';

$authController = new AuthController();
$supplierController = new SupplierController();

// Перевірка авторизації та ролі
if (!$authController->isLoggedIn() || !$authController->checkRole('supplier')) {
    header('Location: ../../index.php');
    exit;
}

// Отримання даних користувача
$currentUser = $authController->getCurrentUser();
$supplierId = $supplierController->getSupplierIdByUserId($currentUser['id']);

if (!$supplierId) {
    // Якщо дані постачальника не знайдені, відображаємо помилку
    $error = "Помилка: дані постачальника не знайдені. Зверніться до адміністратора.";
} else {
    // Отримуємо інформацію про постачальника
    $supplierInfo = $supplierController->getSupplierInfo($supplierId);
    
    // Фільтрація замовлень за статусом
    $statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
    
    if ($statusFilter) {
        $ordersList = $supplierController->getOrdersByStatus($supplierId, $statusFilter);
    } else {
        // Якщо статус не вказано, показуємо всі замовлення
        $pendingOrders = $supplierController->getOrdersByStatus($supplierId, 'pending');
        $approvedOrders = $supplierController->getOrdersByStatus($supplierId, 'approved');
        $rejectedOrders = $supplierController->getOrdersByStatus($supplierId, 'rejected');
        $receivedOrders = $supplierController->getOrdersByStatus($supplierId, 'received');
        
        $ordersList = array_merge($pendingOrders, $approvedOrders, $rejectedOrders, $receivedOrders);
        
        // Сортування за датою (спочатку нові)
        usort($ordersList, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
    }
    
    // Отримання статистики по замовленнях
    $orderStats = $supplierController->getOrderStats($supplierId);
}

// Обробка дій із замовленнями
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Прийняття замовлення
    if (isset($_POST['accept_order'])) {
        $orderId = $_POST['order_id'] ?? '';
        
        if (empty($orderId)) {
            $error = "Ідентифікатор замовлення не вказано";
        } else {
            $result = $supplierController->acceptOrder($orderId, $supplierId);
            if ($result['success']) {
                $message = $result['message'];
                // Перезавантажуємо дані
                if ($statusFilter) {
                    $ordersList = $supplierController->getOrdersByStatus($supplierId, $statusFilter);
                } else {
                    // Оновлюємо всі замовлення
                    $pendingOrders = $supplierController->getOrdersByStatus($supplierId, 'pending');
                    $approvedOrders = $supplierController->getOrdersByStatus($supplierId, 'approved');
                    $rejectedOrders = $supplierController->getOrdersByStatus($supplierId, 'rejected');
                    $receivedOrders = $supplierController->getOrdersByStatus($supplierId, 'received');
                    
                    $ordersList = array_merge($pendingOrders, $approvedOrders, $rejectedOrders, $receivedOrders);
                    
                    // Сортування за датою (спочатку нові)
                    usort($ordersList, function($a, $b) {
                        return strtotime($b['created_at']) - strtotime($a['created_at']);
                    });
                }
                // Оновлюємо статистику
                $orderStats = $supplierController->getOrderStats($supplierId);
            } else {
                $error = $result['message'];
            }
        }
    }
    
    // Відхилення замовлення
    if (isset($_POST['reject_order'])) {
        $orderId = $_POST['order_id'] ?? '';
        $reason = $_POST['rejection_reason'] ?? '';
        
        if (empty($orderId)) {
            $error = "Ідентифікатор замовлення не вказано";
        } else {
            $result = $supplierController->rejectOrder($orderId, $supplierId, $reason);
            if ($result['success']) {
                $message = $result['message'];
                // Перезавантажуємо дані
                if ($statusFilter) {
                    $ordersList = $supplierController->getOrdersByStatus($supplierId, $statusFilter);
                } else {
                    // Оновлюємо всі замовлення
                    $pendingOrders = $supplierController->getOrdersByStatus($supplierId, 'pending');
                    $approvedOrders = $supplierController->getOrdersByStatus($supplierId, 'approved');
                    $rejectedOrders = $supplierController->getOrdersByStatus($supplierId, 'rejected');
                    $receivedOrders = $supplierController->getOrdersByStatus($supplierId, 'received');
                    
                    $ordersList = array_merge($pendingOrders, $approvedOrders, $rejectedOrders, $receivedOrders);
                    
                    // Сортування за датою (спочатку нові)
                    usort($ordersList, function($a, $b) {
                        return strtotime($b['created_at']) - strtotime($a['created_at']);
                    });
                }
                // Оновлюємо статистику
                $orderStats = $supplierController->getOrderStats($supplierId);
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
    $orderDetails = $supplierController->getOrderWithItems($orderId, $supplierId);
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
    <nav class="bg-amber-800 text-white p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center">
                <i class="fas fa-wine-bottle text-2xl mr-3"></i>
                <h1 class="text-xl font-bold">Винне виробництво</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span><?php echo htmlspecialchars($currentUser['name']); ?> (Постачальник)</span>
                <a href="../../controllers/logout.php" class="bg-amber-700 hover:bg-amber-600 py-2 px-4 rounded text-sm">
                    <i class="fas fa-sign-out-alt mr-1"></i> Вийти
                </a>
            </div>
        </div>
    </nav>
    
    <?php if (isset($error) && $error === "Помилка: дані постачальника не знайдені. Зверніться до адміністратора."): ?>
    <div class="container mx-auto mt-6 px-4">
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
            <p><?php echo $error; ?></p>
        </div>
    </div>
    <?php else: ?>
    
    <!-- Бічна панель і основний вміст -->
    <div class="container mx-auto flex flex-wrap mt-6 px-4">
        <!-- Бічна навігація -->
        <aside class="w-full md:w-1/4 pr-0 md:pr-6">
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <div class="flex items-center mb-4 pb-4 border-b border-gray-200">
                    <div class="bg-amber-100 p-3 rounded-full mr-4">
                        <i class="fas fa-user text-amber-600"></i>
                    </div>
                    <div>
                        <p class="font-semibold"><?php echo htmlspecialchars($currentUser['name']); ?></p>
                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($supplierInfo['company_name']); ?></p>
                    </div>
                </div>
                
                <ul class="space-y-2">
                    <li>
                        <a href="dashboard.php" class="flex items-center p-2 text-gray-700 hover:bg-amber-50 rounded font-medium">
                            <i class="fas fa-tachometer-alt w-5 mr-2"></i>
                            <span>Панель керування</span>
                        </a>
                    </li>
                    <li>
                        <a href="orders.php" class="flex items-center p-2 bg-amber-100 text-amber-700 rounded font-medium">
                            <i class="fas fa-shopping-cart w-5 mr-2"></i>
                            <span>Замовлення</span>
                            <?php if ($orderStats['pending_count'] > 0): ?>
                            <span class="ml-auto bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                <?php echo $orderStats['pending_count']; ?>
                            </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="products.php" class="flex items-center p-2 text-gray-700 hover:bg-amber-50 rounded font-medium">
                            <i class="fas fa-boxes w-5 mr-2"></i>
                            <span>Мої товари</span>
                        </a>
                    </li>
                    <li>
                        <a href="messages.php" class="flex items-center p-2 text-gray-700 hover:bg-amber-50 rounded font-medium">
                            <i class="fas fa-envelope w-5 mr-2"></i>
                            <span>Повідомлення</span>
                            <?php 
                            $unreadMessages = $supplierController->getUnreadMessages($currentUser['id']);
                            if (count($unreadMessages) > 0): 
                            ?>
                            <span class="ml-auto bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                <?php echo count($unreadMessages); ?>
                            </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="profile.php" class="flex items-center p-2 text-gray-700 hover:bg-amber-50 rounded font-medium">
                            <i class="fas fa-user-cog w-5 mr-2"></i>
                            <span>Мій профіль</span>
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Блок статистики замовлень -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <h3 class="font-semibold text-lg mb-3">Статистика замовлень</h3>
                <ul class="space-y-3">
                    <li class="flex justify-between">
                        <span class="text-gray-600">Всього замовлень:</span>
                        <span class="font-semibold"><?php echo $orderStats['total_orders']; ?></span>
                    </li>
                    <li class="flex justify-between">
                        <a href="?status=pending" class="text-gray-600 hover:text-amber-700">Очікують підтвердження:</a>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                            <?php echo $orderStats['pending_count']; ?>
                        </span>
                    </li>
                    <li class="flex justify-between">
                        <a href="?status=approved" class="text-gray-600 hover:text-amber-700">Підтверджені:</a>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                            <?php echo $orderStats['approved_count']; ?>
                        </span>
                    </li>
                    <li class="flex justify-between">
                        <a href="?status=rejected" class="text-gray-600 hover:text-amber-700">Відхилені:</a>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                            <?php echo $orderStats['rejected_count']; ?>
                        </span>
                    </li>
                    <li class="flex justify-between">
                        <a href="?status=received" class="text-gray-600 hover:text-amber-700">Отримані:</a>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                            <?php echo $orderStats['received_count']; ?>
                        </span>
                    </li>
                    <li class="border-t border-gray-200 pt-3 mt-2">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Загальна сума:</span>
                            <span class="font-semibold"><?php echo number_format($orderStats['total_amount'], 2, ',', ' '); ?> ₴</span>
                        </div>
                    </li>
                </ul>
            </div>
        </aside>
        
        <!-- Основний вміст -->
        <main class="w-full md:w-3/4">
            <?php if (isset($_GET['view']) && $orderDetails): ?>
                <!-- Режим перегляду деталей замовлення -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-semibold text-gray-800">
                            <a href="orders.php" class="text-amber-600 hover:text-amber-800 mr-2">
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
                                    <dt class="text-sm font-medium text-gray-500">Створено:</dt>
                                    <dd class="text-sm text-gray-900 col-span-2"><?php echo date('d.m.Y H:i', strtotime($orderDetails['order']['created_at'])); ?></dd>
                                </div>
                                <div class="grid grid-cols-3 gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Оновлено:</dt>
                                    <dd class="text-sm text-gray-900 col-span-2"><?php echo date('d.m.Y H:i', strtotime($orderDetails['order']['updated_at'])); ?></dd>
                                </div>
                                <div class="grid grid-cols-3 gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Створив:</dt>
                                    <dd class="text-sm text-gray-900 col-span-2"><?php echo htmlspecialchars($orderDetails['order']['created_by_name']); ?></dd>
                                </div>
                            </dl>
                        </div>
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-3">Сума замовлення</h3>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="text-sm text-gray-500 mb-1">Загальна сума замовлення:</div>
                                <div class="text-3xl font-bold text-gray-900 mb-2">
                                    <?php echo number_format($orderDetails['order']['total_amount'], 2, ',', ' '); ?> ₴
                                </div>
                                
                                <!-- Дії із замовленням -->
                                <?php if ($orderDetails['order']['status'] === 'pending'): ?>
                                <div class="mt-4 flex flex-col space-y-2">
                                    <button id="showAcceptForm" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded">
                                        <i class="fas fa-check mr-1"></i> Підтвердити замовлення
                                    </button>
                                    <button id="showRejectForm" class="w-full bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded">
                                        <i class="fas fa-times mr-1"></i> Відхилити замовлення
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Форми підтвердження/відхилення замовлення -->
                    <?php if ($orderDetails['order']['status'] === 'pending'): ?>
                    <div id="acceptForm" class="hidden bg-gray-50 p-6 rounded-lg mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Підтвердження замовлення</h3>
                        <p class="text-gray-600 mb-4">
                            Ви підтверджуєте, що можете виконати це замовлення згідно із зазначеними умовами?
                        </p>
                        <form method="POST" action="">
                            <input type="hidden" name="order_id" value="<?php echo $orderDetails['order']['id']; ?>">
                            <div class="flex justify-end space-x-3">
                                <button type="button" id="cancelAccept" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Скасувати
                                </button>
                                <button type="submit" name="accept_order" class="bg-green-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-green-700">
                                    Так, підтверджую
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div id="rejectForm" class="hidden bg-gray-50 p-6 rounded-lg mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Відхилення замовлення</h3>
                        <form method="POST" action="">
                            <input type="hidden" name="order_id" value="<?php echo $orderDetails['order']['id']; ?>">
                            <div class="mb-4">
                                <label for="rejection_reason" class="block text-sm font-medium text-gray-700 mb-2">Причина відхилення</label>
                                <textarea id="rejection_reason" name="rejection_reason" rows="3" required
                                          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm"></textarea>
                            </div>
                            <div class="flex justify-end space-x-3">
                                <button type="button" id="cancelReject" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Скасувати
                                </button>
                                <button type="submit" name="reject_order" class="bg-red-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-red-700">
                                    Відхилити замовлення
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                    
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
                    
                    <!-- Зв'язок з менеджером -->
                    <div class="mt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-3">Зв'язок з менеджером</h3>
                        <a href="messages.php?compose=1&order_id=<?php echo $orderDetails['order']['id']; ?>" 
                           class="inline-flex items-center px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700">
                            <i class="fas fa-envelope mr-2"></i> Написати менеджеру із закупівель
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
                            } elseif ($statusFilter === 'received') { echo 'Отримані замовлення';
                            } else {
                                echo 'Усі замовлення';
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
                    
                    <!-- Таблиця замовлень -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        ID
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Дата створення
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Створив
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
                                            <?php echo date('d.m.Y', strtotime($order['created_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($order['created_by_name'] ?? 'Система'); ?>
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
                                            <a href="?view=<?php echo $order['id']; ?>" class="text-amber-600 hover:text-amber-900">
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
            // Форми підтвердження/відхилення замовлення
            const showAcceptFormBtn = document.getElementById('showAcceptForm');
            const cancelAcceptBtn = document.getElementById('cancelAccept');
            const acceptForm = document.getElementById('acceptForm');
            
            const showRejectFormBtn = document.getElementById('showRejectForm');
            const cancelRejectBtn = document.getElementById('cancelReject');
            const rejectForm = document.getElementById('rejectForm');
            
            if (showAcceptFormBtn && cancelAcceptBtn && acceptForm) {
                showAcceptFormBtn.addEventListener('click', function() {
                    acceptForm.classList.remove('hidden');
                    if (rejectForm) rejectForm.classList.add('hidden');
                });
                
                cancelAcceptBtn.addEventListener('click', function() {
                    acceptForm.classList.add('hidden');
                });
            }
            
            if (showRejectFormBtn && cancelRejectBtn && rejectForm) {
                showRejectFormBtn.addEventListener('click', function() {
                    rejectForm.classList.remove('hidden');
                    if (acceptForm) acceptForm.classList.add('hidden');
                });
                
                cancelRejectBtn.addEventListener('click', function() {
                    rejectForm.classList.add('hidden');
                });
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>