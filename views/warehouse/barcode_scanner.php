<?php
// views/warehouse/barcode_scanner.php
// Сторінка для сканування штрих-кодів при інвентаризації

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
$products = $warehouseController->getInventorySummary();

// Обробка AJAX запиту для отримання інформації про товар за ID
if (isset($_GET['ajax']) && isset($_GET['product_id'])) {
    $productId = $_GET['product_id'];
    $product = $warehouseController->getProductDetails($productId);
    
    header('Content-Type: application/json');
    if ($product) {
        echo json_encode([
            'success' => true,
            'product' => $product
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Товар не знайдено'
        ]);
    }
    exit;
}

// Обробка AJAX запиту для оновлення кількості товару
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    $productId = $_POST['product_id'] ?? '';
    $newQuantity = (int)($_POST['quantity'] ?? 0);
    $notes = 'Інвентаризація зі сканером штрих-коду';
    
    if ($productId && $newQuantity >= 0) {
        // Отримуємо поточну кількість товару
        $product = $warehouseController->getProductDetails($productId);
        
        if ($product) {
            $currentQuantity = $product['quantity'];
            $difference = $newQuantity - $currentQuantity;
            
            if ($difference != 0) {
                // Створюємо транзакцію коригування
                $transactionType = $difference > 0 ? 'in' : 'out';
                $transactionQuantity = abs($difference);
                
                $result = $warehouseController->addTransaction(
                    $productId,
                    $transactionQuantity,
                    $transactionType,
                    0,
                    'adjustment',
                    $notes,
                    $currentUser['id']
                );
                
                header('Content-Type: application/json');
                echo json_encode($result);
            } else {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Кількість не змінилась'
                ]);
            }
        } else {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Товар не знайдено'
            ]);
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Невірні дані'
        ]);
    }
    exit;
}

