<?php
// views/warehouse/issue.php
// Сторінка для видачі товарів зі складу

// Підключення контролерів
require_once '../../controllers/AuthController.php';
require_once '../../controllers/WarehouseController.php';

$authController = new AuthController();
$warehouseController = new WarehouseController();

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
$selectedCategory = isset($_GET['category']) ? $_GET['category'] : 'all';
if ($selectedCategory !== 'all') {
    $products = $warehouseController->getProductsByCategory($selectedCategory);
} else {
    $products = $warehouseController->getInventorySummary();
}

// Обробка форми видачі товару
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['issue_items'])) {
        $success = true;
        $transactionResults = [];
        
        // Обробка кожного товару з форми
        foreach ($_POST['product_id'] as $index => $productId) {
            if (!empty($productId) && isset($_POST['quantity'][$index]) && $_POST['quantity'][$index] > 0) {
                $quantity = (int)$_POST['quantity'][$index];
                $notes = $_POST['notes'][$index] ?? '';
                $department = $_POST['department'][$index] ?? '';
                
                // Формуємо примітку з вказанням відділу
                $fullNotes = "Видано у відділ: " . $department . ". " . $notes;
                
                // Створюємо транзакцію (видаток товару)
                $result = $warehouseController->addTransaction(
                    $productId,
                    $quantity,
                    'out', // тип - видаток
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
            $message = "Товари успішно видано зі складу";
        } else {
            $error = "Виникли помилки при видачі деяких товарів";
        }
        
        // Оновлюємо список товарів
        if ($selectedCategory !== 'all') {
            $products = $warehouseController->getProductsByCategory($selectedCategory);
        } else {
            $products = $warehouseController->getInventorySummary();
        }
    }
}

// Відділи для випадаючого списку
$departments = [
    'production' => 'Виробництво',
    'bottling' => 'Розлив',
    'packaging' => 'Пакування',
    'laboratory' => 'Лабораторія',
    'marketing' => 'Маркетинг',
    'administration' => 'Адміністрація',
    'other' => 'Інше'
];
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Видача товарів - Винне виробництво</title>
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
                        <a href="receive.php" class="flex items-center p-2 text-gray-700 hover:bg-purple-50 rounded font-medium">
                            <i class="fas fa-truck-loading w-5 mr-2"></i>
                            <span>Прийом товарів</span>
                        </a>
                    </li>
                    <li>
                        <a href="issue.php" class="flex items-center p-2 bg-purple-100 text-purple-700 rounded font-medium">
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
            
            <!-- Блок категорій -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <h3 class="font-semibold text-lg mb-3">Категорії</h3>
                <ul class="space-y-1">
                    <li>
                        <a href="?category=all" class="<?php echo $selectedCategory === 'all' ? 'text-purple-600 font-medium' : 'text-gray-600'; ?> hover:text-purple-800 block py-1">
                            Всі категорії
                        </a>
                    </li>
                    <?php foreach ($categories as $code => $name): ?>
                    <li>
                        <a href="?category=<?php echo $code; ?>" class="<?php echo $selectedCategory === $code ? 'text-purple-600 font-medium' : 'text-gray-600'; ?> hover:text-purple-800 block py-1">
                            <?php echo $name; ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <!-- Блок з інструкціями -->
            <div class="bg-white rounded-lg shadow-md p-4">
                <h3 class="font-semibold text-lg mb-3">Інструкції</h3>
                <div class="text-sm text-gray-600 space-y-2">
                    <p>1. Виберіть категорію товарів.</p>
                    <p>2. Заповніть форму видачі, вказавши товар, кількість і відділ-отримувач.</p>
                    <p>3. За необхідності додайте примітки.</p>
                    <p>4. Натисніть "Видати товари" для завершення процесу.</p>
                    <div class="mt-4 p-2 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800">
                        <p class="font-medium">Примітка:</p>
                        <p>Переконайтеся, що на складі достатньо товару перед видачею.</p>
                    </div>
                </div>
            </div>
        </aside>
        
        <!-- Основний контент -->
        <main class="w-full md:w-3/4">
            <!-- Форма видачі товарів -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold text-gray-800">Видача товарів зі складу</h2>
                </div>
                
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
                
                <form method="POST" action="" id="issueForm">
                    <div class="mb-6 p-6 bg-gray-50 rounded-lg">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Форма видачі товарів</h3>
                        
                        <div id="issueItems">
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
                                        <label class="block text-xs font-medium text-gray-700">Відділ-отримувач</label>
                                    </div>
                                    <div class="col-span-1">
                                        <label class="block text-xs text-transparent">Дія</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="issueItemRow space-y-4">
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
                                        <select name="department[]" required
                                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                                            <option value="">Виберіть відділ</option>
                                            <?php foreach ($departments as $code => $name): ?>
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
                            <button type="submit" name="issue_items" class="bg-purple-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-purple-700">
                                <i class="fas fa-dolly mr-1"></i> Видати товари
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Таблиця доступних товарів -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Доступні товари на складі</h2>
                
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
                                    Поточний запас
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Мін. запас
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Статус
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Дія
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                    Товари не знайдено
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $categories[$product['category']] ?? $product['category']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $product['quantity'] . ' ' . $product['unit']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $product['min_stock'] . ' ' . $product['unit']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($product['quantity'] <= $product['min_stock']): ?>
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
                                        <button type="button" class="text-purple-600 hover:text-purple-900" 
                                                onclick="selectProduct(<?php echo $product['id']; ?>)">
                                            Видати
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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
            // Ініціалізація обробників подій для першого рядка
            initializeProductSelect(document.querySelector('.product-select'));
            
            // Кнопка додавання товару
            document.getElementById('addItemButton').addEventListener('click', addItemRow);
        });
        
        // Лічильник для унікальних ідентифікаторів рядків
        let rowCounter = 1;
        
        // Додавання нового рядка товару
        function addItemRow() {
            const itemsContainer = document.getElementById('issueItems');
            const template = document.querySelector('.issueItemRow').cloneNode(true);
            
            // Очищаємо значення полів
            const productSelect = template.querySelector('.product-select');
            productSelect.selectedIndex = 0;
            
            template.querySelector('input[name="quantity[]"]').value = '';
            template.querySelector('select[name="department[]"]').selectedIndex = 0;
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
            const row = button.closest('.issueItemRow');
            row.remove();
        }
        
        // Ініціалізація селекта товару
        function initializeProductSelect(select) {
            select.addEventListener('change', function() {
                const row = this.closest('.issueItemRow');
                const option = this.options[this.selectedIndex];
                
                if (option.value) {
                    const stock = option.getAttribute('data-stock');
                    const unit = option.getAttribute('data-unit');
                    
                    row.querySelector('.current-stock').textContent = stock + ' ' + unit;
                    row.querySelector('.unit').textContent = unit;
                    row.querySelector('input[name="quantity[]"]').max = stock;
                } else {
                    row.querySelector('.current-stock').textContent = '-';
                    row.querySelector('.unit').textContent = '';
                    row.querySelector('input[name="quantity[]"]').removeAttribute('max');
                }
            });
        }
        
        // Вибір товару з таблиці
        function selectProduct(productId) {
            // Якщо є рядки з порожнім товаром, використовуємо перший з них
            const emptyRows = Array.from(document.querySelectorAll('.product-select')).filter(select => !select.value);
            
            if (emptyRows.length > 0) {
                const select = emptyRows[0];
                select.value = productId;
                // Емулюємо подію change для оновлення UI
                const event = new Event('change', { bubbles: true });
                select.dispatchEvent(event);
                
                // Прокручуємо до форми
                document.getElementById('issueForm').scrollIntoView({ behavior: 'smooth' });
            } else {
                // Якщо немає порожніх рядків, додаємо новий
                addItemRow();
                
                // Знаходимо щойно доданий рядок і вибираємо товар
                const newSelect = document.querySelector('.issueItemRow:last-child .product-select');
                newSelect.value = productId;
                
                // Емулюємо подію change для оновлення UI
                const event = new Event('change', { bubbles: true });
                newSelect.dispatchEvent(event);
                
                // Прокручуємо до форми
                document.getElementById('issueForm').scrollIntoView({ behavior: 'smooth' });
            }
        }
    </script>
</body>
</html>