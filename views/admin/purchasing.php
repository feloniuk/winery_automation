<?php
// views/admin/purchasing.php
// Сторінка управління закупівлями для адміністратора

// Підключення контролерів
require_once '../../controllers/AuthController.php';
require_once '../../controllers/PurchasingController.php';
require_once '../../controllers/AdminController.php';

$authController = new AuthController();
$purchasingController = new PurchasingController();
$adminController = new AdminController();

// Перевірка авторизації та ролі
if (!$authController->isLoggedIn() || !$authController->checkRole('admin')) {
    header('Location: ../../index.php');
    exit;
}

// Отримання даних для сторінки
$currentUser = $authController->getCurrentUser();
$suppliers = $purchasingController->getActiveSuppliers();
$lowStockItems = $purchasingController->getLowStockItems();

// Отримання замовлень за статусами
$pendingOrders = $purchasingController->getOrdersByStatus('pending');
$approvedOrders = $purchasingController->getOrdersByStatus('approved');
$rejectedOrders = $purchasingController->getOrdersByStatus('rejected');
$receivedOrders = $purchasingController->getRecentReceivedOrders(10);

// Статус для фільтрації
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
if ($statusFilter) {
    $orders = $purchasingController->getOrdersByStatus($statusFilter);
} else {
    // Показуємо активні замовлення (pending + approved)
    $orders = array_merge($pendingOrders, $approvedOrders);
    
    // Сортування за датою (спочатку нові)
    usort($orders, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
}

// Створення даних для графіка замовлень по місяцях
$ordersByMonth = $purchasingController->getOrderCountByMonth(6);

// Лічильники замовлень
$totalOrders = count((array)$pendingOrders) + count((array)$approvedOrders) + count((array)$rejectedOrders) + count((array)$receivedOrders);
$activeOrders = count((array)$pendingOrders) + count((array)$approvedOrders);

// Обробка дій із замовленнями
$message = '';
$error = '';

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
                // Оновлюємо списки замовлень
                $pendingOrders = $purchasingController->getOrdersByStatus('pending');
                $approvedOrders = $purchasingController->getOrdersByStatus('approved');
                $orders = $statusFilter ? $purchasingController->getOrdersByStatus($statusFilter) : array_merge($pendingOrders, $approvedOrders);
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
                // Оновлюємо списки замовлень
                $pendingOrders = $purchasingController->getOrdersByStatus('pending');
                $approvedOrders = $purchasingController->getOrdersByStatus('approved');
                $rejectedOrders = $purchasingController->getOrdersByStatus('rejected');
                $orders = $statusFilter ? $purchasingController->getOrdersByStatus($statusFilter) : array_merge($pendingOrders, $approvedOrders);
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
    <title>Управління закупівлями - Панель адміністратора</title>
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
                        <a href="warehouse.php" class="flex items-center p-2 text-gray-700 hover:bg-indigo-50 rounded font-medium">
                            <i class="fas fa-boxes w-5 mr-2"></i>
                            <span>Склад</span>
                        </a>
                    </li>
                    <li>
                        <a href="purchasing.php" class="flex items-center p-2 bg-indigo-100 text-indigo-700 rounded font-medium">
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
            
            <!-- Фільтр замовлень -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <h3 class="font-semibold text-lg mb-3">Фільтр замовлень</h3>
                <ul class="space-y-2">
                    <li>
                        <a href="purchasing.php" class="<?php echo empty($statusFilter) ? 'text-indigo-600 font-medium' : 'text-gray-600'; ?> hover:text-indigo-800 block py-1">
                            Всі активні замовлення
                        </a>
                    </li>
                    <li>
                        <a href="?status=pending" class="<?php echo $statusFilter === 'pending' ? 'text-indigo-600 font-medium' : 'text-gray-600'; ?> hover:text-indigo-800 block py-1">
                            Очікують підтвердження 
                            <span class="bg-yellow-100 text-yellow-800 text-xs rounded-full px-2"><?php echo count($pendingOrders); ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="?status=approved" class="<?php echo $statusFilter === 'approved' ? 'text-indigo-600 font-medium' : 'text-gray-600'; ?> hover:text-indigo-800 block py-1">
                            Підтверджені
                            <span class="bg-green-100 text-green-800 text-xs rounded-full px-2"><?php echo count($approvedOrders); ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="?status=rejected" class="<?php echo $statusFilter === 'rejected' ? 'text-indigo-600 font-medium' : 'text-gray-600'; ?> hover:text-indigo-800 block py-1">
                            Відхилені
                            <span class="bg-red-100 text-red-800 text-xs rounded-full px-2"><?php echo count($rejectedOrders); ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="?status=received" class="<?php echo $statusFilter === 'received' ? 'text-indigo-600 font-medium' : 'text-gray-600'; ?> hover:text-indigo-800 block py-1">
                            Отримані
                        </a>
                    </li>
                </ul>
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <a href="../purchasing/orders.php?action=create" class="w-full flex justify-center items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                        <i class="fas fa-plus mr-2"></i> Створити нове замовлення
                    </a>
                </div>
            </div>
            
            <!-- Блок з товарами з низьким запасом -->
            <div class="bg-white rounded-lg shadow-md p-4">
                <h3 class="font-semibold text-lg mb-3">Товари з низьким запасом</h3>
                
                <?php if (empty($lowStockItems)): ?>
                <p class="text-green-600 text-center py-2">Всі товари мають достатній запас</p>
                <?php else: ?>
                <ul class="space-y-2">
                    <?php foreach (array_slice($lowStockItems, 0, 6) as $item): ?>
                    <li class="p-2 hover:bg-red-50 rounded">
                        <div class="flex justify-between items-center">
                            <span class="text-red-600"><?php echo htmlspecialchars($item['name']); ?></span>
                            <span class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded-full">
                                <?php echo $item['quantity'] . ' ' . $item['unit']; ?>
                            </span>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php if (count($lowStockItems) > 6): ?>
                <div class="mt-2 text-center">
                    <a href="../purchasing/low_stock.php" class="text-indigo-600 hover:text-indigo-800 text-sm">
                        Показати всі (<?php echo count($lowStockItems); ?>)
                    </a>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </aside>
        
        <!-- Основний контент -->
        <main class="w-full md:w-3/4">
            <?php if (isset($orderDetails)): ?>
            <!-- Режим перегляду деталей замовлення -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold text-gray-800">
                        <a href="purchasing.php" class="text-indigo-600 hover:text-indigo-800 mr-2">
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
                                    Всього:
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                    <?php echo number_format($totalAmount, 2, ',', ' ') . ' ₴'; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Зв'язок з постачальником -->
                <div class="mt-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Зв'язок з постачальником</h3>
                    <a href="../common/messages.php?compose=1&supplier_id=<?php echo $orderDetails['order']['supplier_id']; ?>&order_id=<?php echo $orderDetails['order']['id']; ?>" 
                       class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                        <i class="fas fa-envelope mr-2"></i> Написати постачальнику
                    </a>
                </div>
            </div>
            <?php else: ?>
            <!-- Режим перегляду списку замовлень -->
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
                    <a href="../purchasing/orders.php" class="text-indigo-600 hover:text-indigo-800">
                        Перейти до розділу закупівель <i class="fas fa-arrow-right ml-1"></i>
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
                
                <!-- Картки з короткою статистикою -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <div class="bg-gray-50 rounded-lg shadow-sm p-4">
                        <div class="flex items-center">
                            <div class="bg-yellow-100 p-3 rounded-full mr-4">
                                <i class="fas fa-clock text-yellow-600"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Очікує підтвердження</p>
                                <p class="text-2xl font-bold"><?php echo count($pendingOrders); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-lg shadow-sm p-4">
                        <div class="flex items-center">
                            <div class="bg-green-100 p-3 rounded-full mr-4">
                                <i class="fas fa-check text-green-600"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Підтверджено</p>
                                <p class="text-2xl font-bold"><?php echo count($approvedOrders); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-lg shadow-sm p-4">
                        <div class="flex items-center">
                            <div class="bg-blue-100 p-3 rounded-full mr-4">
                                <i class="fas fa-truck-loading text-blue-600"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Підтверджено</p>
                                <p class="text-2xl font-bold"><?php echo count($approvedOrders); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-lg shadow-sm p-4">
                        <div class="flex items-center">
                            <div class="bg-blue-100 p-3 rounded-full mr-4">
                                <i class="fas fa-truck-loading text-blue-600"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Активні постачальники</p>
                                <p class="text-2xl font-bold"><?php echo count($suppliers); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Графік замовлень по місяцях -->
                <div class="mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Динаміка замовлень по місяцях</h3>
                    <div class="bg-gray-50 p-4 rounded-lg h-72">
                        <canvas id="ordersChart"></canvas>
                    </div>
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
                            <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                    Замовлення не знайдені
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
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
                                        <a href="?view=<?php echo $order['id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">
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
            
            <!-- Список постачальників -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-800">Активні постачальники</h2>
                    <a href="../purchasing/suppliers.php" class="text-indigo-600 hover:text-indigo-800 text-sm">
                        Управління постачальниками <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                
                <?php if (empty($suppliers)): ?>
                <div class="text-center py-6">
                    <p class="text-gray-500">Постачальники не знайдені</p>
                </div>
                <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach (array_slice($suppliers, 0, 6) as $supplier): ?>
                    <div class="bg-gray-50 rounded-lg p-4 hover:shadow-md transition-shadow duration-200">
                        <div class="font-medium text-gray-900 mb-1"><?php echo htmlspecialchars($supplier['company_name']); ?></div>
                        <div class="text-sm text-gray-500 mb-2"><?php echo htmlspecialchars($supplier['contact_person']); ?></div>
                        <div class="flex items-center text-xs text-gray-500 mb-1">
                            <i class="fas fa-phone w-4 mr-1"></i>
                            <?php echo htmlspecialchars($supplier['phone']); ?>
                        </div>
                        <div class="flex items-center text-xs text-gray-500">
                            <i class="fas fa-envelope w-4 mr-1"></i>
                            <?php echo htmlspecialchars($supplier['email']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($suppliers) > 6): ?>
                <div class="mt-4 text-center">
                    <a href="../purchasing/suppliers.php" class="text-indigo-600 hover:text-indigo-800 text-sm">
                        Показати всіх постачальників (<?php echo count($suppliers); ?>)
                    </a>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>
    
    <footer class="bg-white p-4 mt-8 border-t border-gray-200">
        <div class="container mx-auto text-center text-gray-500 text-sm">
            &copy; <?php echo date('Y'); ?> Винне виробництво. Система автоматизації процесів.
        </div>
    </footer>
    
    <!-- JavaScript для графіків -->
    <script>
        <?php if (!empty($ordersByMonth)): ?>
        // Графік замовлень по місяцях
        var ctx = document.getElementById('ordersChart').getContext('2d');
        var ordersChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($ordersByMonth, 'month_name')); ?>,
                datasets: [{
                    label: 'Кількість замовлень',
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