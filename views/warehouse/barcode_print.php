<?php
// views/warehouse/barcode_print.php
// Сторінка для генерації та друку штрих-кодів товарів

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

// Категорії для відображення
$categories = [
    'raw_material' => 'Сировина',
    'packaging' => 'Упаковка',
    'finished_product' => 'Готова продукція'
];

?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Друк штрих-кодів - Винне виробництво</title>
    <!-- Підключення Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Іконки -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- JsBarcode для генерації штрих-кодів -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    
    <style>
        @media print {
            body * {
                visibility: hidden;
            }
            
            #printArea, #printArea * {
                visibility: visible;
            }
            
            #printArea {
                position: absolute;
                left: 0;
                top: 0;
            }
            
            .no-print {
                display: none !important;
            }
            
            .barcode-label {
                page-break-inside: avoid;
                margin: 5mm;
                padding: 5mm;
                border: 1px solid #000;
            }
        }
        
        .barcode-label {
            width: 200px;
            padding: 10px;
            border: 1px solid #ddd;
            text-align: center;
            margin: 5px;
            background: white;
        }
        
        .barcode-label h4 {
            font-size: 14px;
            font-weight: bold;
            margin: 0 0 5px 0;
            word-wrap: break-word;
        }
        
        .barcode-label .category {
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .barcode-label canvas {
            max-width: 100%;
        }
        
        .barcode-label .barcode-text {
            font-size: 12px;
            margin-top: 5px;
            font-family: monospace;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Верхня навігаційна панель -->
    <nav class="bg-purple-800 text-white p-4 shadow-md no-print">
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
    
    <div class="container mx-auto mt-6 px-4 no-print">
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-semibold text-gray-800">Генерація та друк штрих-кодів</h2>
                <a href="inventory.php" class="text-purple-600 hover:text-purple-800">
                    <i class="fas fa-arrow-left mr-1"></i> Повернутися до інвентаризації
                </a>
            </div>
            
            <!-- Фільтри -->
            <div class="mb-6">
                <h3 class="text-lg font-medium text-gray-900 mb-3">Виберіть товари для друку штрих-кодів</h3>
                
                <div class="flex items-center space-x-4 mb-4">
                    <button id="selectAll" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded text-sm">
                        <i class="fas fa-check-square mr-1"></i> Вибрати всі
                    </button>
                    <button id="deselectAll" class="bg-gray-600 hover:bg-gray-700 text-white py-2 px-4 rounded text-sm">
                        <i class="fas fa-square mr-1"></i> Зняти виділення
                    </button>
                    <select id="categoryFilter" class="rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                        <option value="">Всі категорії</option>
                        <?php foreach ($categories as $code => $name): ?>
                        <option value="<?php echo $code; ?>"><?php echo $name; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Список товарів -->
                <div class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        <?php foreach ($products as $product): ?>
                        <label class="flex items-center p-3 border border-gray-200 rounded hover:bg-gray-50 cursor-pointer product-item" 
                               data-category="<?php echo $product['category']; ?>">
                            <input type="checkbox" class="product-checkbox mr-3" 
                                   data-id="<?php echo $product['id']; ?>"
                                   data-name="<?php echo htmlspecialchars($product['name']); ?>"
                                   data-category="<?php echo $product['category']; ?>">
                            <div class="flex-1">
                                <div class="font-medium"><?php echo htmlspecialchars($product['name']); ?></div>
                                <div class="text-sm text-gray-500">
                                    <?php echo $categories[$product['category']] ?? $product['category']; ?> | 
                                    ID: <?php echo $product['id']; ?>
                                </div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Кнопки дій -->
            <div class="flex justify-between items-center">
                <div class="text-sm text-gray-500">
                    Вибрано товарів: <span id="selectedCount" class="font-bold">0</span>
                </div>
                <div class="space-x-3">
                    <button id="generateBarcodes" class="bg-purple-600 hover:bg-purple-700 text-white py-2 px-6 rounded" disabled>
                        <i class="fas fa-barcode mr-1"></i> Згенерувати штрих-коди
                    </button>
                    <button id="printBarcodes" class="bg-green-600 hover:bg-green-700 text-white py-2 px-6 rounded hidden">
                        <i class="fas fa-print mr-1"></i> Друк
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Попередній перегляд штрих-кодів -->
        <div id="previewContainer" class="bg-white rounded-lg shadow-md p-6 hidden">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Попередній перегляд штрих-кодів</h3>
            <div id="barcodesPreview" class="flex flex-wrap justify-center">
                <!-- Штрих-коди будуть додані сюди -->
            </div>
        </div>
    </div>
    
    <!-- Область для друку -->
    <div id="printArea" style="display: none;">
        <!-- Штрих-коди для друку будуть додані сюди -->
    </div>
    
    <footer class="bg-white p-4 mt-8 border-t border-gray-200 no-print">
        <div class="container mx-auto text-center text-gray-500 text-sm">
            &copy; <?php echo date('Y'); ?> Винне виробництво. Система автоматизації процесів.
        </div>
    </footer>
    
    <!-- JavaScript -->
    <script>
        const selectAllBtn = document.getElementById('selectAll');
        const deselectAllBtn = document.getElementById('deselectAll');
        const categoryFilter = document.getElementById('categoryFilter');
        const generateBtn = document.getElementById('generateBarcodes');
        const printBtn = document.getElementById('printBarcodes');
        const selectedCountSpan = document.getElementById('selectedCount');
        const previewContainer = document.getElementById('previewContainer');
        const barcodesPreview = document.getElementById('barcodesPreview');
        const printArea = document.getElementById('printArea');
        
        // Оновлення лічильника вибраних товарів
        function updateSelectedCount() {
            const count = document.querySelectorAll('.product-checkbox:checked').length;
            selectedCountSpan.textContent = count;
            generateBtn.disabled = count === 0;
        }
        
        // Вибрати всі
        selectAllBtn.addEventListener('click', function() {
            const visibleCheckboxes = document.querySelectorAll('.product-item:not([style*="display: none"]) .product-checkbox');
            visibleCheckboxes.forEach(cb => cb.checked = true);
            updateSelectedCount();
        });
        
        // Зняти виділення
        deselectAllBtn.addEventListener('click', function() {
            document.querySelectorAll('.product-checkbox').forEach(cb => cb.checked = false);
            updateSelectedCount();
        });
        
        // Фільтр за категорією
        categoryFilter.addEventListener('change', function() {
            const selectedCategory = this.value;
            const productItems = document.querySelectorAll('.product-item');
            
            productItems.forEach(item => {
                if (!selectedCategory || item.dataset.category === selectedCategory) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
        
        // Оновлення лічильника при зміні checkbox
        document.querySelectorAll('.product-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSelectedCount);
        });
        
        // Генерація штрих-кодів
        generateBtn.addEventListener('click', function() {
            const selectedProducts = [];
            document.querySelectorAll('.product-checkbox:checked').forEach(cb => {
                selectedProducts.push({
                    id: cb.dataset.id,
                    name: cb.dataset.name,
                    category: cb.dataset.category
                });
            });
            
            if (selectedProducts.length === 0) return;
            
            // Очищаємо попередні штрих-коди
            barcodesPreview.innerHTML = '';
            printArea.innerHTML = '';
            
            // Генеруємо штрих-коди
            selectedProducts.forEach(product => {
                // Для попереднього перегляду
                const previewLabel = createBarcodeLabel(product);
                barcodesPreview.appendChild(previewLabel);
                
                // Для друку
                const printLabel = createBarcodeLabel(product);
                printArea.appendChild(printLabel);
            });
            
            // Показуємо попередній перегляд та кнопку друку
            previewContainer.classList.remove('hidden');
            printBtn.classList.remove('hidden');
            
            // Прокручуємо до попереднього перегляду
            previewContainer.scrollIntoView({ behavior: 'smooth' });
        });
        
        // Створення елемента штрих-коду
        function createBarcodeLabel(product) {
            const label = document.createElement('div');
            label.className = 'barcode-label';
            
            const categoryNames = {
                'raw_material': 'Сировина',
                'packaging': 'Упаковка',
                'finished_product': 'Готова продукція'
            };
            
            label.innerHTML = `
                <h4>${product.name}</h4>
                <div class="category">${categoryNames[product.category] || product.category}</div>
                <canvas id="barcode-${product.id}-${Date.now()}"></canvas>
                <div class="barcode-text">ID: ${product.id}</div>
            `;
            
            // Генерація штрих-коду після додавання в DOM
            setTimeout(() => {
                const canvas = label.querySelector('canvas');
                if (canvas) {
                    JsBarcode(canvas, product.id.toString(), {
                        format: "CODE128",
                        width: 2,
                        height: 60,
                        displayValue: false,
                        margin: 10
                    });
                }
            }, 100);
            
            return label;
        }
        
        // Друк штрих-кодів
        printBtn.addEventListener('click', function() {
            window.print();
        });
        
        // Ініціалізація
        updateSelectedCount();
    </script>
</body>
</html>