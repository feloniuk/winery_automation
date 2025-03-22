<?php
// views/common/messages.php
// Универсальная страница обмена сообщениями для всех ролей

// Подключение контроллеров
require_once '../../controllers/AuthController.php';

$authController = new AuthController();

// Проверка авторизации
if (!$authController->isLoggedIn()) {
    header('Location: ../../index.php');
    exit;
}

// Определение роли и загрузка соответствующего контроллера
$currentUser = $authController->getCurrentUser();
$role = $currentUser['role'];

if ($role === 'supplier') {
    require_once '../../controllers/SupplierController.php';
    $controller = new SupplierController();
} elseif ($role === 'purchasing') {
    require_once '../../controllers/PurchasingController.php';
    $controller = new PurchasingController();
} elseif ($role === 'warehouse') {
    require_once '../../controllers/WarehouseController.php';
    $controller = new WarehouseController();
} elseif ($role === 'admin') {
    require_once '../../controllers/AdminController.php';
    $controller = new AdminController();
}

// Определение цветов и заголовка в зависимости от роли
switch ($role) {
    case 'supplier':
        $primaryColor = 'amber';
        $headerTitle = 'Поставщик';
        $basePath = '../supplier/';
        break;
    case 'purchasing':
        $primaryColor = 'teal';
        $headerTitle = 'Менеджер по закупкам';
        $basePath = '../purchasing/';
        break;
    case 'warehouse':
        $primaryColor = 'purple';
        $headerTitle = 'Начальник склада';
        $basePath = '../warehouse/';
        break;
    case 'admin':
        $primaryColor = 'indigo';
        $headerTitle = 'Администратор';
        $basePath = '../admin/';
        break;
    default:
        $primaryColor = 'gray';
        $headerTitle = 'Пользователь';
        $basePath = '../';
}

// Получение пользователей для отправки сообщений
$recipients = [];
if (isset($controller) && method_exists($controller, 'getPotentialMessageRecipients')) {
    $recipients = $controller->getPotentialMessageRecipients();
}

// Обработка действий
$message = '';
$error = '';
$viewMessage = null;
$replyToMessage = null;

// Режим просмотра сообщения
if (isset($_GET['view']) && !empty($_GET['view'])) {
    $messageId = $_GET['view'];
    $viewMessage = $controller->getMessage($messageId, $currentUser['id']);
}

