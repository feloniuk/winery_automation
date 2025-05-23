<?php
// views/admin/temperature.php
// Страница для просмотра и управления данными о температуре охлаждения вина

// Подключение контроллеров
require_once '../../controllers/AuthController.php';
require_once '../../controllers/AdminController.php';
require_once '../../config/database.php';

$authController = new AuthController();
$adminController = new AdminController();
$db = new Database();
$db->getConnection();

// Проверка авторизации и роли
if (!$authController->isLoggedIn() || !$authController->checkRole('admin')) {
    header('Location: ../../index.php');
    exit;
}

// Получение данных для страницы
$currentUser = $authController->getCurrentUser();

// Получение данных мониторинга температуры
// Порядок по ID, чтобы правильно отразить хронологию
$query = "SELECT * FROM data ORDER BY ID ASC";
$temperatureData = $db->select($query);

// Обработка действий с записями
$message = '';
$error = '';
$editRecord = null;

// Если это запрос на редактирование, получаем данные записи
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $recordId = $_GET['id'];
    $query = "SELECT * FROM data WHERE ID = ?";
    $editRecord = $db->selectOne($query, [$recordId]);
}

// Если это запрос на удаление
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $recordId = $_GET['id'];
    $query = "DELETE FROM data WHERE ID = ?";
    $result = $db->execute($query, [$recordId]);
    
    if ($result) {
        $message = "Запись успешно удалена";
        // Обновляем список записей
        $temperatureData = $db->select("SELECT * FROM data ORDER BY Dates DESC, Times DESC");
    } else {
        $error = "Ошибка при удалении записи";
    }
}

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Добавление новой записи
    if (isset($_POST['add_record'])) {
        $name = trim($_POST['name'] ?? '');
        $parameter = trim($_POST['parameter'] ?? '');
        $dates = trim($_POST['dates'] ?? '');
        $times = trim($_POST['times'] ?? '');
        
        if (empty($name) || empty($parameter) || empty($dates) || empty($times)) {
            $error = "Все поля необходимо заполнить";
        } else {
            $query = "INSERT INTO data (Name, Parameter, Dates, Times) VALUES (?, ?, ?, ?)";
            $result = $db->execute($query, [$name, $parameter, $dates, $times]);
            
            if ($result) {
                $message = "Запись успешно добавлена";
                // Обновляем список записей
                $temperatureData = $db->select("SELECT * FROM data ORDER BY Dates DESC, Times DESC");
            } else {
                $error = "Ошибка при добавлении записи";
            }
        }
    }
    
    // Обновление существующей записи
    if (isset($_POST['update_record'])) {
        $recordId = $_POST['record_id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $parameter = trim($_POST['parameter'] ?? '');
        $dates = trim($_POST['dates'] ?? '');
        $times = trim($_POST['times'] ?? '');
        
        if (empty($recordId) || empty($name) || empty($parameter) || empty($dates) || empty($times)) {
            $error = "Все поля необходимо заполнить";
        } else {
            $query = "UPDATE data SET Name = ?, Parameter = ?, Dates = ?, Times = ? WHERE ID = ?";
            $result = $db->execute($query, [$name, $parameter, $dates, $times, $recordId]);
            
            if ($result) {
                $message = "Запись успешно обновлена";
                // Обновляем список записей
                $temperatureData = $db->select("SELECT * FROM data ORDER BY Dates DESC, Times DESC");
                // Сбрасываем режим редактирования
                $editRecord = null;
            } else {
                $error = "Ошибка при обновлении записи";
            }
        }
    }
}

// Пагинация
$currentPage = isset($_GET['page']) ? max(1, min($totalPages, (int)$_GET['page'])) : 1;
$recordsPerPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 25;
$totalRecords = count($temperatureData);
$totalPages = ceil($totalRecords / $recordsPerPage);
$startIndex = ($currentPage - 1) * $recordsPerPage;
$displayedRecords = array_slice($temperatureData, $startIndex, $recordsPerPage);

// Статистика
$avgTemperature = 0;
$minTemperature = 100;
$maxTemperature = 0;