?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Сканер штрих-кодів - Винне виробництво</title>
    <!-- Підключення Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Іконки -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- QuaggaJS для сканування штрих-кодів -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js"></script>
    <style>
        #scanner-container {
            position: relative;
            width: 100%;
            max-width: 640px;
            margin: 0 auto;
        }
        
        #scanner-container video {
            width: 100%;
            height: auto;
        }
        
        #scanner-container canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        
        .scanner-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }
        
        .scan-line {
            position: absolute;
            left: 10%;
            right: 10%;
            height: 2px;
            background: #00ff00;
            animation: scan 2s linear infinite;
            box-shadow: 0 0 5px #00ff00;
        }
        
        @keyframes scan {
            0% { top: 10%; }
            100% { top: 90%; }
        }
        
        .scan-frame {
            position: absolute;
            top: 20%;
            left: 10%;
            right: 10%;
            bottom: 20%;
            border: 2px solid #00ff00;
            box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.5);
        }
        
        .scan-corner {
            position: absolute;
            width: 20px;
            height: 20px;
            border: 3px solid #00ff00;
        }
        
        .scan-corner.top-left {
            top: -2px;
            left: -2px;
            border-right: none;
            border-bottom: none;
        }
        
        .scan-corner.top-right {
            top: -2px;
            right: -2px;
            border-left: none;
            border-bottom: none;
        }
        
        .scan-corner.bottom-left {
            bottom: -2px;
            left: -2px;
            border-right: none;
            border-top: none;
        }
        
        .scan-corner.bottom-right {
            bottom: -2px;
            right: -2px;
            border-left: none;
            border-top: none;
        }
    </style>
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
    
    <!-- Бічна панель та основний контент -->
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
                        <a href="barcode_scanner.php" class="flex items-center p-2 bg-purple-100 text-purple-700 rounded font-medium">
                            <i class="fas fa-barcode w-5 mr-2"></i>
                            <span>Сканер штрих-кодів</span>
                        </a>
                    </li>
                    <li>
                        <a href="receive.php" class="flex items-center p-2 text-gray-700 hover:bg-purple-50 rounded font-medium">
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
            
            <!-- Інструкції -->
            <div class="bg-white rounded-lg shadow-md p-4">
                <h3 class="font-semibold text-lg mb-3">Інструкції</h3>
                <ul class="text-sm text-gray-600 space-y-2">
                    <li>1. Натисніть "Почати сканування"</li>
                    <li>2. Дозвольте доступ до камери</li>
                    <li>3. Наведіть камеру на штрих-код товару</li>
                    <li>4. Після розпізнавання перевірте інформацію</li>
                    <li>5. Оновіть кількість при необхідності</li>
                </ul>
                
                <div class="mt-4 p-3 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800">
                    <p class="font-medium">Примітка:</p>
                    <p>ID товару в системі відповідає штрих-коду на упаковці</p>
                </div>
            </div>
        </aside>
        
        <!-- Основний контент -->
        <main class="w-full md:w-3/4">
            <!-- Сканер штрих-кодів -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">Сканер штрих-кодів для інвентаризації</h2>
                
                <!-- Область сканування -->
                <div id="scanner-container" class="mb-6 hidden">
                    <div class="scanner-overlay">
                        <div class="scan-frame">
                            <div class="scan-corner top-left"></div>
                            <div class="scan-corner top-right"></div>
                            <div class="scan-corner bottom-left"></div>
                            <div class="scan-corner bottom-right"></div>
                        </div>
                        <div class="scan-line"></div>
                    </div>
                </div>
                
                <!-- Кнопки управління -->
                <div class="text-center mb-6">
                    <button id="startButton" class="bg-purple-600 hover:bg-purple-700 text-white py-2 px-6 rounded-lg text-lg font-medium">
                        <i class="fas fa-camera mr-2"></i> Почати сканування
                    </button>
                    <button id="stopButton" class="bg-red-600 hover:bg-red-700 text-white py-2 px-6 rounded-lg text-lg font-medium hidden">
                        <i class="fas fa-stop mr-2"></i> Зупинити сканування
                    </button>
                </div>
                
                <!-- Ручне введення штрих-коду -->
                <div class="mb-6">
                    <label for="manualBarcode" class="block text-sm font-medium text-gray-700 mb-2">
                        Або введіть штрих-код вручну:
                    </label>
                    <div class="flex">
                        <input type="text" id="manualBarcode" placeholder="Введіть ID товару" 
                               class="flex-1 rounded-l-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                        <button id="manualSearchButton" class="bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded-r-md">
                            <i class="fas fa-search"></i> Знайти
                        </button>
                    </div>
                </div>
                
                <!-- Результат сканування -->
                <div id="scanResult" class="hidden">
                    <div class="bg-gray-50 rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Результат сканування</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <dl class="space-y-3">
                                    <div class="grid grid-cols-3 gap-4">
                                        <dt class="text-sm font-medium text-gray-500">Штрих-код:</dt>
                                        <dd class="text-sm text-gray-900 col-span-2" id="resultBarcode">-</dd>
                                    </div>
                                    <div class="grid grid-cols-3 gap-4">
                                        <dt class="text-sm font-medium text-gray-500">Назва:</dt>
                                        <dd class="text-sm text-gray-900 col-span-2" id="resultName">-</dd>
                                    </div>
                                    <div class="grid grid-cols-3 gap-4">
                                        <dt class="text-sm font-medium text-gray-500">Категорія:</dt>
                                        <dd class="text-sm text-gray-900 col-span-2" id="resultCategory">-</dd>
                                    </div>
                                </dl>
                            </div>
                            <div>
                                <dl class="space-y-3">
                                    <div class="grid grid-cols-3 gap-4">
                                        <dt class="text-sm font-medium text-gray-500">Поточний запас:</dt>
                                        <dd class="text-sm font-bold text-gray-900 col-span-2" id="resultQuantity">-</dd>
                                    </div>
                                    <div class="grid grid-cols-3 gap-4">
                                        <dt class="text-sm font-medium text-gray-500">Мін. запас:</dt>
                                        <dd class="text-sm text-gray-900 col-span-2" id="resultMinStock">-</dd>
                                    </div>
                                    <div class="grid grid-cols-3 gap-4">
                                        <dt class="text-sm font-medium text-gray-500">Статус:</dt>
                                        <dd class="text-sm col-span-2" id="resultStatus">-</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                        
                        <!-- Форма оновлення кількості -->
                        <div class="border-t border-gray-200 pt-4">
                            <h4 class="text-md font-medium text-gray-900 mb-3">Оновити кількість</h4>
                            <form id="updateQuantityForm" class="flex items-end space-x-4">
                                <input type="hidden" id="updateProductId">
                                <div class="flex-1">
                                    <label for="newQuantity" class="block text-sm font-medium text-gray-700 mb-1">
                                        Фактична кількість:
                                    </label>
                                    <input type="number" id="newQuantity" min="0" required
                                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                                </div>
                                <div>
                                    <span class="text-sm text-gray-500" id="unitLabel">од.</span>
                                </div>
                                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded">
                                    <i class="fas fa-save mr-1"></i> Зберегти
                                </button>
                                <button type="button" id="cancelUpdate" class="bg-gray-300 hover:bg-gray-400 text-gray-800 py-2 px-4 rounded">
                                    <i class="fas fa-times mr-1"></i> Скасувати
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Повідомлення про успіх/помилку -->
                <div id="successMessage" class="hidden bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                    <p class="font-medium">Успішно!</p>
                    <p id="successText"></p>
                </div>
                
                <div id="errorMessage" class="hidden bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                    <p class="font-medium">Помилка!</p>
                    <p id="errorText"></p>
                </div>
            </div>
            
            <!-- Історія сканувань -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Історія сканувань в цій сесії</h3>
                
                <div id="scanHistory" class="overflow-x-auto">
                    <p class="text-gray-500 text-center py-6">Історія порожня. Почніть сканування товарів.</p>
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
        let scannerIsRunning = false;
        let scanHistory = [];
        
        const startButton = document.getElementById('startButton');
        const stopButton = document.getElementById('stopButton');
        const scannerContainer = document.getElementById('scanner-container');
        const scanResult = document.getElementById('scanResult');
        const manualBarcode = document.getElementById('manualBarcode');
        const manualSearchButton = document.getElementById('manualSearchButton');
        const updateQuantityForm = document.getElementById('updateQuantityForm');
        const cancelUpdate = document.getElementById('cancelUpdate');
        
        // Налаштування для QuaggaJS
        const quaggaConfig = {
            inputStream: {
                name: "Live",
                type: "LiveStream",
                target: scannerContainer,
                constraints: {
                    width: 640,
                    height: 480,
                    facingMode: "environment"
                }
            },
            locator: {
                patchSize: "medium",
                halfSample: true
            },
            numOfWorkers: navigator.hardwareConcurrency || 4,
            decoder: {
                readers: [
                    "code_128_reader",
                    "ean_reader",
                    "ean_8_reader",
                    "code_39_reader",
                    "code_39_vin_reader",
                    "codabar_reader",
                    "upc_reader",
                    "upc_e_reader",
                    "i2of5_reader"
                ]
            },
            locate: true
        };
        
        // Почати сканування
        startButton.addEventListener('click', function() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                showError('Ваш браузер не підтримує доступ до камери');
                return;
            }
            
            startScanner();
        });
        
        // Зупинити сканування
        stopButton.addEventListener('click', function() {
            stopScanner();
        });
        
        // Ручний пошук
        manualSearchButton.addEventListener('click', function() {
            const barcode = manualBarcode.value.trim();
            if (barcode) {
                processBarcode(barcode);
            }
        });
        
        // Пошук за Enter
        manualBarcode.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                manualSearchButton.click();
            }
        });
        
        // Оновлення кількості
        updateQuantityForm.addEventListener('submit', function(e) {
            e.preventDefault();
            updateProductQuantity();
        });
        
        // Скасування оновлення
        cancelUpdate.addEventListener('click', function() {
            hideResult();
        });
        
        function startScanner() {
            Quagga.init(quaggaConfig, function(err) {
                if (err) {
                    console.error(err);
                    showError('Не вдалося ініціалізувати сканер: ' + err);
                    return;
                }
                
                scannerContainer.classList.remove('hidden');
                startButton.classList.add('hidden');
                stopButton.classList.remove('hidden');
                scannerIsRunning = true;
                
                Quagga.start();
            });
            
            // Обробка результату сканування
            Quagga.onDetected(function(result) {
                const code = result.codeResult.code;
                
                // Відтворюємо звуковий сигнал
                playBeep();
                
                // Зупиняємо сканер
                stopScanner();
                
                // Обробляємо штрих-код
                processBarcode(code);
            });
        }
        
        function stopScanner() {
            if (scannerIsRunning) {
                Quagga.stop();
                scannerContainer.classList.add('hidden');
                startButton.classList.remove('hidden');
                stopButton.classList.add('hidden');
                scannerIsRunning = false;
            }
        }
        
        function processBarcode(barcode) {
            // Очищаємо попередні повідомлення
            hideMessages();
            
            // Запит до сервера для отримання інформації про товар
            fetch(`barcode_scanner.php?ajax=1&product_id=${barcode}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayProduct(data.product);
                        addToHistory(data.product);
                    } else {
                        showError('Товар зі штрих-кодом ' + barcode + ' не знайдено');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('Помилка при пошуку товару');
                });
        }
        
        function displayProduct(product) {
            // Заповнюємо інформацію про товар
            document.getElementById('resultBarcode').textContent = product.id;
            document.getElementById('resultName').textContent = product.name;
            document.getElementById('resultCategory').textContent = getCategoryName(product.category);
            document.getElementById('resultQuantity').textContent = product.quantity + ' ' + product.unit;
            document.getElementById('resultMinStock').textContent = product.min_stock + ' ' + product.unit;
            
            // Статус запасу
            const statusElement = document.getElementById('resultStatus');
            if (product.quantity <= product.min_stock) {
                statusElement.innerHTML = '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Низький запас</span>';
            } else {
                statusElement.innerHTML = '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">В наявності</span>';
            }
            
            // Форма оновлення
            document.getElementById('updateProductId').value = product.id;
            document.getElementById('newQuantity').value = product.quantity;
            document.getElementById('unitLabel').textContent = product.unit;
            
            // Показуємо результат
            scanResult.classList.remove('hidden');
            
            // Очищаємо поле ручного введення
            manualBarcode.value = '';
        }
        
        function updateProductQuantity() {
            const productId = document.getElementById('updateProductId').value;
            const newQuantity = document.getElementById('newQuantity').value;
            
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('product_id', productId);
            formData.append('quantity', newQuantity);
            
            fetch('barcode_scanner.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess(data.message);
                    hideResult();
                    updateHistoryItem(productId, newQuantity);
                } else {
                    showError(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Помилка при оновленні кількості');
            });
        }
        
        function addToHistory(product) {
            scanHistory.unshift({
                id: product.id,
                name: product.name,
                oldQuantity: product.quantity,
                newQuantity: null,
                unit: product.unit,
                time: new Date().toLocaleTimeString()
            });
            
            updateHistoryDisplay();
        }
        
        function updateHistoryItem(productId, newQuantity) {
            const item = scanHistory.find(h => h.id == productId && h.newQuantity === null);
            if (item) {
                item.newQuantity = newQuantity;
                updateHistoryDisplay();
            }
        }
        
        function updateHistoryDisplay() {
            const historyContainer = document.getElementById('scanHistory');
            
            if (scanHistory.length === 0) {
                historyContainer.innerHTML = '<p class="text-gray-500 text-center py-6">Історія порожня. Почніть сканування товарів.</p>';
                return;
            }
            
            let html = `
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Час</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Товар</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Було</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Стало</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Зміна</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
            `;
            
            scanHistory.forEach(item => {
                const diff = item.newQuantity !== null ? (item.newQuantity - item.oldQuantity) : '-';
                const diffClass = diff > 0 ? 'text-green-600' : (diff < 0 ? 'text-red-600' : 'text-gray-500');
                const diffText = diff !== '-' ? (diff > 0 ? '+' + diff : diff) : diff;
                
                html += `
                    <tr>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">${item.time}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">${item.id}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">${item.name}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">${item.oldQuantity} ${item.unit}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                            ${item.newQuantity !== null ? item.newQuantity + ' ' + item.unit : '-'}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium ${diffClass}">
                            ${diffText} ${diff !== '-' ? item.unit : ''}
                        </td>
                    </tr>
                `;
            });
            
            html += `
                    </tbody>
                </table>
            `;
            
            historyContainer.innerHTML = html;
        }
        
        function getCategoryName(category) {
            const categories = {
                'raw_material': 'Сировина',
                'packaging': 'Упаковка',
                'finished_product': 'Готова продукція'
            };
            return categories[category] || category;
        }
        
        function showSuccess(message) {
            const successMessage = document.getElementById('successMessage');
            const successText = document.getElementById('successText');
            
            successText.textContent = message;
            successMessage.classList.remove('hidden');
            
            setTimeout(() => {
                successMessage.classList.add('hidden');
            }, 5000);
        }
        
        function showError(message) {
            const errorMessage = document.getElementById('errorMessage');
            const errorText = document.getElementById('errorText');
            
            errorText.textContent = message;
            errorMessage.classList.remove('hidden');
            
            setTimeout(() => {
                errorMessage.classList.add('hidden');
            }, 5000);
        }
        
        function hideMessages() {
            document.getElementById('successMessage').classList.add('hidden');
            document.getElementById('errorMessage').classList.add('hidden');
        }
        
        function hideResult() {
            scanResult.classList.add('hidden');
        }
        
        function playBeep() {
            const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBjiS1/LNeSsFJH');
            audio.play();
        }
        
        // Очищення при закритті сторінки
        window.addEventListener('beforeunload', function() {
            if (scannerIsRunning) {
                Quagga.stop();
            }
        });
    </script>
</body>
</html>