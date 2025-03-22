<?php
// views/supplier/products.php
// Страница для просмотра и управления товарами поставщика

// Подключение контроллеров
require_once '../../controllers/AuthController.php';
require_once '../../controllers/SupplierController.php';

$authController = new AuthController();
$supplierController = new SupplierController();

// Проверка авторизации и роли
if (!$authController->isLoggedIn() || !$authController->checkRole('supplier')) {
    header('Location: ../../index.php');
    exit;
}

// Получение данных пользователя
$currentUser = $authController->getCurrentUser();
$supplierId = $supplierController->getSupplierIdByUserId($currentUser['id']);

if (!$supplierId) {
    // Если данные поставщика не найдены, отображаем ошибку
    $error = "Ошибка: данные поставщика не найдены. Обратитесь к администратору.";
} else {
    // Получаем информацию о поставщике
    $supplierInfo = $supplierController->getSupplierInfo($supplierId);
    
    // Получаем список товаров поставщика
    $supplierProducts = $supplierController->getSupplierProducts($supplierId);
    
    // Категории товаров для отображения
    $categories = [
        'raw_material' => 'Сырьё',
        'packaging' => 'Упаковка',
        'finished_product' => 'Готовая продукция'
    ];
    
    // Фильтрация товаров по категории
    $categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';
    if ($categoryFilter) {
        $supplierProducts = array_filter($supplierProducts, function($product) use ($categoryFilter) {
            return $product['category'] === $categoryFilter;
        });
    }
    
    // Поиск по названию товара
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    if ($searchTerm) {
        $supplierProducts = array_filter($supplierProducts, function($product) use ($searchTerm) {
            return stripos($product['name'], $searchTerm) !== false;
        });
    }
    
    // Общая статистика по товарам
    $totalProducts = count($supplierController->getSupplierProducts($supplierId));
    $totalSupplied = array_sum(array_map(function($product) {
        return $product['total_supplied'] ?? 0;
    }, $supplierController->getSupplierProducts($supplierId)));
    
    // Получение количества непрочитанных сообщений
    $unreadMessages = $supplierController->getUnreadMessages($currentUser['id']);
}

