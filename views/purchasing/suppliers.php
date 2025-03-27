<?php
// views/purchasing/suppliers.php
// Сторінка для роботи з постачальниками для менеджера із закупівель

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

// Обробка дій
$message = '';
$error = '';

// Отримання даних про конкретного постачальника, якщо вказано ID
$supplierDetails = null;
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $supplierId = $_GET['id'];
    $supplierDetails = $purchasingController->getSupplierById($supplierId);
    
    // Отримання останніх замовлень постачальника
    $recentOrders = $purchasingController->getSupplierRecentOrders($supplierId, 5);
}

// Дані для фільтрації постачальників
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
if ($searchTerm) {
    $suppliers = array_filter($suppliers, function($supplier) use ($searchTerm) {
        return (stripos($supplier['company_name'], $searchTerm) !== false) || 
               (stripos($supplier['contact_person'], $searchTerm) !== false) || 
               (stripos($supplier['phone'], $searchTerm) !== false);
    });
}

// Статистичні дані
$totalSuppliers = count($purchasingController->getActiveSuppliers());
$totalOrders = $purchasingController->getTotalOrdersCount();
$avgResponseTime = $purchasingController->getAverageResponseTime(); // в годинах
$topSuppliers = $purchasingController->getTopSuppliers(5);

?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Постачальники - Винне виробництво</title>
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
                        <a href="suppliers.php" class="flex items-center p-2 bg-teal-100 text-teal-700 rounded font-medium">
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
            
            <!-- Блок статистики -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <h3 class="font-semibold text-lg mb-3">Статистика постачальників</h3>
                <ul class="space-y-3">
                    <li class="flex justify-between">
                        <span class="text-gray-600">Всього постачальників:</span>
                        <span class="font-semibold"><?php echo $totalSuppliers; ?></span>
                    </li>
                    <li class="flex justify-between">
                        <span class="text-gray-600">Всього замовлень:</span>
                        <span class="font-semibold"><?php echo $totalOrders; ?></span>
                    </li>
                    <li class="flex justify-between">
                        <span class="text-gray-600">Середній час відповіді:</span>
                        <span class="font-semibold"><?php echo $avgResponseTime; ?> год.</span>
                    </li>
                </ul>
                
                <?php if (!empty($topSuppliers)): ?>
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <h4 class="font-medium text-gray-700 mb-2">Топ постачальників</h4>
                    <ul class="space-y-2">
                        <?php foreach ($topSuppliers as $index => $supplier): ?>
                        <li class="flex justify-between items-center">
                            <div class="flex items-center">
                                <span class="text-xs bg-gray-200 rounded-full w-5 h-5 flex items-center justify-center mr-2">
                                    <?php echo $index + 1; ?>
                                </span>
                                <span class="text-sm truncate"><?php echo htmlspecialchars($supplier['company_name']); ?></span>
                            </div>
                            <span class="text-xs text-gray-500"><?php echo $supplier['order_count']; ?> замовлень</span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </aside>
        
        <!-- Основний контент -->
        <main class="w-full md:w-3/4">
            <?php if (isset($supplierDetails)): ?>
            <!-- Режим перегляду деталей постачальника -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold text-gray-800">
                        <a href="suppliers.php" class="text-teal-600 hover:text-teal-800 mr-2">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                        Інформація про постачальника
                    </h2>
                </div>
                
                <!-- Інформація про постачальника -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-3">Основна інформація</h3>
                        <dl class="space-y-2">
                            <div class="grid grid-cols-3 gap-4">
                                <dt class="text-sm font-medium text-gray-500">Компанія:</dt>
                                <dd class="text-sm text-gray-900 col-span-2"><?php echo htmlspecialchars($supplierDetails['company_name']); ?></dd>
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <dt class="text-sm font-medium text-gray-500">Контактна особа:</dt>
                                <dd class="text-sm text-gray-900 col-span-2"><?php echo htmlspecialchars($supplierDetails['contact_person']); ?></dd>
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <dt class="text-sm font-medium text-gray-500">Телефон:</dt>
                                <dd class="text-sm text-gray-900 col-span-2"><?php echo htmlspecialchars($supplierDetails['phone']); ?></dd>
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <dt class="text-sm font-medium text-gray-500">Email:</dt>
                                <dd class="text-sm text-gray-900 col-span-2"><?php echo htmlspecialchars($supplierDetails['email']); ?></dd>
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <dt class="text-sm font-medium text-gray-500">Адреса:</dt>
                                <dd class="text-sm text-gray-900 col-span-2"><?php echo htmlspecialchars($supplierDetails['address']); ?></dd>
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <dt class="text-sm font-medium text-gray-500">Статус:</dt>
                                <dd class="text-sm col-span-2">
                                    <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                        Активний
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-3">Додаткова інформація</h3>
                        <dl class="space-y-2">
                            <div class="grid grid-cols-3 gap-4">
                                <dt class="text-sm font-medium text-gray-500">Ім'я користувача:</dt>
                                <dd class="text-sm text-gray-900 col-span-2"><?php echo htmlspecialchars($supplierDetails['name']); ?></dd>
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <dt class="text-sm font-medium text-gray-500">Дата реєстрації:</dt>
                                <dd class="text-sm text-gray-900 col-span-2"><?php echo date('d.m.Y', strtotime($supplierDetails['created_at'])); ?></dd>
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <dt class="text-sm font-medium text-gray-500">Всього замовлень:</dt>
                                <dd class="text-sm text-gray-900 col-span-2"><?php echo $purchasingController->getSupplierOrdersCount($supplierDetails['id']); ?></dd>
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <dt class="text-sm font-medium text-gray-500">Остання активність:</dt>
                                <dd class="text-sm text-gray-900 col-span-2">
                                    <?php 
                                    $lastActivity = $purchasingController->getSupplierLastActivity($supplierDetails['id']);
                                    echo $lastActivity ? date('d.m.Y H:i', strtotime($lastActivity)) : 'Немає даних'; 
                                    ?>
                                </dd>
                            </div>
                        </dl>
                        <div class="mt-4 flex space-x-2">
                            <a href="orders.php?create=1&supplier_id=<?php echo $supplierDetails['id']; ?>" class="inline-flex items-center px-4 py-2 bg-teal-600 text-white rounded-md hover:bg-teal-700">
                                <i class="fas fa-plus mr-2"></i> Створити замовлення
                            </a>
                            <a href="messages.php?compose=1&supplier_id=<?php echo $supplierDetails['id']; ?>" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                <i class="fas fa-envelope mr-2"></i> Написати повідомлення
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Останні замовлення постачальника -->
                <h3 class="text-lg font-medium text-gray-900 mb-3">Останні замовлення</h3>
                <?php if (empty($recentOrders)): ?>
                <p class="text-gray-500 text-center py-6">У цього постачальника ще немає замовлень</p>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    ID
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
                            <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo $order['id']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('d.m.Y', strtotime($order['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo number_format($order['total_amount'], 2, ',', ' '); ?> ₴
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                        $statusClasses = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'approved' => 'bg-green-100 text-green-800',
                                            'rejected' => 'bg-red-100 text-red-800',
                                            'received' => 'bg-blue-100 text-blue-800'
                                        ];
                                        $statusNames = [
                                            'pending' => 'Очікує підтвердження',
                                            'approved' => 'Підтверджено',
                                            'rejected' => 'Відхилено',
                                            'received' => 'Отримано'
                                        ];
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClasses[$order['status']]; ?>">
                                        <?php echo $statusNames[$order['status']]; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="orders.php?view=<?php echo $order['id']; ?>" class="text-teal-600 hover:text-teal-900">
                                        Деталі
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 text-right">
                    <a href="orders.php?supplier_id=<?php echo $supplierDetails['id']; ?>" class="text-sm text-teal-600 hover:text-teal-800">
                        Всі замовлення постачальника <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <!-- Режим списку постачальників -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold text-gray-800">Постачальники</h2>
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
                
                <!-- Форма пошуку -->
                <div class="mb-6">
                    <form method="GET" action="" class="flex">
                        <div class="relative flex-grow">
                            <input type="text" name="search" placeholder="Пошук постачальників..." value="<?php echo htmlspecialchars($searchTerm); ?>"
                                  class="block w-full rounded-l-md border-gray-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 sm:text-sm pl-10">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                        </div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-r-md shadow-sm text-white bg-teal-600 hover:bg-teal-700">
                            Пошук
                        </button>
                        <?php if ($searchTerm): ?>
                        <a href="suppliers.php" class="ml-2 inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            <i class="fas fa-times mr-1"></i> Скинути
                        </a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <!-- Список постачальників -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if (empty($suppliers)): ?>
                    <div class="col-span-3 text-center py-6">
                        <p class="text-gray-500">Постачальники не знайдені</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($suppliers as $supplier): ?>
                        <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden hover:shadow-md transition-shadow duration-200">
                            <div class="p-5">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900 mb-1"><?php echo htmlspecialchars($supplier['company_name']); ?></h3>
                                        <p class="text-sm text-gray-500 mb-2"><?php echo htmlspecialchars($supplier['contact_person']); ?></p>
                                    </div>
                                    <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                        Активний
                                    </span>
                                </div>
                                <div class="space-y-1 mt-3">
                                    <div class="flex items-center text-sm text-gray-600">
                                        <i class="fas fa-phone-alt w-4 mr-2 text-gray-400"></i>
                                        <span><?php echo htmlspecialchars($supplier['phone']); ?></span>
                                    </div>
                                    <div class="flex items-center text-sm text-gray-600">
                                        <i class="fas fa-envelope w-4 mr-2 text-gray-400"></i>
                                        <span><?php echo htmlspecialchars($supplier['email']); ?></span>
                                    </div>
                                    <div class="flex items-start text-sm text-gray-600">
                                        <i class="fas fa-map-marker-alt w-4 mr-2 text-gray-400 mt-1"></i>
                                        <span><?php echo htmlspecialchars($supplier['address']); ?></span>
                                    </div>
                                </div>
                                <div class="mt-4 flex justify-between items-center border-t border-gray-100 pt-3">
                                    <div class="text-xs text-gray-500">
                                        З нами з <?php echo date('d.m.Y', strtotime($supplier['created_at'])); ?>
                                    </div>
                                    <div class="flex space-x-2">
                                        <a href="suppliers.php?id=<?php echo $supplier['id']; ?>" class="text-teal-600 hover:text-teal-900 text-sm">
                                            <i class="fas fa-info-circle mr-1"></i> Детальніше
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
</body>
</html>