// Режим ответа на сообщение
if (isset($_GET['reply']) && !empty($_GET['reply'])) {
    $messageId = $_GET['reply'];
    $replyToMessage = $controller->getMessage($messageId, $currentUser['id']);
}

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Отправка сообщения
    if (isset($_POST['send_message'])) {
        $receiverId = $_POST['receiver_id'] ?? '';
        $subject = $_POST['subject'] ?? '';
        $content = $_POST['message'] ?? '';
        
        if (empty($receiverId)) {
            $error = "Необходимо выбрать получателя";
        } elseif (empty($content)) {
            $error = "Текст сообщения не может быть пустым";
        } else {
            $result = $controller->sendMessage($currentUser['id'], $receiverId, $subject, $content);
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Получение всех сообщений пользователя
$allMessages = $controller->getAllMessages($currentUser['id']);

// Разделение на входящие и исходящие
$receivedMessages = array_filter($allMessages, function($msg) {
    return $msg['type'] === 'received';
});

$sentMessages = array_filter($allMessages, function($msg) {
    return $msg['type'] === 'sent';
});

// Подсчет непрочитанных сообщений
$unreadCount = count(array_filter($receivedMessages, function($msg) {
    return $msg['is_read'] == 0;
}));
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Сообщения - Винное производство</title>
    <!-- Подключение Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Иконки -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Верхняя навигационная панель -->
    <nav class="bg-<?php echo $primaryColor; ?>-800 text-white p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center">
                <i class="fas fa-wine-bottle text-2xl mr-3"></i>
                <h1 class="text-xl font-bold">Винное производство</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span><?php echo htmlspecialchars($currentUser['name']); ?> (<?php echo $headerTitle; ?>)</span>
                <a href="../../controllers/logout.php" class="bg-<?php echo $primaryColor; ?>-700 hover:bg-<?php echo $primaryColor; ?>-600 py-2 px-4 rounded text-sm">
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
                    <div class="bg-<?php echo $primaryColor; ?>-100 p-3 rounded-full mr-4">
                        <i class="fas fa-user text-<?php echo $primaryColor; ?>-600"></i>
                    </div>
                    <div>
                        <p class="font-semibold"><?php echo htmlspecialchars($currentUser['name']); ?></p>
                        <p class="text-sm text-gray-500"><?php echo $headerTitle; ?></p>
                    </div>
                </div>
                
                <ul class="space-y-2">
                    <li>
                        <a href="<?php echo $basePath; ?>dashboard.php" class="flex items-center p-2 text-gray-700 hover:bg-<?php echo $primaryColor; ?>-50 rounded font-medium">
                            <i class="fas fa-tachometer-alt w-5 mr-2"></i>
                            <span>Панель управления</span>
                        </a>
                    </li>
                    
                    <?php if ($role === 'supplier'): ?>
                    <li>
                        <a href="<?php echo $basePath; ?>orders.php" class="flex items-center p-2 text-gray-700 hover:bg-<?php echo $primaryColor; ?>-50 rounded font-medium">
                            <i class="fas fa-shopping-cart w-5 mr-2"></i>
                            <span>Заказы</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $basePath; ?>products.php" class="flex items-center p-2 text-gray-700 hover:bg-<?php echo $primaryColor; ?>-50 rounded font-medium">
                            <i class="fas fa-boxes w-5 mr-2"></i>
                            <span>Мои товары</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if ($role === 'purchasing'): ?>
                    <li>
                        <a href="<?php echo $basePath; ?>suppliers.php" class="flex items-center p-2 text-gray-700 hover:bg-<?php echo $primaryColor; ?>-50 rounded font-medium">
                            <i class="fas fa-truck w-5 mr-2"></i>
                            <span>Поставщики</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $basePath; ?>orders.php" class="flex items-center p-2 text-gray-700 hover:bg-<?php echo $primaryColor; ?>-50 rounded font-medium">
                            <i class="fas fa-shopping-cart w-5 mr-2"></i>
                            <span>Заказы</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $basePath; ?>inventory.php" class="flex items-center p-2 text-gray-700 hover:bg-<?php echo $primaryColor; ?>-50 rounded font-medium">
                            <i class="fas fa-boxes w-5 mr-2"></i>
                            <span>Склад</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if ($role === 'warehouse'): ?>
                    <li>
                        <a href="<?php echo $basePath; ?>inventory.php" class="flex items-center p-2 text-gray-700 hover:bg-<?php echo $primaryColor; ?>-50 rounded font-medium">
                            <i class="fas fa-boxes w-5 mr-2"></i>
                            <span>Инвентаризация</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $basePath; ?>receive.php" class="flex items-center p-2 text-gray-700 hover:bg-<?php echo $primaryColor; ?>-50 rounded font-medium">
                            <i class="fas fa-truck-loading w-5 mr-2"></i>
                            <span>Приём товаров</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $basePath; ?>issue.php" class="flex items-center p-2 text-gray-700 hover:bg-<?php echo $primaryColor; ?>-50 rounded font-medium">
                            <i class="fas fa-dolly w-5 mr-2"></i>
                            <span>Выдача товаров</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $basePath; ?>transactions.php" class="flex items-center p-2 text-gray-700 hover:bg-<?php echo $primaryColor; ?>-50 rounded font-medium">
                            <i class="fas fa-exchange-alt w-5 mr-2"></i>
                            <span>Транзакции</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if ($role === 'admin'): ?>
                    <li>
                        <a href="<?php echo $basePath; ?>users.php" class="flex items-center p-2 text-gray-700 hover:bg-<?php echo $primaryColor; ?>-50 rounded font-medium">
                            <i class="fas fa-users w-5 mr-2"></i>
                            <span>Пользователи</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $basePath; ?>cameras.php" class="flex items-center p-2 text-gray-700 hover:bg-<?php echo $primaryColor; ?>-50 rounded font-medium">
                            <i class="fas fa-video w-5 mr-2"></i>
                            <span>Камеры</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $basePath; ?>warehouse.php" class="flex items-center p-2 text-gray-700 hover:bg-<?php echo $primaryColor; ?>-50 rounded font-medium">
                            <i class="fas fa-boxes w-5 mr-2"></i>
                            <span>Склад</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $basePath; ?>purchasing.php" class="flex items-center p-2 text-gray-700 hover:bg-<?php echo $primaryColor; ?>-50 rounded font-medium">
                            <i class="fas fa-shopping-cart w-5 mr-2"></i>
                            <span>Закупки</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <li>
                        <a href="messages.php" class="flex items-center p-2 bg-<?php echo $primaryColor; ?>-100 text-<?php echo $primaryColor; ?>-700 rounded font-medium">
                            <i class="fas fa-envelope w-5 mr-2"></i>
                            <span>Сообщения</span>
                            <?php if ($unreadCount > 0): ?>
                            <span class="ml-auto bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                <?php echo $unreadCount; ?>
                            </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <?php if ($role === 'supplier'): ?>
                    <li>
                        <a href="<?php echo $basePath; ?>profile.php" class="flex items-center p-2 text-gray-700 hover:bg-<?php echo $primaryColor; ?>-50 rounded font-medium">
                            <i class="fas fa-user-cog w-5 mr-2"></i>
                            <span>Мой профиль</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <!-- Блок с кнопкой написать сообщение -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <button id="composeButton" class="w-full flex justify-center items-center px-4 py-2 bg-<?php echo $primaryColor; ?>-600 text-white rounded-md hover:bg-<?php echo $primaryColor; ?>-700">
                    <i class="fas fa-pen mr-2"></i> Написать сообщение
                </button>
            </div>
            
            <!-- Навигация по папкам -->
            <div class="bg-white rounded-lg shadow-md p-4">
                <h3 class="font-semibold text-lg mb-3">Папки</h3>
                <ul class="space-y-2">
                    <li>
                        <a href="#inbox" id="inboxTab" class="active-tab flex justify-between items-center p-2 text-gray-700 hover:bg-<?php echo $primaryColor; ?>-50 rounded font-medium">
                            <div>
                                <i class="fas fa-inbox w-5 mr-2"></i>
                                <span>Входящие</span>
                            </div>
                            <?php if ($unreadCount > 0): ?>
                            <span class="bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                <?php echo $unreadCount; ?>
                            </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="#sent" id="sentTab" class="flex justify-between items-center p-2 text-gray-700 hover:bg-<?php echo $primaryColor; ?>-50 rounded font-medium">
                            <div>
                                <i class="fas fa-paper-plane w-5 mr-2"></i>
                                <span>Отправленные</span>
                            </div>
                            <span class="text-gray-500 text-xs">
                                <?php echo count($sentMessages); ?>
                            </span>
                        </a>
                    </li>
                </ul>
            </div>
        </aside>
        
        <!-- Основной контент -->
        <main class="w-full md:w-3/4">
            <?php if (isset($_GET['view']) && $viewMessage): ?>
                <!-- Режим просмотра сообщения -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <div class="flex justify-between items-start mb-6">
                        <h2 class="text-2xl font-semibold text-gray-800">
                            <a href="messages.php" class="text-<?php echo $primaryColor; ?>-600 hover:text-<?php echo $primaryColor; ?>-800 mr-2">
                                <i class="fas fa-arrow-left"></i>
                            </a>
                            <?php echo htmlspecialchars($viewMessage['subject'] ? $viewMessage['subject'] : '(без темы)'); ?>
                        </h2>
                    </div>
                    
                    <!-- Информация о сообщении -->
                    <div class="bg-gray-50 p-4 rounded-lg mb-6">
                        <div class="flex justify-between mb-2">
                            <div>
                                <span class="text-gray-500">От:</span>
                                <span class="font-medium"><?php echo htmlspecialchars($viewMessage['sender_name']); ?></span>
                                <span class="text-gray-500">(<?php echo htmlspecialchars($viewMessage['sender_email']); ?>)</span>
                            </div>
                            <div class="text-gray-500">
                                <?php echo date('d.m.Y H:i', strtotime($viewMessage['created_at'])); ?>
                            </div>
                        </div>
                        <div>
                            <span class="text-gray-500">Кому:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($viewMessage['receiver_name']); ?></span>
                            <span class="text-gray-500">(<?php echo htmlspecialchars($viewMessage['receiver_email']); ?>)</span>
                        </div>
                    </div>
                    
                    <!-- Тело сообщения -->
                    <div class="prose max-w-none mb-6">
                        <?php echo nl2br(htmlspecialchars($viewMessage['message'])); ?>
                    </div>
                    
                    <!-- Кнопки действий -->
                    <div class="flex justify-end space-x-3">
                        <a href="messages.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 py-2 px-4 rounded">
                            <i class="fas fa-arrow-left mr-1"></i> Назад
                        </a>
                        <a href="?reply=<?php echo $viewMessage['id']; ?>" class="bg-<?php echo $primaryColor; ?>-600 hover:bg-<?php echo $primaryColor; ?>-700 text-white py-2 px-4 rounded">
                            <i class="fas fa-reply mr-1"></i> Ответить
                        </a>
                    </div>
                </div>
            <?php elseif (isset($_GET['reply']) && $replyToMessage || isset($_GET['compose'])): ?>
                <!-- Режим создания нового сообщения или ответа -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <div class="flex justify-between items-start mb-6">
                        <h2 class="text-2xl font-semibold text-gray-800">
                            <a href="messages.php" class="text-<?php echo $primaryColor; ?>-600 hover:text-<?php echo $primaryColor; ?>-800 mr-2">
                                <i class="fas fa-arrow-left"></i>
                            </a>
                            <?php echo $replyToMessage ? 'Ответ на сообщение' : 'Новое сообщение'; ?>
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
                    
                    <!-- Форма отправки сообщения -->
                    <form method="POST" action="">
                        <div class="mb-4">
                            <label for="receiver_id" class="block text-sm font-medium text-gray-700 mb-2">Получатель</label>
                            <select id="receiver_id" name="receiver_id" required
                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-<?php echo $primaryColor; ?>-500 focus:ring-<?php echo $primaryColor; ?>-500 sm:text-sm">
                                <option value="">Выберите получателя</option>
                                <?php 
                                // Группируем получателей по ролям
                                $groupedRecipients = [];
                                foreach ($recipients as $recipient) {
                                    $roleGroup = $recipient['role_name'] ?? $recipient['role'];
                                    if (!isset($groupedRecipients[$roleGroup])) {
                                        $groupedRecipients[$roleGroup] = [];
                                    }
                                    $groupedRecipients[$roleGroup][] = $recipient;
                                }
                                
                                // Выводим получателей сгруппированными
                                foreach ($groupedRecipients as $group => $groupRecipients):
                                ?>
                                <optgroup label="<?php echo htmlspecialchars($group); ?>">
                                    <?php foreach ($groupRecipients as $recipient): ?>
                                    <option value="<?php echo $recipient['id']; ?>" 
                                           <?php echo $replyToMessage && $recipient['id'] == $replyToMessage['sender_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($recipient['name']); ?>
                                        <?php if (isset($recipient['company_name'])): ?>
                                         (<?php echo htmlspecialchars($recipient['company_name']); ?>)
                                        <?php endif; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">Тема</label>
                            <input type="text" id="subject" name="subject" 
                                   value="<?php echo $replyToMessage ? 'Re: ' . htmlspecialchars($replyToMessage['subject']) : ''; ?>"
                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-<?php echo $primaryColor; ?>-500 focus:ring-<?php echo $primaryColor; ?>-500 sm:text-sm">
                        </div>
                        
                        <div class="mb-4">
                            <label for="message" class="block text-sm font-medium text-gray-700 mb-2">Сообщение</label>
                            <textarea id="message" name="message" rows="8" required
                                     class="block w-full rounded-md border-gray-300 shadow-sm focus:border-<?php echo $primaryColor; ?>-500 focus:ring-<?php echo $primaryColor; ?>-500 sm:text-sm"><?php 
                                     if ($replyToMessage) {
                                         echo "\n\n\n--- Исходное сообщение ---\nОт: " . $replyToMessage['sender_name'] . "\nДата: " . date('d.m.Y H:i', strtotime($replyToMessage['created_at'])) . "\n\n" . $replyToMessage['message'];
                                     }
                                     ?></textarea>
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <a href="messages.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 py-2 px-4 rounded">
                                Отмена
                            </a>
                            <button type="submit" name="send_message" class="bg-<?php echo $primaryColor; ?>-600 hover:bg-<?php echo $primaryColor; ?>-700 text-white py-2 px-4 rounded">
                                <i class="fas fa-paper-plane mr-1"></i> Отправить
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- Режим списка сообщений -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-semibold text-gray-800" id="messageListTitle">Входящие сообщения</h2>
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
                    
                    <!-- Форма написания сообщения (скрыта по умолчанию) -->
                    <div id="composeForm" class="hidden bg-gray-50 p-6 rounded-lg mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Новое сообщение</h3>
                        <form method="POST" action="">
                            <div class="mb-4">
                                <label for="compose_receiver_id" class="block text-sm font-medium text-gray-700 mb-2">Получатель</label>
                                <select id="compose_receiver_id" name="receiver_id" required
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-<?php echo $primaryColor; ?>-500 focus:ring-<?php echo $primaryColor; ?>-500 sm:text-sm">
                                    <option value="">Выберите получателя</option>
                                    <?php 
                                    // Группируем получателей по ролям
                                    $groupedRecipients = [];
                                    foreach ($recipients as $recipient) {
                                        $roleGroup = $recipient['role_name'] ?? $recipient['role'];
                                        if (!isset($groupedRecipients[$roleGroup])) {
                                            $groupedRecipients[$roleGroup] = [];
                                        }
                                        $groupedRecipients[$roleGroup][] = $recipient;
                                    }
                                    
                                    // Выводим получателей сгруппированными
                                    foreach ($groupedRecipients as $group => $groupRecipients):
                                    ?>
                                    <optgroup label="<?php echo htmlspecialchars($group); ?>">
                                        <?php foreach ($groupRecipients as $recipient): ?>
                                        <option value="<?php echo $recipient['id']; ?>">
                                            <?php echo htmlspecialchars($recipient['name']); ?>
                                            <?php if (isset($recipient['company_name'])): ?>
                                             (<?php echo htmlspecialchars($recipient['company_name']); ?>)
                                            <?php endif; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label for="compose_subject" class="block text-sm font-medium text-gray-700 mb-2">Тема</label>
                                <input type="text" id="compose_subject" name="subject" 
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-<?php echo $primaryColor; ?>-500 focus:ring-<?php echo $primaryColor; ?>-500 sm:text-sm">
                            </div>
                            
                            <div class="mb-4">
                                <label for="compose_message" class="block text-sm font-medium text-gray-700 mb-2">Сообщение</label>
                                <textarea id="compose_message" name="message" rows="6" required
                                         class="block w-full rounded-md border-gray-300 shadow-sm focus:border-<?php echo $primaryColor; ?>-500 focus:ring-<?php echo $primaryColor; ?>-500 sm:text-sm"></textarea>
                            </div>
                            
                            <div class="flex justify-end space-x-3">
                                <button type="button" id="cancelCompose" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Отмена
                                </button>
                                <button type="submit" name="send_message" class="bg-<?php echo $primaryColor; ?>-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-<?php echo $primaryColor; ?>-700">
                                    <i class="fas fa-paper-plane mr-1"></i> Отправить
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Список входящих сообщений -->
                    <div id="inboxMessages" class="space-y-4">
                        <?php if (empty($receivedMessages)): ?>
                        <p class="text-gray-500 text-center py-6">У вас нет входящих сообщений</p>
                        <?php else: ?>
                            <?php foreach ($receivedMessages as $msg): ?>
                            <div class="border-b border-gray-200 pb-4 <?php echo $msg['is_read'] ? 'bg-white' : 'bg-blue-50'; ?> p-4 rounded-lg">
                                <div class="flex justify-between mb-2">
                                    <div class="font-medium">
                                        <?php if (!$msg['is_read']): ?>
                                        <span class="inline-block w-2 h-2 rounded-full bg-blue-600 mr-2"></span>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($msg['sender_name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo date('d.m.Y H:i', strtotime($msg['created_at'])); ?>
                                    </div>
                                </div>
                                <a href="?view=<?php echo $msg['id']; ?>" class="block hover:bg-gray-50 rounded p-1">
                                    <h3 class="text-lg text-gray-900 mb-1"><?php echo htmlspecialchars($msg['subject'] ? $msg['subject'] : '(без темы)'); ?></h3>
                                    <p class="text-gray-500 truncate">
                                        <?php echo htmlspecialchars(substr($msg['message'], 0, 100) . (strlen($msg['message']) > 100 ? '...' : '')); ?>
                                    </p>
                                </a>
                                <div class="mt-2 flex justify-end space-x-2">
                                    <a href="?view=<?php echo $msg['id']; ?>" class="text-<?php echo $primaryColor; ?>-600 hover:text-<?php echo $primaryColor; ?>-800 text-sm">
                                        <i class="fas fa-eye mr-1"></i> Просмотр
                                    </a>
                                    <a href="?reply=<?php echo $msg['id']; ?>" class="text-<?php echo $primaryColor; ?>-600 hover:text-<?php echo $primaryColor; ?>-800 text-sm">
                                        <i class="fas fa-reply mr-1"></i> Ответить
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Список отправленных сообщений (скрыт по умолчанию) -->
                    <div id="sentMessages" class="hidden space-y-4">
                        <?php if (empty($sentMessages)): ?>
                        <p class="text-gray-500 text-center py-6">У вас нет отправленных сообщений</p>
                        <?php else: ?>
                            <?php foreach ($sentMessages as $msg): ?>
                            <div class="border-b border-gray-200 pb-4 bg-white p-4 rounded-lg">
                                <div class="flex justify-between mb-2">
                                    <div class="font-medium">
                                        Кому: <?php echo htmlspecialchars($msg['receiver_name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo date('d.m.Y H:i', strtotime($msg['created_at'])); ?>
                                    </div>
                                </div>
                                <a href="?view=<?php echo $msg['id']; ?>" class="block hover:bg-gray-50 rounded p-1">
                                    <h3 class="text-lg text-gray-900 mb-1"><?php echo htmlspecialchars($msg['subject'] ? $msg['subject'] : '(без темы)'); ?></h3>
                                    <p class="text-gray-500 truncate">
                                        <?php echo htmlspecialchars(substr($msg['message'], 0, 100) . (strlen($msg['message']) > 100 ? '...' : '')); ?>
                                    </p>
                                </a>
                                <div class="mt-2 flex justify-end">
                                    <a href="?view=<?php echo $msg['id']; ?>" class="text-<?php echo $primaryColor; ?>-600 hover:text-<?php echo $primaryColor; ?>-800 text-sm">
                                        <i class="fas fa-eye mr-1"></i> Просмотр
                                    </a>
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
            &copy; <?php echo date('Y'); ?> Винное производство. Система автоматизации процессов.
        </div>
    </footer>
    
    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Форма создания сообщения
            const composeButton = document.getElementById('composeButton');
            const cancelComposeBtn = document.getElementById('cancelCompose');
            const composeForm = document.getElementById('composeForm');
            
            if (composeButton && cancelComposeBtn && composeForm) {
                composeButton.addEventListener('click', function() {
                    composeForm.classList.remove('hidden');
                    composeButton.classList.add('hidden');
                });
                
                cancelComposeBtn.addEventListener('click', function() {
                    composeForm.classList.add('hidden');
                    composeButton.classList.remove('hidden');
                });
            }
            
            // Переключение между папками (входящие/отправленные)
            const inboxTab = document.getElementById('inboxTab');
            const sentTab = document.getElementById('sentTab');
            const inboxMessages = document.getElementById('inboxMessages');
            const sentMessages = document.getElementById('sentMessages');
            const messageListTitle = document.getElementById('messageListTitle');
            
            if (inboxTab && sentTab && inboxMessages && sentMessages && messageListTitle) {
                inboxTab.addEventListener('click', function(e) {
                    e.preventDefault();
                    inboxMessages.classList.remove('hidden');
                    sentMessages.classList.add('hidden');
                    inboxTab.classList.add('active-tab');
                    sentTab.classList.remove('active-tab');
                    messageListTitle.textContent = 'Входящие сообщения';
                });
                
                sentTab.addEventListener('click', function(e) {
                    e.preventDefault();
                    inboxMessages.classList.add('hidden');
                    sentMessages.classList.remove('hidden');
                    inboxTab.classList.remove('active-tab');
                    sentTab.classList.add('active-tab');
                    messageListTitle.textContent = 'Отправленные сообщения';
                });
            }
        });
    </script>
</body>
</html>