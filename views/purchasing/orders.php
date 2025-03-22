<?php
// views/purchasing/orders.php
// Страница просмотра и управления заказами для менеджера по закупкам

// Подключение контроллеров
require_once '../../controllers/AuthController.php';
require_once '../../controllers/PurchasingController.php';

$authController = new AuthController();
$purchasingController = new PurchasingController();

// Проверка авторизации и роли
if (!$authController->isLoggedIn() || !$authController->checkRole(['purchasing', 'admin'])) {
    header('Location: ../../index.php');
    exit;
}

// Получение данных для страницы
$currentUser = $authController->getCurrentUser();
$suppliers = $purchasingController->getActiveSuppliers();

// Фильтрация по статусу
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$ordersList = [];

if ($statusFilter) {
    $ordersList = $purchasingController->getOrdersByStatus($statusFilter);
} else {
    // Если статус не указан, показываем все активные заказы (pending и approved)
    $pendingOrders = $purchasingController->getOrdersByStatus('pending');
    $approvedOrders = $purchasingController->getOrdersByStatus('approved');
    $ordersList = array_merge($pendingOrders, $approvedOrders);
    
    // Сортировка по дате (сначала новые)
    usort($ordersList, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
}

// Обработка действий
$message = '';
$error = '';

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Одобрение заказа
    if (isset($_POST['approve_order'])) {
        $orderId = $_POST['order_id'] ?? '';
        
        if (empty($orderId)) {
            $error = "Идентификатор заказа не указан";
        } else {
            $result = $purchasingController->updateOrderStatus($orderId, 'approved');
            if ($result['success']) {
                $message = $result['message'];
                // Перезагружаем список заказов
                if ($statusFilter) {
                    $ordersList = $purchasingController->getOrdersByStatus($statusFilter);
                } else {
                    // Если статус не указан, показываем все активные заказы
                    $pendingOrders = $purchasingController->getOrdersByStatus('pending');
                    $approvedOrders = $purchasingController->getOrdersByStatus('approved');
                    $ordersList = array_merge($pendingOrders, $approvedOrders);
                    
                    // Сортировка по дате (сначала новые)
                    usort($ordersList, function($a, $b) {
                        return strtotime($b['created_at']) - strtotime($a['created_at']);
                    });
                }
            } else {
                $error = $result['message'];
            }
        }
    }
    
    // Отклонение заказа
    if (isset($_POST['reject_order'])) {
        $orderId = $_POST['order_id'] ?? '';
        
        if (empty($orderId)) {
            $error = "Идентификатор заказа не указан";
        } else {
            $result = $purchasingController->updateOrderStatus($orderId, 'rejected');
            if ($result['success']) {
                $message = $result['message'];
                // Перезагружаем список заказов
                if ($statusFilter) {
                    $ordersList = $purchasingController->getOrdersByStatus($statusFilter);
                } else {
                    // Если статус не указан, показываем все активные заказы
                    $pendingOrders = $purchasingController->getOrdersByStatus('pending');
                    $approvedOrders = $purchasingController->getOrdersByStatus('approved');
                    $ordersList = array_merge($pendingOrders, $approvedOrders);
                    
                    // Сортировка по дате (сначала новые)
                    usort($ordersList, function($a, $b) {
                        return strtotime($b['created_at']) - strtotime($a['created_at']);
                    });
                }
            } else {
                $error = $result['message'];
            }
        }
    }
    
    // Создание нового заказа
    if (isset($_POST['create_order'])) {
        $supplierId = $_POST['supplier_id'] ?? '';
        $items = [];
        
        // Собираем информацию о товарах
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
            $error = "Необходимо выбрать поставщика";
        } elseif (empty($items)) {
            $error = "Необходимо добавить хотя бы один товар в заказ";
        } else {
            $result = $purchasingController->createOrder($supplierId, $currentUser['id'], $items);
            if ($result['success']) {
                $message = $result['message'];
                // Перезагружаем список заказов
                if ($statusFilter) {
                    $ordersList = $purchasingController->getOrdersByStatus($statusFilter);
                } else {
                    // Если статус не указан, показываем все активные заказы
                    $pendingOrders = $purchasingController->getOrdersByStatus('pending');
                    $approvedOrders = $purchasingController->getOrdersByStatus('approved');
                    $ordersList = array_merge($pendingOrders, $approvedOrders);
                    
                    // Сортировка по дате (сначала новые)
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

// Получение деталей заказа, если указан ID
$orderDetails = null;
if (isset($_GET['view']) && !empty($_GET['view'])) {
    $orderId = $_GET['view'];
    $orderDetails = $purchasingController->getOrderWithItems($orderId);
}

// Определяем переводы статусов для отображения
$statusTranslations = [
    'pending' => 'Ожидает подтверждения',
    'approved' => 'Подтвержден',
    'rejected' => 'Отклонен',
    'received' => 'Получен'
];

// Определяем классы стилей для статусов
$statusClasses = [
    'pending' => 'bg-yellow-100 text-yellow-800',
    'approved' => 'bg-green-100 text-green-800',
    'rejected' => 'bg-red-100 text-red-800',
    'received' => 'bg-blue-100 text-blue-800'
];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление заказами - Винное производство</title>
    <!-- Подключение Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Иконки -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Верхняя навигационная панель -->
    <nav class="bg-teal-800 text-white p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center">
                <i class="fas fa-wine-bottle text-2xl mr-3"></i>
                <h1 class="text-xl font-bold">Винное производство</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span><?php echo htmlspecialchars($currentUser['name']); ?> (<?php echo $currentUser['role'] === 'admin' ? 'Администратор' : 'Менеджер по закупкам'; ?>)</span>
                <a href="../../controllers/logout.php" class="bg-teal-700 hover:bg-teal-600 py-2 px-4 rounded text-sm">
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
                    <div class="bg-teal-100 p-3 rounded-full mr-4">
                        <i class="fas fa-user text-teal-600"></i>
                    </div>
                    <div>
                        <p class="font-semibold"><?php echo htmlspecialchars($currentUser['name']); ?></p>
                        <p class="text-sm text-gray-500"><?php echo $currentUser['role'] === 'admin' ? 'Администратор' : 'Менеджер по закупкам'; ?></p>
                    </div>
                </div>
                
                <ul class="space-y-2">
                    <li>
                        <a href="dashboard.php" class="flex items-center p-2 text-gray-700 hover:bg-teal-50 rounded font-medium">
                            <i class="fas fa-tachometer-alt w-5 mr-2"></i>
                            <span>Панель управления</span>
                        </a>
                    </li>
                    <li>
                        <a href="suppliers.php" class="flex items-center p-2 text-gray-700 hover:bg-teal-50 rounded font-medium">
                            <i class="fas fa-truck w-5 mr-2"></i>
                            <span>Поставщики</span>
                        </a>
                    </li>
                    <li>
                        <a href="orders.php" class="flex items-center p-2 bg-teal-100 text-teal-700 rounded font-medium">
                            <i class="fas fa-shopping-cart w-5 mr-2"></i>
                            <span>Заказы</span>
                        </a>
                    </li>
                    <li>
                        <a href="inventory.php" class="flex items-center p-2 text-gray-700 hover:bg-teal-50 rounded font-medium">
                            <i class="fas fa-boxes w-5 mr-2"></i>
                            <span>Остатки на складе</span>
                        </a>
                    </li>
                    <li>
                        <a href="messages.php" class="flex items-center p-2 text-gray-700 hover:bg-teal-50 rounded font-medium">
                            <i class="fas fa-envelope w-5 mr-2"></i>
                            <span>Сообщения</span>
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
                            <span>Панель администратора</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <!-- Фильтр заказов -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <h3 class="font-semibold text-lg mb-3">Фильтр заказов</h3>
                <ul class="space-y-2">
                    <li>
                        <a href="orders.php" class="<?php echo empty($statusFilter) ? 'text-teal-600 font-medium' : 'text-gray-600'; ?> hover:text-teal-800 block py-1">
                            Все активные заказы
                        </a>
                    </li>
                    <li>
                        <a href="?status=pending" class="<?php echo $statusFilter === 'pending' ? 'text-teal-600 font-medium' : 'text-gray-600'; ?> hover:text-teal-800 block py-1">
                            Ожидают подтверждения
                        </a>
                    </li>
                    <li>
                        <a href="?status=approved" class="<?php echo $statusFilter === 'approved' ? 'text-teal-600 font-medium' : 'text-gray-600'; ?> hover:text-teal-800 block py-1">
                            Подтвержденные
                        </a>
                    </li>
                    <li>
                        <a href="?status=rejected" class="<?php echo $statusFilter === 'rejected' ? 'text-teal-600 font-medium' : 'text-gray-600'; ?> hover:text-teal-800 block py-1">
                            Отклоненные
                        </a>
                    </li>
                    <li>
                        <a href="?status=received" class="<?php echo $statusFilter === 'received' ? 'text-teal-600 font-medium' : 'text-gray-600'; ?> hover:text-teal-800 block py-1">
                            Полученные
                        </a>
                    </li>
                </ul>
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <button id="showCreateOrderForm" class="w-full flex justify-center items-center px-4 py-2 bg-teal-600 text-white rounded-md hover:bg-teal-700">
                        <i class="fas fa-plus mr-2"></i> Создать новый заказ
                    </button>
                </div>
            </div>
        </aside>
        
        <!-- Основной контент -->
        <main class="w-full md:w-3/4">
            <?php if (isset($_GET['view']) && $orderDetails): ?>
                <!-- Режим просмотра деталей заказа -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-semibold text-gray-800">
                            <a href="orders.php" class="text-teal-600 hover:text-teal-800 mr-2">
                                <i class="fas fa-arrow-left"></i>
                            </a>
                            Заказ #<?php echo $orderDetails['order']['id']; ?>
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
                    
                    <!-- Информация о заказе -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-3">Информация о заказе</h3>
                            <dl class="space-y-2">
                                <div class="grid grid-cols-3 gap-4">
                                    <dt class="text-sm font-medium text-gray-500">ID заказа:</dt>
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
                                    <dt class="text-sm font-medium text-gray-500">Дата создания:</dt>
                                    <dd class="text-sm text-gray-900 col-span-2"><?php echo date('d.m.Y H:i', strtotime($orderDetails['order']['created_at'])); ?></dd>
                                </div>
                                <div class="grid grid-cols-3 gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Последнее обновление:</dt>
                                    <dd class="text-sm text-gray-900 col-span-2"><?php echo date('d.m.Y H:i', strtotime($orderDetails['order']['updated_at'])); ?></dd>
                                </div>
                                <div class="grid grid-cols-3 gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Создал:</dt>
                                    <dd class="text-sm text-gray-900 col-span-2"><?php echo htmlspecialchars($orderDetails['order']['created_by_name']); ?></dd>
                                </div>
                            </dl>
                        </div>
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-3">Информация о поставщике</h3>
                            <dl class="space-y-2">
                                <div class="grid grid-cols-3 gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Компания:</dt>
                                    <dd class="text-sm text-gray-900 col-span-2"><?php echo htmlspecialchars($orderDetails['order']['company_name']); ?></dd>
                                </div>
                                <div class="grid grid-cols-3 gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Сумма заказа:</dt>
                                    <dd class="text-sm text-gray-900 col-span-2"><?php echo number_format($orderDetails['order']['total_amount'], 2, ',', ' ') . ' ₴'; ?></dd>
                                </div>
                                
                                <!-- Действия с заказом -->
                                <?php if ($orderDetails['order']['status'] === 'pending'): ?>
                                <div class="col-span-3 mt-4 flex space-x-3">
                                    <form method="POST" action="" class="flex-1">
                                        <input type="hidden" name="order_id" value="<?php echo $orderDetails['order']['id']; ?>">
                                        <button type="submit" name="approve_order" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded">
                                            <i class="fas fa-check mr-1"></i> Подтвердить заказ
                                        </button>
                                    </form>
                                    <form method="POST" action="" class="flex-1">
                                        <input type="hidden" name="order_id" value="<?php echo $orderDetails['order']['id']; ?>">
                                        <button type="submit" name="reject_order" class="w-full bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded">
                                            <i class="fas fa-times mr-1"></i> Отклонить заказ
                                        </button>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </dl>
                        </div>
                    </div>
                    
                    <!-- Элементы заказа -->
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Элементы заказа</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Товар
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Категория
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Количество
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Цена за ед.
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Сумма
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
                                            'raw_material' => 'Сырьё',
                                            'packaging' => 'Упаковка',
                                            'finished_product' => 'Готовая продукция'
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
                                        Итого:
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                        <?php echo number_format($totalAmount, 2, ',', ' ') . ' ₴'; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Сообщение поставщику -->
                    <div class="mt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-3">Связь с поставщиком</h3>
                        <a href="messages.php?compose=1&supplier_id=<?php echo $orderDetails['order']['supplier_id']; ?>&order_id=<?php echo $orderDetails['order']['id']; ?>" 
                           class="inline-flex items-center px-4 py-2 bg-teal-600 text-white rounded-md hover:bg-teal-700">
                            <i class="fas fa-envelope mr-2"></i> Написать поставщику
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Режим списка заказов -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-semibold text-gray-800">
                            <?php
                            if ($statusFilter === 'pending') {
                                echo 'Заказы, ожидающие подтверждения';
                            } elseif ($statusFilter === 'approved') {
                                echo 'Подтвержденные заказы';
                            } elseif ($statusFilter === 'rejected') {
                                echo 'Отклоненные заказы';
                            } elseif ($statusFilter === 'received') {
                                echo 'Полученные заказы';
                            } else {
                                echo 'Активные заказы';
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
                    
                    <!-- Форма создания нового заказа -->
                    <div id="createOrderForm" class="hidden bg-gray-50 p-6 rounded-lg mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Создание нового заказа</h3>
                        <form method="POST" action="" id="newOrderForm">
                            <!-- Выбор поставщика -->
                            <div class="mb-4">
                                <label for="supplier_id" class="block text-sm font-medium text-gray-700 mb-2">Поставщик</label>
                                <select id="supplier_id" name="supplier_id" required
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 sm:text-sm">
                                    <option value="">Выберите поставщика</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['id']; ?>"><?php echo htmlspecialchars($supplier['company_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Товары для заказа -->
                            <div class="mb-4">
                                <h4 class="font-medium text-gray-900 mb-2">Товары</h4>
                                <div id="orderItems">
                                    <div class="grid grid-cols-12 gap-2 mb-2 items-center">
                                        <div class="col-span-5">
                                            <label class="block text-xs font-medium text-gray-700">Товар</label>
                                        </div>
                                        <div class="col-span-3">
                                            <label class="block text-xs font-medium text-gray-700">Количество</label>
                                        </div>
                                        <div class="col-span-3">
                                            <label class="block text-xs font-medium text-gray-700">Цена за ед. (₴)</label>
                                        </div>
                                        <div class="col-span-1">
                                            <label class="block text-xs font-medium text-gray-700">&nbsp;</label>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-12 gap-2 mb-2 items-center orderItemRow">
                                        <div class="col-span-5">
                                            <select name="product_id_0" required
                                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 sm:text-sm">
                                                <option value="">Выберите товар</option>
                                                <optgroup label="Сырьё">
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
                                            <input type="number" name="quantity_0" required min="1" placeholder="Кол-во"
                                                  class="block w-full rounded-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 sm:text-sm">
                                        </div>
                                        <div class="col-span-3">
                                            <input type="number" name="price_0" required min="0.01" step="0.01" placeholder="Цена"
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
                                        <i class="fas fa-plus mr-1"></i> Добавить товар
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mt-6 flex justify-end space-x-3">
                                <button type="button" id="cancelCreateOrder" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Отмена
                                </button>
                                <button type="submit" name="create_order" class="bg-teal-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-teal-700">
                                    Создать заказ
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Таблица заказов -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        ID
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Поставщик
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Дата
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Сумма
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
                                <?php if (empty($ordersList)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                        Заказы не найдены
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
                                                Детали
                                            </a>
                                            
                                            <?php if ($order['status'] === 'pending'): ?>
                                            <form method="POST" action="" class="inline-block mr-3">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <button type="submit" name="approve_order" class="text-green-600 hover:text-green-900">
                                                    Подтвердить
                                                </button>
                                            </form>
                                            <form method="POST" action="" class="inline-block">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <button type="submit" name="reject_order" class="text-red-600 hover:text-red-900">
                                                    Отклонить
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
            &copy; <?php echo date('Y'); ?> Винное производство. Система автоматизации процессов.
        </div>
    </footer>
    
    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Форма создания заказа
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
        
        // Счетчик для уникальных имен полей товара
        let itemCounter = 1;
        
        // Добавление товара в заказ
        function addOrderItem() {
            const orderItems = document.getElementById('orderItems');
            const template = document.querySelector('.orderItemRow').cloneNode(true);
            
            // Обновляем имена полей
            template.querySelector('select').name = 'product_id_' + itemCounter;
            template.querySelector('input[type="number"][placeholder="Кол-во"]').name = 'quantity_' + itemCounter;
            template.querySelector('input[type="number"][placeholder="Цена"]').name = 'price_' + itemCounter;
            
            // Очищаем значения
            template.querySelector('select').selectedIndex = 0;
            template.querySelector('input[type="number"][placeholder="Кол-во"]').value = '';
            template.querySelector('input[type="number"][placeholder="Цена"]').value = '';
            
            // Включаем кнопку удаления
            template.querySelector('button').disabled = false;
            
            // Увеличиваем счетчик
            itemCounter++;
            
            // Добавляем элемент в форму
            orderItems.appendChild(template);
        }
        
        // Удаление товара из заказа
        function removeOrderItem(button) {
            const row = button.closest('.orderItemRow');
            row.remove();
        }
    </script>
</body>
</html>