if (!empty($temperatureData)) {
    $sum = 0;
    foreach ($temperatureData as $record) {
        $temperature = (float)$record['Parameter'];
        $sum += $temperature;
        $minTemperature = min($minTemperature, $temperature);
        $maxTemperature = max($maxTemperature, $temperature);
    }
    $avgTemperature = $sum / count($temperatureData);
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Моніторинг температури - Панель адміністратора</title>
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
                        <a href="purchasing.php" class="flex items-center p-2 text-gray-700 hover:bg-indigo-50 rounded font-medium">
                            <i class="fas fa-shopping-cart w-5 mr-2"></i>
                            <span>Закупівлі</span>
                        </a>
                    </li>
                    <li>
                        <a href="temperature.php" class="flex items-center p-2 bg-indigo-100 text-indigo-700 rounded font-medium">
                            <i class="fas fa-thermometer-half w-5 mr-2"></i>
                            <span>Моніторинг СКАДА</span>
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
            
            <!-- Блок зі статистикою -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <h3 class="font-semibold text-lg mb-3">Статистика температури</h3>
                
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Середнє значення:</span>
                        <span class="font-semibold"><?php echo number_format($avgTemperature, 2); ?> °C</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Мінімальне:</span>
                        <span class="font-semibold"><?php echo number_format($minTemperature, 2); ?> °C</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Максимальне:</span>
                        <span class="font-semibold"><?php echo number_format($maxTemperature, 2); ?> °C</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Всього записів:</span>
                        <span class="font-semibold"><?php echo $totalRecords; ?></span>
                    </div>
                </div>
            </div>
        </aside>
        
        <!-- Основний контент -->
        <main class="w-full md:w-3/4">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold text-gray-800">Моніторинг температури охолодження вина</h2>
                    
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
                
                <!-- Графік температури -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Графік зміни температури</h3>
                    <div class="h-80">
                        <canvas id="temperatureChart"></canvas>
                    </div>
                </div>
                
                <!-- Форма додавання/редагування запису -->
                <div id="recordForm" class="<?php echo $editRecord ? 'block' : 'hidden'; ?> bg-gray-50 p-6 rounded-lg mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        <?php echo $editRecord ? 'Редактирование записи' : 'Добавление новой записи'; ?>
                    </h3>
                    <form method="POST" action="">
                        <?php if ($editRecord): ?>
                        <input type="hidden" name="record_id" value="<?php echo $editRecord['ID']; ?>">
                        <?php endif; ?>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">Название</label>
                                <input type="text" id="name" name="name" required
                                       value="<?php echo $editRecord ? htmlspecialchars($editRecord['Name']) : 'Температура охлаждения вина'; ?>"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="parameter" class="block text-sm font-medium text-gray-700">Температура (°C)</label>
                                <input type="number" id="parameter" name="parameter" step="0.01" required
                                       value="<?php echo $editRecord ? htmlspecialchars($editRecord['Parameter']) : '14.00'; ?>"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="dates" class="block text-sm font-medium text-gray-700">Дата</label>
                                <input type="date" id="dates" name="dates" required
                                       value="<?php echo $editRecord ? htmlspecialchars($editRecord['Dates']) : date('Y-m-d'); ?>"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="times" class="block text-sm font-medium text-gray-700">Время</label>
                                <input type="time" id="times" name="times" required
                                       value="<?php echo $editRecord ? htmlspecialchars($editRecord['Times']) : date('H:i:s'); ?>"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                        </div>
                        
                        <div class="mt-6 flex justify-end space-x-3">
                            <button type="button" id="cancelForm" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Отмена
                            </button>
                            <?php if ($editRecord): ?>
                            <button type="submit" name="update_record" class="bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700">
                                Сохранить изменения
                            </button>
                            <?php else: ?>
                            <button type="submit" name="add_record" class="bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700">
                                Добавить запись
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <!-- Инструменты управления отображением -->
                <div class="flex flex-wrap justify-between items-center mb-4">
                    <div class="flex items-center space-x-2">
                        <span class="text-sm text-gray-700">Показать</span>
                        <select id="perPage" class="text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                onchange="changeRecordsPerPage(this)">
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="250">250</option>
                            <option value="500">500</option>
                        </select>
                        <span class="text-sm text-gray-700">записей</span>
                    </div>
                    
                    <div class="flex items-center">
                        <span class="text-sm text-gray-700 mr-3">
                            Показано <?php echo $startIndex + 1; ?> - <?php echo min($startIndex + $recordsPerPage, $totalRecords); ?> из <?php echo $totalRecords; ?> записей
                        </span>
                        
                        <div class="flex space-x-1">
                            <a href="?page=1" class="px-3 py-1 bg-white text-gray-500 border border-gray-300 rounded-md hover:bg-gray-100 <?php echo $currentPage == 1 ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="?page=<?php echo max(1, $currentPage - 1); ?>" class="px-3 py-1 bg-white text-gray-500 border border-gray-300 rounded-md hover:bg-gray-100 <?php echo $currentPage == 1 ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                                <i class="fas fa-angle-left"></i>
                            </a>
                            
                            <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>" class="px-3 py-1 <?php echo $i == $currentPage ? 'bg-indigo-600 text-white' : 'bg-white text-gray-500'; ?> border border-gray-300 rounded-md hover:bg-indigo-100">
                                <?php echo $i; ?>
                            </a>
                            <?php endfor; ?>
                            
                            <a href="?page=<?php echo min($totalPages, $currentPage + 1); ?>" class="px-3 py-1 bg-white text-gray-500 border border-gray-300 rounded-md hover:bg-gray-100 <?php echo $currentPage == $totalPages ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="?page=<?php echo $totalPages; ?>" class="px-3 py-1 bg-white text-gray-500 border border-gray-300 rounded-md hover:bg-gray-100 <?php echo $currentPage == $totalPages ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Таблица данных температуры -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    ID
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Название
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Температура (°C)
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Дата
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Время
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Действия
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($displayedRecords)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                    Записи не найдены
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($displayedRecords as $record): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $record['ID']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($record['Name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm <?php echo $record['Parameter'] < 13 || $record['Parameter'] > 16 ? 'text-red-600 font-bold' : 'text-green-600 font-medium'; ?>">
                                        <?php echo htmlspecialchars($record['Parameter']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($record['Dates']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($record['Times']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="?action=edit&id=<?php echo $record['ID']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                            <i class="fas fa-edit"></i> Изменить
                                        </a>
                                        <a href="?action=delete&id=<?php echo $record['ID']; ?>" 
                                           onclick="return confirm('Вы уверены, что хотите удалить эту запись?')"
                                           class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i> Удалить
                                        </a>
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
            &copy; <?php echo date('Y'); ?> Винное производство. Система автоматизации процессов.
        </div>
    </footer>
    
    <!-- JavaScript -->
    <script>
        // Показать/скрыть форму добавления записи
        document.addEventListener('DOMContentLoaded', function() {
            const showAddFormBtn = document.getElementById('showAddForm');
            const cancelFormBtn = document.getElementById('cancelForm');
            const recordForm = document.getElementById('recordForm');
            
            if (showAddFormBtn) {
                showAddFormBtn.addEventListener('click', function() {
                    recordForm.classList.remove('hidden');
                    showAddFormBtn.classList.add('hidden');
                });
            }
            
            if (cancelFormBtn) {
                cancelFormBtn.addEventListener('click', function() {
                    <?php if ($editRecord): ?>
                    window.location.href = 'temperature.php';
                    <?php else: ?>
                    recordForm.classList.add('hidden');
                    showAddFormBtn.classList.remove('hidden');
                    <?php endif; ?>
                });
            }
            
            // График температуры
            const ctx = document.getElementById('temperatureChart').getContext('2d');
            
            // Подготавливаем данные для графика (берем все доступные данные или ограничиваем)
            const labels = [];
            const data = [];
            
            <?php 
            // Подготовка данных для графика - сортировка по ID, чтобы показать хронологию
            // Возьмем все записи или ограничим до 100 для производительности
            $graphData = array_slice($temperatureData, 0, 100);
            
            foreach ($graphData as $record) {
                $dateTime = date('H:i:s', strtotime($record['Times']));
                echo "labels.push('$dateTime');";
                echo "data.push(" . $record['Parameter'] . ");";
            }
            ?>
            
            const temperatureChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Температура охлаждения вина (°C)',
                        data: data,
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 2,
                        tension: 0.2,
                        pointRadius: 3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                title: function(tooltipItems) {
                                    return 'Время: ' + tooltipItems[0].label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            min: <?php echo floor(min(array_column($graphData, 'Parameter')) - 1); ?>,
                            max: <?php echo ceil(max(array_column($graphData, 'Parameter')) + 1); ?>,
                            title: {
                                display: true,
                                text: 'Температура (°C)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Время'
                            }
                        }
                    }
                }
            });
        });
        
        // Изменение количества записей на странице
        function changeRecordsPerPage(select) {
            const perPage = select.value;
            window.location.href = `?page=1&per_page=${perPage}`;
        }
    </script>
</body>
</html>