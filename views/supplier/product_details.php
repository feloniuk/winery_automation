<?php
// views/supplier/product_details.php
// Сторінка детальної інформації про товар для постачальника

// Підключення контролерів
require_once '../../controllers/AuthController.php';
require_once '../../controllers/SupplierController.php';
require_once '../../controllers/WarehouseController.php';

$authController = new AuthController();
$supplierController = new SupplierController();
$warehouseController = new WarehouseController();

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
    
    // Отримання кількості непрочитаних повідомлень
    $unreadMessages = $supplierController->getUnreadMessages($currentUser['id']);
    
    // Отримання ID товару з GET-параметра
    $productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($productId <= 0) {
        $error = "Помилка: невірний ідентифікатор товару.";
    } else {
        // Отримання інформації про товар
        $productDetails = $warehouseController->getProductDetails($productId);
        
        if (!$productDetails) {
            $error = "Помилка: товар не знайдено.";
        } else {
            // Отримуємо історію транзакцій по цьому товару
            $productTransactions = $warehouseController->getProductTransactions($productId, 10);
            
            // Фільтруємо транзакції, що відносяться до замовлень цього постачальника
            $supplierTransactions = array_filter($productTransactions, function($transaction) use ($supplierId) {
                if ($transaction['reference_type'] === 'order' && $transaction['reference_id'] > 0) {
                    // Перевіряємо, чи належить замовлення цьому постачальнику
                    $orderQuery = "SELECT * FROM orders WHERE id = ? AND supplier_id = ?";
                    $supplierController = new SupplierController();
                    $order = $supplierController->db->selectOne($orderQuery, [$transaction['reference_id'], $supplierId]);
                    return !empty($order);
                }
                return false;
            });
            
            // Отримуємо останню дату поставки цього товару цим постачальником
            $lastDeliveryDate = $supplierController->getProductLastDelivery($supplierId, $productId);
            
            // Отримуємо загальну кількість поставленого товару
            $totalSupplied = 0;
            foreach ($supplierTransactions as $transaction) {
                if ($transaction['transaction_type'] === 'in') {
                    $totalSupplied += $transaction['quantity'];
                }
            }
            
            // Категорії товарів
            $productCategories = [
                'raw_material' => 'Сировина',
                'packaging' => 'Упаковка',
                'finished_product' => 'Готова продукція'
            ];
        }
    }
}

// Обробка відправки форми
$message = '';

