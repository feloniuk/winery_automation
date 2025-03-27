<?php
// views/admin/cameras.php
// Сторінка для перегляду та управління камерами спостереження

// Підключення контролерів
require_once '../../controllers/AuthController.php';
require_once '../../controllers/AdminController.php';

$authController = new AuthController();
$adminController = new AdminController();

// Перевірка авторизації та ролі
if (!$authController->isLoggedIn() || !$authController->checkRole('admin')) {
    header('Location: ../../index.php');
    exit;
}

// Отримання даних для сторінки
$currentUser = $authController->getCurrentUser();
$cameraList = $adminController->getCameras();

// Обробка дій додавання/редагування камери
$message = '';
$error = '';
$editCamera = null;

// Якщо це запит на редагування, отримуємо дані камери
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $cameraId = $_GET['id'];
    $editCamera = array_filter($cameraList, function($cam) use ($cameraId) {
        return $cam['id'] == $cameraId;
    });
    
    if (!empty($editCamera)) {
        $editCamera = reset($editCamera); // Отримуємо перший елемент
    }
}

// Обробка відправки форми
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Додавання нової камери
    if (isset($_POST['add_camera'])) {
        $name = trim($_POST['name'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $stream_url = trim($_POST['stream_url'] ?? '');
        
        if (empty($name) || empty($location) || empty($stream_url)) {
            $error = "Усі поля необхідно заповнити";
        } else {
            $result = $adminController->addCamera($name, $location, $stream_url);
            if ($result['success']) {
                $message = $result['message'];
                // Перезавантажуємо список камер
                $cameraList = $adminController->getCameras();
            } else {
                $error = $result['message'];
            }
        }
    }
    
    // Оновлення існуючої камери
    if (isset($_POST['update_camera'])) {
        $camera_id = $_POST['camera_id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $stream_url = trim($_POST['stream_url'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        if (empty($camera_id) || empty($name) || empty($location) || empty($stream_url)) {
            $error = "Усі поля необхідно заповнити";
        } else {
            $result = $adminController->updateCamera($camera_id, $name, $location, $stream_url, $status);
            if ($result['success']) {
                $message = $result['message'];
                // Перезавантажуємо список камер
                $cameraList = $adminController->getCameras();
                // Скидаємо режим редагування
                $editCamera = null;
            } else {
                $error = $result['message'];
            }
        }
    }
    
    // Видалення камери
    if (isset($_POST['delete_camera'])) {
        $camera_id = $_POST['camera_id'] ?? '';
        
        if (empty($camera_id)) {
            $error = "Ідентифікатор камери не вказано";
        } else {
            $result = $adminController->deleteCamera($camera_id);
            if ($result['success']) {
                $message = $result['message'];
                // Перезавантажуємо список камер
                $cameraList = $adminController->getCameras();
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Камери спостереження - Панель адміністратора</title>
    <!-- Підключення Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
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
                        <a href="cameras.php" class="flex items-center p-2 bg-indigo-100 text-indigo-700 rounded font-medium">
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
        </aside>
        
        <!-- Основний контент -->
        <main class="w-full md:w-3/4">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold text-gray-800">Камери спостереження</h2>
                    <?php if (!$editCamera): ?>
                    <button id="showAddForm" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded">
                        <i class="fas fa-plus mr-1"></i> Додати камеру
                    </button>
                    <?php endif; ?>
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
                
                <!-- Форма додавання/редагування камери -->
                <div id="cameraForm" class="<?php echo $editCamera ? 'block' : 'hidden'; ?> bg-gray-50 p-6 rounded-lg mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        <?php echo $editCamera ? 'Редагування камери' : 'Додавання нової камери'; ?>
                    </h3>
                    <form method="POST" action="">
                        <?php if ($editCamera): ?>
                        <input type="hidden" name="camera_id" value="<?php echo $editCamera['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">Назва камери</label>
                                <input type="text" id="name" name="name" required
                                       value="<?php echo $editCamera ? htmlspecialchars($editCamera['name']) : ''; ?>"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="location" class="block text-sm font-medium text-gray-700">Розташування</label>
                                <input type="text" id="location" name="location" required
                                       value="<?php echo $editCamera ? htmlspecialchars($editCamera['location']) : ''; ?>"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                            <div class="md:col-span-2">
                                <label for="stream_url" class="block text-sm font-medium text-gray-700">URL потоку</label>
                                <input type="text" id="stream_url" name="stream_url" required
                                       value="<?php echo $editCamera ? htmlspecialchars($editCamera['stream_url']) : ''; ?>"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <p class="mt-1 text-xs text-gray-500">Наприклад: rtsp://192.168.1.100:554/cam1 або http://localhost/webcam.php для локальної камери</p>
                            </div>
                            
                            <?php if ($editCamera): ?>
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">Статус</label>
                                <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="active" <?php echo $editCamera['status'] == 'active' ? 'selected' : ''; ?>>Активна</option>
                                    <option value="inactive" <?php echo $editCamera['status'] == 'inactive' ? 'selected' : ''; ?>>Неактивна</option>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mt-6 flex justify-end space-x-3">
                            <button type="button" id="cancelForm" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Скасувати
                            </button>
                            <?php if ($editCamera): ?>
                            <button type="submit" name="update_camera" class="bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700">
                                Зберегти зміни
                            </button>
                            <?php else: ?>
                            <button type="submit" name="add_camera" class="bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700">
                                Додати камеру
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <!-- Список камер -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if (empty($cameraList)): ?>
                    <div class="md:col-span-2 lg:col-span-3 text-center py-8">
                        <p class="text-gray-500">Камери не налаштовані. Додайте першу камеру.</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($cameraList as $camera): ?>
                        <div class="bg-gray-50 rounded-lg overflow-hidden shadow">
                            <div class="aspect-w-16 aspect-h-9 bg-gray-200">
                                <div class="w-full h-40 flex items-center justify-center bg-gray-800 text-gray-400">
                                    <i class="fas fa-video fa-3x"></i>
                                </div>
                            </div>
                            <div class="p-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($camera['name']); ?></h3>
                                        <p class="text-sm text-gray-500">
                                            <i class="fas fa-map-marker-alt mr-1"></i> 
                                            <?php echo htmlspecialchars($camera['location']); ?>
                                        </p>
                                    </div>
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $camera['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $camera['status'] === 'active' ? 'Активна' : 'Неактивна'; ?>
                                    </span>
                                </div>
                                <p class="mt-2 text-sm text-gray-600 truncate">
                                    <span class="font-medium">URL:</span> <?php echo htmlspecialchars($camera['stream_url']); ?>
                                </p>
                                <div class="mt-4 flex justify-between">
                                    <a href="?action=edit&id=<?php echo $camera['id']; ?>" class="text-indigo-600 hover:text-indigo-900">
                                        <i class="fas fa-edit mr-1"></i> Редагувати
                                    </a>
                                    <form method="POST" action="" onsubmit="return confirm('Ви впевнені, що хочете видалити цю камеру?');">
                                        <input type="hidden" name="camera_id" value="<?php echo $camera['id']; ?>">
                                        <button type="submit" name="delete_camera" class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash mr-1"></i> Видалити
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Розділ з відео моніторингом -->
            <?php if (!empty($cameraList)): ?>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">Відеоспостереження в реальному часі</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php 
                    // Отримуємо тільки активні камери
                    $activeCameras = array_filter($cameraList, function($cam) {
                        return $cam['status'] === 'active';
                    });
                    
                    // Якщо немає активних камер
                    if (empty($activeCameras)): 
                    ?>
                    <div class="md:col-span-2 text-center py-8">
                        <p class="text-gray-500">Немає активних камер для відображення.</p>
                    </div>
                    <?php else: ?>
                        <?php 
                        // Створюємо тимчасовий HTML-файл для веб-камери, якщо його ще немає
                        $webcamFilePath = '../../webcam.php';
                        if (!file_exists($webcamFilePath)) {
                            $webcamContent = <<<HTML
<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Веб-камера</title>
    <style>
        body { 
            margin: 0; 
            padding: 0; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            background: #000; 
            overflow: hidden;
        }
        video { 
            width: 100%; 
            height: 100vh; 
            object-fit: cover;
        }
    </style>
</head>
<body>
    <video id="video" autoplay playsinline></video>
    
    <script>
        const video = document.getElementById('video');
        
        async function startVideo() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    },
                    audio: false
                });
                
                video.srcObject = stream;
            } catch (err) {
                console.error("Помилка доступу до камери:", err);
                document.body.innerHTML = "<p style='color: white; text-align: center;'>Помилка доступу до камери. Переконайтеся, що камера підключена та дозволи надані.</p>";
            }
        }
        
        startVideo();
    </script>
</body>
</html>
HTML;
                            file_put_contents($webcamFilePath, $webcamContent);
                        }
                        
                        foreach (array_slice($activeCameras, 0, 4) as $index => $camera): 
                        // Перевіряємо, чи є ця камера Камерою 1
                        $isCamera1 = $camera['id'] == 1 || $camera['name'] == 'Камера 1';
                        ?>
                        <div class="bg-gray-900 rounded-lg overflow-hidden shadow">
                            <div class="aspect-w-16 aspect-h-9">
                                <?php if ($isCamera1): ?>
                                <!-- Відображаємо веб-камеру для Камери 1 -->
                                <iframe src="<?php echo htmlspecialchars('../../webcam.php'); ?>" 
                                        class="w-full h-64" 
                                        frameborder="0" 
                                        allow="camera; microphone" 
                                        allowfullscreen></iframe>
                                <?php else: ?>
                                <!-- Для інших камер показуємо заглушку -->
                                <div class="w-full h-64 flex flex-col items-center justify-center text-gray-500">
                                    <i class="fas fa-video-slash fa-3x mb-2"></i>
                                    <p class="text-center px-4">Це демонстраційний режим.<br>У реальній системі тут буде відео з камери.</p>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="p-3 bg-gray-800 text-white">
                                <div class="flex justify-between items-center">
                                    <span><?php echo htmlspecialchars($camera['name']); ?></span>
                                    <span class="text-xs text-gray-400"><?php echo htmlspecialchars($camera['location']); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <?php if (count($activeCameras) > 4): ?>
                <div class="mt-6 text-center">
                    <button id="loadMoreCameras" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Завантажити більше камер <i class="fas fa-chevron-down ml-2"></i>
                    </button>
                </div>
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
    
    <!-- JavaScript -->
    <script>
        // Показати/приховати форму додавання камери
        document.addEventListener('DOMContentLoaded', function() {
            const showAddFormBtn = document.getElementById('showAddForm');
            const cancelFormBtn = document.getElementById('cancelForm');
            const cameraForm = document.getElementById('cameraForm');
            
            if (showAddFormBtn) {
                showAddFormBtn.addEventListener('click', function() {
                    cameraForm.classList.remove('hidden');
                    showAddFormBtn.classList.add('hidden');
                });
            }
            
            if (cancelFormBtn) {
                cancelFormBtn.addEventListener('click', function() {
                    <?php if ($editCamera): ?>
                    window.location.href = 'cameras.php';
                    <?php else: ?>
                    cameraForm.classList.add('hidden');
                    showAddFormBtn.classList.remove('hidden');
                    <?php endif; ?>
                });
            }
        });
    </script>
</body>
</html>