// Обработка действий
$message = '';
$error = '';

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мои товары - Винное производство</title>
    <!-- Подключение Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js для графиков -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Иконки -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Верхняя навигационная панель -->
    <nav class="bg-amber-800 text-white p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center">
                <i class="fas fa-wine-bottle text-2xl mr-3"></i>
                <h1 class="text-xl font-bold">Винное производство</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span><?php echo htmlspecialchars($currentUser['name']); ?> (Поставщик)</span>
                <a href="../../controllers/logout.php" class="bg-amber-700 hover:bg-amber-600 py-2 px-4 rounded text-sm">
                    <i class="fas fa-sign-out-alt mr-1"></i> Выйти
                </a>
            </div>
        </div>
    </nav>
    
    <?php if (isset($error) && $error === "Ошибка: данные поставщика не найдены. Обратитесь к администратору."): ?>
    <div class="container mx-auto mt-6 px-4">
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
            <p><?php echo $error; ?></p>
        </div>
    </div>
    <?php else: ?>
    
    <!-- Боковая панель и основной контент -->
    <div class="container mx-auto flex flex-wrap mt-6 px-4">
        <!-- Боковая навигация -->
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
                            <span>Панель управления</span>
                        </a>
                    </li>
                    <li>
                        <a href="orders.php" class="flex items-center p-2 text-gray-700 hover:bg-amber-50 rounded font-medium">
                            <i class="fas fa-shopping-cart w-5 mr-2"></i>
                            <span>Заказы</span>
                        </a>
                    </li>
                    <li>
                        <a href="products.php" class="flex items-center p-2 bg-amber-100 text-amber-700 rounded font-medium">
                            <i class="fas fa-boxes w-5 mr-2"></i>
                            <span>Мои товары</span>
                        </a>
                    </li>
                    <li>
                        <a href="messages.php" class="flex items-center p-2 text-gray-700 hover:bg-amber-50 rounded font-medium">
                            <i class="fas fa-envelope w-5 mr-2"></i>
                            <span>Сообщения</span>
                            <?php if (count($unreadMessages) > 0): ?>
                            <span class="ml-auto bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                <?php echo count($unreadMessages); ?>
                            </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="profile.php" class="flex items-center p-2 text-gray-700 hover:bg-amber-50 rounded font-medium">
                            <i class="fas fa-user-cog w-5 mr-2"></i>
                            <span>Мой профиль</span>
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Блок статистики товаров -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <h3 class="font-semibold text-lg mb-3">Статистика товаров</h3>
                <ul class="space-y-3">
                    <li class="flex justify-between">
                        <span class="text-gray-600">Всего товаров:</span>
                        <span class="font-semibold"><?php echo $totalProducts; ?></span>
                    </li>
                    <li class="flex justify-between">
                        <span class="text-gray-600">Всего поставлено:</span>
                        <span class="font-semibold"><?php echo $totalSupplied; ?> ед.</span>
                    </li>
                    <li class="border-t border-gray-200 pt-3">
                        <div class="grid grid-cols-2 gap-2">
                            <?php foreach ($categories as $code => $name): ?>
                            <?php 
                            $categoryCount = count(array_filter($supplierController->getSupplierProducts($supplierId), function($product) use ($code) {
                                return $product['category'] === $code;
                            }));
                            ?>
                            <div class="flex justify-between items-center">
                                <a href="?category=<?php echo $code; ?>" class="text-gray-600 hover:text-amber-700">
                                    <?php echo $name; ?>:
                                </a>
                                <span class="font-semibold"><?php echo $categoryCount; ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </li>
                </ul>
            </div>
            
            <!-- Полезные ссылки -->
            <div class="bg-white rounded-lg shadow-md p-4">
                <h3 class="font-semibold text-lg mb-3">Полезные ссылки</h3>
                <ul class="space-y-2">
                    <li>
                        <a href="orders.php?status=pending" class="text-amber-600 hover:text-amber-800 block py-1">
                            <i class="fas fa-clock mr-1"></i> Ожидающие заказы
                        </a>
                    </li>
                    <li>
                        <a href="messages.php?compose=1" class="text-amber-600 hover:text-amber-800 block py-1">
                            <i class="fas fa-envelope mr-1"></i> Написать сообщение
                        </a>
                    </li>
                    <li>
                        <a href="profile.php" class="text-amber-600 hover:text-amber-800 block py-1">
                            <i class="fas fa-user-edit mr-1"></i> Обновить профиль
                        </a>
                    </li>
                </ul>
            </div>
        </aside>
        
        <!-- Основной контент -->
        <main class="w-full md:w-3/4">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold text-gray-800">Мои товары</h2>
                </div>
                
                <?php if ($message): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p><?php echo $message; ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($error && $error !== "Ошибка: данные поставщика не найдены. Обратитесь к администратору."): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p><?php echo $error; ?></p>
                </div>
                <?php endif; ?>
                
                <!-- Фильтры и поиск -->
                <div class="mb-6 flex flex-wrap justify-between items-center">
                    <div class="flex space-x-2 mb-3 md:mb-0">
                        <a href="products.php" class="<?php echo empty($categoryFilter) ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-800'; ?> px-3 py-1 rounded-full text-xs">
                            Все категории
                        </a>
                        <?php foreach ($categories as $code => $name): ?>
                        <a href="?category=<?php echo $code; ?>" class="<?php echo $categoryFilter === $code ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-800'; ?> px-3 py-1 rounded-full text-xs">
                            <?php echo $name; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    
                    <form method="GET" action="" class="flex">
                        <?php if ($categoryFilter): ?>
                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($categoryFilter); ?>">
                        <?php endif; ?>
                        <div class="relative flex-grow max-w-xs">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" 
                                  placeholder="Поиск товаров..." 
                                  class="block w-full rounded-l-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm pl-10">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                        </div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-r-md shadow-sm text-white bg-amber-600 hover:bg-amber-700">
                            Поиск
                        </button>
                        <?php if ($searchTerm): ?>
                        <a href="<?php echo $categoryFilter ? "?category=$categoryFilter" : "products.php"; ?>" class="ml-2 inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            <i class="fas fa-times mr-1"></i> Сбросить
                        </a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <!-- График поставок по категориям -->
                <div class="mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Распределение поставок по категориям</h3>
                    <div class="h-64">
                        <canvas id="categoriesChart"></canvas>
                    </div>
                </div>
                
                <!-- Таблица товаров -->
                <h3 class="text-lg font-medium text-gray-900 mb-4">Список поставляемых товаров</h3>
                <?php if (empty($supplierProducts)): ?>
                <div class="text-center py-6 bg-gray-50 rounded-lg">
                    <p class="text-gray-500">У вас пока нет поставляемых товаров</p>
                    <p class="text-sm text-gray-400 mt-2">Когда вы начнете поставлять товары, они появятся здесь</p>
                </div>
                <?php else: ?>
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
                                    Единица измерения
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Всего поставлено
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Последняя поставка
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Действия
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($supplierProducts as $product): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $categories[$product['category']] ?? $product['category']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($product['unit']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $product['total_supplied'] ?? 0; ?> <?php echo htmlspecialchars($product['unit']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php 
                                    $lastDelivery = $supplierController->getProductLastDelivery($supplierId, $product['id']);
                                    echo $lastDelivery ? date('d.m.Y', strtotime($lastDelivery)) : 'Нет данных';
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="product_details.php?id=<?php echo $product['id']; ?>" class="text-amber-600 hover:text-amber-900">
                                        Подробнее
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Топ-5 самых поставляемых товаров -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Топ поставляемых товаров</h2>
                
                <?php
                // Сортируем товары по количеству поставок
                usort($supplierProducts, function($a, $b) {
                    return ($b['total_supplied'] ?? 0) - ($a['total_supplied'] ?? 0);
                });
                
                // Берем первые 5 товаров
                $topProducts = array_slice($supplierProducts, 0, 5);
                ?>
                
                <?php if (empty($topProducts)): ?>
                <div class="text-center py-6 bg-gray-50 rounded-lg">
                    <p class="text-gray-500">Нет данных о поставляемых товарах</p>
                </div>
                <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($topProducts as $index => $product): ?>
                    <?php 
                    $percentage = !empty($supplierProducts) && isset($product['total_supplied']) && $supplierProducts[0]['total_supplied'] > 0
                        ? round(($product['total_supplied'] / $supplierProducts[0]['total_supplied']) * 100)
                        : 0;
                    ?>
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <div class="flex items-center">
                                <span class="w-6 h-6 flex items-center justify-center bg-amber-100 text-amber-800 rounded-full text-xs font-medium mr-2">
                                    <?php echo $index + 1; ?>
                                </span>
                                <span class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </span>
                            </div>
                            <span class="text-sm text-gray-500">
                                <?php echo $product['total_supplied'] ?? 0; ?> <?php echo htmlspecialchars($product['unit']); ?>
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-amber-600 h-2 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="mt-6 bg-amber-50 p-4 rounded-lg">
                    <h3 class="text-sm font-medium text-amber-900 mb-2">Совет по улучшению поставок:</h3>
                    <p class="text-sm text-amber-800">
                        Обратите внимание на товары, которые пользуются наибольшим спросом. Регулярное обновление запасов этих товаров поможет укрепить ваши партнерские отношения с винным производством.
                    </p>
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
    
    <!-- JavaScript для графиков -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Группировка товаров по категориям для графика
            const categoryCounts = {
                'raw_material': 0,
                'packaging': 0,
                'finished_product': 0
            };
            
            const categoryLabels = {
                'raw_material': 'Сырьё',
                'packaging': 'Упаковка',
                'finished_product': 'Готовая продукция'
            };
            
            <?php foreach ($supplierProducts as $product): ?>
            <?php if (isset($product['category']) && isset($product['total_supplied'])): ?>
            categoryCounts['<?php echo $product['category']; ?>'] += <?php echo $product['total_supplied'] ?? 0; ?>;
            <?php endif; ?>
            <?php endforeach; ?>
            
            // Создание графика распределения по категориям
            var ctx = document.getElementById('categoriesChart').getContext('2d');
            var categoriesChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: Object.keys(categoryCounts).map(key => categoryLabels[key]),
                    datasets: [{
                        data: Object.values(categoryCounts),
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(54, 162, 235, 0.7)',
                            'rgba(255, 206, 86, 0.7)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 206, 86, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var label = context.label || '';
                                    var value = context.raw || 0;
                                    var total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    var percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return label + ': ' + value + ' ед. (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>