// Перевіряємо якщо метод запиту POST і була натиснута кнопка відправки пропозиції
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_offer'])) {
    // Відправка повідомлення з пропозицією щодо поставки
    $recipientQuery = "SELECT u.id FROM users u WHERE u.role = 'purchasing' LIMIT 1";
    $recipient = $supplierController->db->selectOne($recipientQuery);
    
    if ($recipient) {
        $subject = "Пропозиція щодо поставки: " . $productDetails['name'];
        $content = "Вітаю!\n\n";
        $content .= "Я хотів би запропонувати поставку товару \"" . $productDetails['name'] . "\".\n\n";
        $content .= "Кількість: " . $_POST['offer_quantity'] . " " . $productDetails['unit'] . "\n";
        $content .= "Ціна за одиницю: " . $_POST['offer_price'] . " ₴\n\n";
        $content .= "Додаткова інформація:\n" . $_POST['offer_notes'] . "\n\n";
        $content .= "З повагою,\n" . $currentUser['name'] . "\n" . $supplierInfo['company_name'];
        
        $result = $supplierController->sendMessage($currentUser['id'], $recipient['id'], $subject, $content);
        
        if ($result['success']) {
            $message = "Вашу пропозицію успішно надіслано менеджеру із закупівель!";
        } else {
            $error = "Помилка при відправленні пропозиції: " . $result['message'];
        }
    } else {
        $error = "Помилка: не знайдено менеджера із закупівель для відправлення пропозиції.";
    }
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Деталі товару - Винне виробництво</title>
    <!-- Підключення Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js для графіків -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    
    <?php if (isset($error) && $error): ?>
    <div class="container mx-auto mt-6 px-4">
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
            <p><?php echo $error; ?></p>
            <p class="mt-2">
                <a href="products.php" class="text-red-700 underline">Повернутися до списку товарів</a>
            </p>
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
                        <a href="orders.php" class="flex items-center p-2 text-gray-700 hover:bg-amber-50 rounded font-medium">
                            <i class="fas fa-shopping-cart w-5 mr-2"></i>
                            <span>Замовлення</span>
                        </a>
                    </li>
                    <li>
                        <a href="products.php" class="flex items-center p-2 bg-amber-100 text-amber-700 rounded font-medium">
                            <i class="fas fa-boxes w-5 mr-2"></i>
                            <span>Мої товари</span>
                        </a>
                    </li>
                    <li>
                        <a href="messages.php" class="flex items-center p-2 text-gray-700 hover:bg-amber-50 rounded font-medium">
                            <i class="fas fa-envelope w-5 mr-2"></i>
                            <span>Повідомлення</span>
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
                            <span>Мій профіль</span>
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Блок з інформацією про товар -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <h3 class="font-semibold text-lg mb-3">Інформація про товар</h3>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Категорія:</span>
                        <span class="font-medium"><?php echo $productCategories[$productDetails['category']] ?? $productDetails['category']; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Одиниця виміру:</span>
                        <span class="font-medium"><?php echo htmlspecialchars($productDetails['unit']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Поточний запас:</span>
                        <span class="font-medium">
                            <?php if ($productDetails['quantity'] <= $productDetails['min_stock']): ?>
                            <span class="text-red-600"><?php echo $productDetails['quantity'] . ' ' . $productDetails['unit']; ?></span>
                            <?php else: ?>
                            <span class="text-green-600"><?php echo $productDetails['quantity'] . ' ' . $productDetails['unit']; ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Мін. запас:</span>
                        <span class="font-medium"><?php echo $productDetails['min_stock'] . ' ' . $productDetails['unit']; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Всього поставлено:</span>
                        <span class="font-medium"><?php echo $totalSupplied . ' ' . $productDetails['unit']; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Остання поставка:</span>
                        <span class="font-medium">
                            <?php echo $lastDeliveryDate ? date('d.m.Y', strtotime($lastDeliveryDate)) : 'Немає даних'; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Блок з швидкими діями -->
            <div class="bg-white rounded-lg shadow-md p-4">
                <h3 class="font-semibold text-lg mb-3">Швидкі дії</h3>
                <div class="space-y-2">
                    <button id="showOfferForm" class="w-full flex justify-center items-center px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700">
                        <i class="fas fa-file-invoice-dollar mr-2"></i> Створити пропозицію
                    </button>
                    <a href="products.php" class="w-full flex justify-center items-center px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                        <i class="fas fa-arrow-left mr-2"></i> До списку товарів
                    </a>
                </div>
            </div>
        </aside>
        
        <!-- Основний вміст -->
        <main class="w-full md:w-3/4">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold text-gray-800">
                        <a href="products.php" class="text-amber-600 hover:text-amber-800 mr-2">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                        <?php echo htmlspecialchars($productDetails['name']); ?>
                    </h2>
                    <?php if ($productDetails['quantity'] <= $productDetails['min_stock']): ?>
                    <span class="px-3 py-1 text-sm font-medium rounded-full bg-red-100 text-red-800">
                        Низький запас
                    </span>
                    <?php else: ?>
                    <span class="px-3 py-1 text-sm font-medium rounded-full bg-green-100 text-green-800">
                        Достатній запас
                    </span>
                    <?php endif; ?>
                </div>
                
                <?php if ($message): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p><?php echo $message; ?></p>
                </div>
                <?php endif; ?>
                
                <?php if (isset($error) && $error && $error !== "Помилка: дані постачальника не знайдені. Зверніться до адміністратора."): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p><?php echo $error; ?></p>
                </div>
                <?php endif; ?>
                
                <!-- Форма створення пропозиції (прихована за замовчуванням) -->
                <div id="offerForm" class="hidden bg-amber-50 p-6 rounded-lg mb-6">
                    <h3 class="text-lg font-medium text-amber-800 mb-4">Створення пропозиції щодо поставки</h3>
                    <form method="POST" action="">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="offer_quantity" class="block text-sm font-medium text-gray-700 mb-1">Пропонована кількість *</label>
                                <div class="flex">
                                    <input type="number" id="offer_quantity" name="offer_quantity" required min="1" 
                                           class="block w-full rounded-l-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm">
                                    <span class="inline-flex items-center px-3 py-2 rounded-r-md border border-l-0 border-gray-300 bg-gray-50 text-gray-500 sm:text-sm">
                                        <?php echo htmlspecialchars($productDetails['unit']); ?>
                                    </span>
                                </div>
                            </div>
                            <div>
                                <label for="offer_price" class="block text-sm font-medium text-gray-700 mb-1">Ціна за одиницю *</label>
                                <div class="flex">
                                    <input type="number" id="offer_price" name="offer_price" required min="0.01" step="0.01" 
                                           class="block w-full rounded-l-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm">
                                    <span class="inline-flex items-center px-3 py-2 rounded-r-md border border-l-0 border-gray-300 bg-gray-50 text-gray-500 sm:text-sm">
                                        ₴
                                    </span>
                                </div>
                            </div>
                            <div class="md:col-span-2">
                                <label for="offer_notes" class="block text-sm font-medium text-gray-700 mb-1">Додаткова інформація</label>
                                <textarea id="offer_notes" name="offer_notes" rows="4" 
                                          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm"
                                          placeholder="Вкажіть терміни поставки, особливі умови або іншу важливу інформацію"></textarea>
                            </div>
                        </div>
                        
                        <div class="mt-4 text-sm text-amber-700">
                            <p>* Обов'язкові поля</p>
                        </div>
                        
                        <div class="mt-6 flex justify-end space-x-3">
                            <button type="button" id="cancelOfferForm" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Скасувати
                            </button>
                            <button type="submit" name="send_offer" class="bg-amber-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-amber-700">
                                Надіслати пропозицію
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Опис товару -->
                <div class="mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Опис</h3>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-gray-700">
                            <?php echo empty($productDetails['description']) ? 'Опис відсутній.' : nl2br(htmlspecialchars($productDetails['description'])); ?>
                        </p>
                    </div>
                </div>
                
                <!-- Історія поставок -->
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Історія поставок</h3>
                    <?php if (empty($supplierTransactions)): ?>
                    <div class="bg-gray-50 p-6 rounded-lg text-center">
                        <p class="text-gray-500">У вас поки немає історії поставок цього товару.</p>
                        <button id="createFirstOffer" class="mt-4 inline-flex items-center px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700">
                            <i class="fas fa-plus mr-2"></i> Створити першу пропозицію
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="overflow-x-auto bg-gray-50 rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Дата
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Тип
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Замовлення
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Кількість
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Примітка
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($supplierTransactions as $transaction): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('d.m.Y H:i', strtotime($transaction['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($transaction['transaction_type'] === 'in'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Надходження
                                        </span>
                                        <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                            Повернення
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <a href="order_details.php?id=<?php echo $transaction['reference_id']; ?>" class="text-amber-600 hover:text-amber-800">
                                            Замовлення #<?php echo $transaction['reference_id']; ?>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <?php echo $transaction['quantity'] . ' ' . $productDetails['unit']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo !empty($transaction['notes']) ? htmlspecialchars($transaction['notes']) : '-'; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Рекомендації щодо поставок -->
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Рекомендації щодо поставок</h3>
                    
                    <div class="bg-amber-50 p-4 rounded-lg">
                        <?php if ($productDetails['quantity'] <= $productDetails['min_stock']): ?>
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-amber-600 mt-0.5"></i>
                            </div>
                            <div class="ml-3">
                                <h4 class="text-sm font-medium text-amber-800">Рекомендується поставка</h4>
                                <p class="mt-1 text-sm text-amber-700">
                                    Поточний запас цього товару нижче мінімального порогу. Рекомендуємо запропонувати поставку найближчим часом.
                                    Рекомендована кількість: <?php echo max(($productDetails['min_stock'] * 2) - $productDetails['quantity'], 1) . ' ' . $productDetails['unit']; ?>
                                </p>
                                <div class="mt-2">
                                    <button id="recommendedOffer" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-amber-700 bg-amber-100 hover:bg-amber-200">
                                        Створити рекомендовану пропозицію
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <i class="fas fa-info-circle text-amber-600 mt-0.5"></i>
                            </div>
                            <div class="ml-3">
                                <h4 class="text-sm font-medium text-amber-800">Достатній запас</h4>
                                <p class="mt-1 text-sm text-amber-700">
                                    Поточний запас цього товару вище мінімального порогу. Поставка не потрібна на даний момент, але ви завжди
                                    можете запропонувати вигідні умови для майбутніх поставок.
                                </p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Графік останніх поставок -->
                <?php if (!empty($supplierTransactions)): ?>
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Графік поставок</h3>
                    <div class="bg-white p-4 rounded-lg border border-gray-200">
                        <canvas id="deliveryChart" width="400" height="200"></canvas>
                    </div>
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
    
    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Форма створення пропозиції
            const showOfferFormBtn = document.getElementById('showOfferForm');
            const cancelOfferFormBtn = document.getElementById('cancelOfferForm');
            const offerForm = document.getElementById('offerForm');
            
            if (showOfferFormBtn && cancelOfferFormBtn && offerForm) {
                showOfferFormBtn.addEventListener('click', function() {
                    offerForm.classList.remove('hidden');
                });
                
                cancelOfferFormBtn.addEventListener('click', function() {
                    offerForm.classList.add('hidden');
                });
            }
            
            // Кнопка "Створити першу пропозицію"
            const createFirstOfferBtn = document.getElementById('createFirstOffer');
            if (createFirstOfferBtn && offerForm) {
                createFirstOfferBtn.addEventListener('click', function() {
                    offerForm.classList.remove('hidden');
                });
            }
            
            // Кнопка "Створити рекомендовану пропозицію"
            const recommendedOfferBtn = document.getElementById('recommendedOffer');
            if (recommendedOfferBtn && offerForm) {
                recommendedOfferBtn.addEventListener('click', function() {
                    const offerQuantityInput = document.getElementById('offer_quantity');
                    if (offerQuantityInput) {
                        <?php $recommendedQuantity = max(($productDetails['min_stock'] * 2) - $productDetails['quantity'], 1); ?>
                        offerQuantityInput.value = <?php echo $recommendedQuantity; ?>;
                    }
                    offerForm.classList.remove('hidden');
                });
            }
            
            // Графік поставок
            <?php if (!empty($supplierTransactions)): ?>
            const ctx = document.getElementById('deliveryChart').getContext('2d');
            
            // Підготовка даних для графіка
            const chartData = {
                labels: [
                    <?php 
                    // Сортуємо транзакції за датою
                    usort($supplierTransactions, function($a, $b) {
                        return strtotime($a['created_at']) - strtotime($b['created_at']);
                    });
                    
                    // Максимум 10 останніх транзакцій
                    $displayTransactions = array_slice($supplierTransactions, -10);
                    
                    // Виводимо дати
                    foreach ($displayTransactions as $index => $transaction) {
                        echo "'" . date('d.m.Y', strtotime($transaction['created_at'])) . "'";
                        if ($index < count($displayTransactions) - 1) echo ", ";
                    }
                    ?>
                ],
                datasets: [{
                    label: 'Кількість поставленого товару',
                    data: [
                        <?php 
                        foreach ($displayTransactions as $index => $transaction) {
                            echo $transaction['transaction_type'] === 'in' ? $transaction['quantity'] : -$transaction['quantity'];
                            if ($index < count($displayTransactions) - 1) echo ", ";
                        }
                        ?>
                    ],
                    backgroundColor: 'rgba(217, 119, 6, 0.2)',
                    borderColor: 'rgba(217, 119, 6, 1)',
                    borderWidth: 2,
                    tension: 0.3
                }]
            };
            
            const deliveryChart = new Chart(ctx, {
                type: 'line',
                data: chartData,
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: false,
                            title: {
                                display: true,
                                text: 'Кількість (<?php echo htmlspecialchars($productDetails['unit']); ?>)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Дата поставки'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.raw + ' <?php echo htmlspecialchars($productDetails['unit']); ?>';
                                }
                            }
                        }
                    }
                }
            });
            <?php endif; ?>
        });
    </script>
    <?php endif; ?>
</body>
</html>