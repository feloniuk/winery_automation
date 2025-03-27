<?php
// views/common/messages.php
// Універсальна сторінка обміну повідомленнями для всіх ролей

// Підключення контролерів
require_once '../../controllers/AuthController.php';

$authController = new AuthController();

// Перевірка авторизації
if (!$authController->isLoggedIn()) {
    header('Location: ../../index.php');
    exit;
}

// Визначення ролі та завантаження відповідного контролера
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

// Визначення кольорів та заголовка залежно від ролі
switch ($role) {
    case 'supplier':
        $primaryColor = 'amber';
        $headerTitle = 'Постачальник';
        $basePath = '../supplier/';
        break;
    case 'purchasing':
        $primaryColor = 'teal';
        $headerTitle = 'Менеджер із закупівель';
        $basePath = '../purchasing/';
        break;
    case 'warehouse':
        $primaryColor = 'purple';
        $headerTitle = 'Начальник складу';
        $basePath = '../warehouse/';
        break;
    case 'admin':
        $primaryColor = 'indigo';
        $headerTitle = 'Адміністратор';
        $basePath = '../admin/';
        break;
    default:
        $primaryColor = 'gray';
        $headerTitle = 'Користувач';
        $basePath = '../';
}

// Отримання користувачів для надсилання повідомлень
$recipients = [];
if (isset($controller) && method_exists($controller, 'getPotentialMessageRecipients')) {
    $recipients = $controller->getPotentialMessageRecipients();
}

// Обробка дій
$message = '';
$error = '';
$viewMessage = null;
$replyToMessage = null;

// Режим перегляду повідомлення
if (isset($_GET['view']) && !empty($_GET['view'])) {
    $messageId = $_GET['view'];
    $viewMessage = $controller->getMessage($messageId, $currentUser['id']);
}

// Режим відповіді на повідомлення
if (isset($_GET['reply']) && !empty($_GET['reply'])) {
    $messageId = $_GET['reply'];
    $replyToMessage = $controller->getMessage($messageId, $currentUser['id']);
}

// Обробка відправки форми
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Відправка повідомлення
    if (isset($_POST['send_message'])) {
        $receiverId = $_POST['receiver_id'] ?? '';
        $subject = $_POST['subject'] ?? '';
        $content = $_POST['message'] ?? '';
        
        if (empty($receiverId)) {
            $error = "Необхідно вибрати отримувача";
        } elseif (empty($content)) {
            $error = "Текст повідомлення не може бути порожнім";
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

// Отримання всіх повідомлень користувача
$allMessages = $controller->getAllMessages($currentUser['id']);

// Розділення на вхідні та вихідні
$receivedMessages = array_filter($allMessages, function($msg) {
    return $msg['type'] === 'received';
});

$sentMessages = array_filter($allMessages, function($msg) {
    return $msg['type'] === 'sent';
});

// Підрахунок непрочитаних повідомлень
$unreadCount = count(array_filter($receivedMessages, function($msg) {
    return $msg['is_read'] == 0;
}));
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Повідомлення - Винне виробництво</title>
    <!-- Підключення Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Іконки -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Верхня навігаційна панель -->
    <nav class="bg-<?php echo $primaryColor; ?>-800 text-white p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center">
                <i class="fas fa-wine-bottle text-2xl mr-3"></i>
                <h1 class="text-xl font-bold">Винне виробництво</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span><?php echo htmlspecialchars($currentUser['name']); ?> (<?php echo $headerTitle; ?>)</span>
                <a href="../../controllers/logout.php" class="bg-<?php echo $primaryColor; ?>-700 hover:bg-<?php echo $primaryColor; ?>-600 py-2 px-4 rounded text-sm">
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
                            <span>Панель керування</span>
                        </a>
                    </li>
                    
                    <?php if ($role === 'supplier'): ?>
                    <li>
                        <a href="<?php echo $basePath; ?>orders.php" class="flex items-center p-2 text-gray-700 hover:bg-<?php echo $primaryColor; ?>-50 rounded font-medium">
                            <i class="fas fa-shopping-cart w-5 mr-2"></i>
                            <span>Замовлення</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $basePath; ?>products.php" class="flex items-center p-2 text-gray-700 hover:bg-<?php echo $primaryColor; ?>-50 rounded font-medium">
                            <i class="fas fa-boxes w-5 mr-2"></i>
                            <span>Мої товари</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if ($role === 'purchasing'): ?>
                    <li>
                        <a href="<?php echo $basePath; ?>suppliers.php" class="flex items-center p-2 text-gray-700 hover:bg-<?php echo $primaryColor; ?>-50 rounded font-medium">
                            <i class="fas fa-truck w-5 mr-2"></i>
                            <span>Постачальники</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $basePath; ?>orders.php" class="flex items-center p-2 text-gray-700 hover:bg-<?php echo $primaryColor; ?>-50 rounded font-medium">
                            <i class="fas fa-shopping-cart w-5 mr-2"></i>
                            <span>Замовлення</span>
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
                            <span>Інвентаризація</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $basePath; ?>receive.php" class="flex items-center p-2 text-gray-700 hover:bg-<?php echo $primaryColor; ?>-50 rounded font-medium">
                            <i class="fas fa-truck-loading w-5 mr-2"></i>
                            <span>Прийом товарів</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $basePath; ?>issue.php" class="flex items-center p-2 text-gray-700 hover:bg-<?php echo $primaryColor; ?>-50 rounded font-medium">
                            <i class="fas fa-dolly w-5 mr-2"></i>
                            <span>Видача товарів</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $basePath; ?>transactions.php" class="flex items-center p-2 text-gray-700 hover:bg-<?php echo $primaryColor; ?>-50 rounded font-medium">
                            <i class="fas fa-exchange-alt w-5 mr-2"></i>
                            <span>Транзакції</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if ($role === 'admin'): ?>
                    <li>
                        <a href="<?php echo $basePath; ?>users.php" class="flex items-center p-2 text-gray-700 hover:bg-<?php echo $primaryColor; ?>-50 rounded font-medium">
                            <i class="fas fa-users w-5 mr-2"></i>
                            <span>Користувачі</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $basePath; ?>cameras.php" class="flex items-center p-2 text-gray-700 hover:bg-<?php echo $primaryColor; ?>-50 rounded font-medium">
                            <i class="fas fa-video w-5 mr-2"></i>
                            <span>Камери</span>
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
                            <span>Закупівлі</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <li>
                        <a href="messages.php" class="flex items-center p-2 bg-<?php echo $primaryColor; ?>-100 text-<?php echo $primaryColor; ?>-700 rounded font-medium">
                            <i class="fas fa-envelope w-5 mr-2"></i>
                            <span>Повідомлення</span>
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
                            <span>Мій профіль</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <!-- Блок з кнопкою написати повідомлення -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <button id="composeButton" class="w-full flex justify-center items-center px-4 py-2 bg-<?php echo $primaryColor; ?>-600 text-white rounded-md hover:bg-<?php echo $primaryColor; ?>-700">
                    <i class="fas fa-pen mr-2"></i> Написати повідомлення
                </button>
            </div>
            
            <!-- Навігація по папках -->
            <div class="bg-white rounded-lg shadow-md p-4">
                <h3 class="font-semibold text-lg mb-3">Папки</h3>
                <ul class="space-y-2">
                    <li>
                        <a href="#inbox" id="inboxTab" class="active-tab flex justify-between items-center p-2 text-gray-700 hover:bg-<?php echo $primaryColor; ?>-50 rounded font-medium">
                            <div>
                                <i class="fas fa-inbox w-5 mr-2"></i>
                                <span>Вхідні</span>
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
                                <span>Надіслані</span>
                            </div>
                            <span class="text-gray-500 text-xs">
                                <?php echo count($sentMessages); ?>
                            </span>
                        </a>
                    </li>
                </ul>
            </div>
        </aside>
        
        <!-- Основний контент -->
        <main class="w-full md:w-3/4">
            <?php if (isset($_GET['view']) && $viewMessage): ?>
                <!-- Режим перегляду повідомлення -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <div class="flex justify-between items-start mb-6">
                        <h2 class="text-2xl font-semibold text-gray-800">
                            <a href="messages.php" class="text-<?php echo $primaryColor; ?>-600 hover:text-<?php echo $primaryColor; ?>-800 mr-2">
                                <i class="fas fa-arrow-left"></i>
                            </a>
                            <?php echo htmlspecialchars($viewMessage['subject'] ? $viewMessage['subject'] : '(без теми)'); ?>
                        </h2>
                    </div>
                    
                    <!-- Інформація про повідомлення -->
                    <div class="bg-gray-50 p-4 rounded-lg mb-6">
                        <div class="flex justify-between mb-2">
                            <div>
                                <span class="text-gray-500">Від:</span>
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
                    
                    <!-- Тіло повідомлення -->
                    <div class="prose max-w-none mb-6">
                        <?php echo nl2br(htmlspecialchars($viewMessage['message'])); ?>
                    </div>
                    
                    <!-- Кнопки дій -->
                    <div class="flex justify-end space-x-3">
                        <a href="messages.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 py-2 px-4 rounded">
                            <i class="fas fa-arrow-left mr-1"></i> Назад
                        </a>
                        <a href="?reply=<?php echo $viewMessage['id']; ?>" class="bg-<?php echo $primaryColor; ?>-600 hover:bg-<?php echo $primaryColor; ?>-700 text-white py-2 px-4 rounded">
                            <i class="fas fa-reply mr-1"></i> Відповісти
                        </a>
                    </div>
                </div>
            <?php elseif (isset($_GET['reply']) && $replyToMessage || isset($_GET['compose'])): ?>
                <!-- Режим створення нового повідомлення або відповіді -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <div class="flex justify-between items-start mb-6">
                        <h2 class="text-2xl font-semibold text-gray-800">
                            <a href="messages.php" class="text-<?php echo $primaryColor; ?>-600 hover:text-<?php echo $primaryColor; ?>-800 mr-2">
                                <i class="fas fa-arrow-left"></i>
                            </a>
                            <?php echo $replyToMessage ? 'Відповідь на повідомлення' : 'Нове повідомлення'; ?>
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
                    
                    <!-- Форма надсилання повідомлення -->
                    <form method="POST" action="">
                        <div class="mb-4">
                            <label for="receiver_id" class="block text-sm font-medium text-gray-700 mb-2">Отримувач</label>
                            <select id="receiver_id" name="receiver_id" required
                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-<?php echo $primaryColor; ?>-500 focus:ring-<?php echo $primaryColor; ?>-500 sm:text-sm">
                                <option value="">Виберіть отримувача</option>
                                <?php 
                                // Групуємо отримувачів за ролями
                                $groupedRecipients = [];
                                foreach ($recipients as $recipient) {
                                    $roleGroup = $recipient['role_name'] ?? $recipient['role'];
                                    if (!isset($groupedRecipients[$roleGroup])) {
                                        $groupedRecipients[$roleGroup] = [];
                                    }
                                    $groupedRecipients[$roleGroup][] = $recipient;
                                }
                                
                                // Виводимо отримувачів згрупованими
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
                            <label for="message" class="block text-sm font-medium text-gray-700 mb-2">Повідомлення</label>
                            <textarea id="message" name="message" rows="8" required
                                     class="block w-full rounded-md border-gray-300 shadow-sm focus:border-<?php echo $primaryColor; ?>-500 focus:ring-<?php echo $primaryColor; ?>-500 sm:text-sm"><?php 
                                     if ($replyToMessage) {
                                         echo "\n\n\n--- Початкове повідомлення ---\nВід: " . $replyToMessage['sender_name'] . "\nДата: " . date('d.m.Y H:i', strtotime($replyToMessage['created_at'])) . "\n\n" . $replyToMessage['message'];
                                     }
                                     ?></textarea>
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <a href="messages.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 py-2 px-4 rounded">
                                Скасувати
                            </a>
                            <button type="submit" name="send_message" class="bg-<?php echo $primaryColor; ?>-600 hover:bg-<?php echo $primaryColor; ?>-700 text-white py-2 px-4 rounded">
                                <i class="fas fa-paper-plane mr-1"></i> Надіслати
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- Режим списку повідомлень -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-semibold text-gray-800" id="messageListTitle">Вхідні повідомлення</h2>
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
                    
                    <!-- Форма написання повідомлення (прихована за замовчуванням) -->
                    <div id="composeForm" class="hidden bg-gray-50 p-6 rounded-lg mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Нове повідомлення</h3>
                        <form method="POST" action="">
                            <div class="mb-4">
                                <label for="compose_receiver_id" class="block text-sm font-medium text-gray-700 mb-2">Отримувач</label>
                                <select id="compose_receiver_id" name="receiver_id" required
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-<?php echo $primaryColor; ?>-500 focus:ring-<?php echo $primaryColor; ?>-500 sm:text-sm">
                                    <option value="">Виберіть отримувача</option>
                                    <?php 
                                    // Групуємо отримувачів за ролями
                                    $groupedRecipients = [];
                                    foreach ($recipients as $recipient) {
                                        $roleGroup = $recipient['role_name'] ?? $recipient['role'];
                                        if (!isset($groupedRecipients[$roleGroup])) {
                                            $groupedRecipients[$roleGroup] = [];
                                        }
                                        $groupedRecipients[$roleGroup][] = $recipient;
                                    }
                                    
                                    // Виводимо отримувачів згрупованими
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
                                <label for="compose_message" class="block text-sm font-medium text-gray-700 mb-2">Повідомлення</label>
                                <textarea id="compose_message" name="message" rows="6" required
                                         class="block w-full rounded-md border-gray-300 shadow-sm focus:border-<?php echo $primaryColor; ?>-500 focus:ring-<?php echo $primaryColor; ?>-500 sm:text-sm"></textarea>
                            </div>
                            
                            <div class="flex justify-end space-x-3">
                                <button type="button" id="cancelCompose" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Скасувати
                                </button>
                                <button type="submit" name="send_message" class="bg-<?php echo $primaryColor; ?>-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-<?php echo $primaryColor; ?>-700">
                                    <i class="fas fa-paper-plane mr-1"></i> Надіслати
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Список вхідних повідомлень -->
                    <div id="inboxMessages" class="space-y-4">
                        <?php if (empty($receivedMessages)): ?>
                        <p class="text-gray-500 text-center py-6">У вас немає вхідних повідомлень</p>
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
                                    <h3 class="text-lg text-gray-900 mb-1"><?php echo htmlspecialchars($msg['subject'] ? $msg['subject'] : '(без теми)'); ?></h3>
                                    <p class="text-gray-500 truncate">
                                        <?php echo htmlspecialchars(substr($msg['message'], 0, 100) . (strlen($msg['message']) > 100 ? '...' : '')); ?>
                                    </p>
                                </a>
                                <div class="mt-2 flex justify-end space-x-2">
                                    <a href="?view=<?php echo $msg['id']; ?>" class="text-<?php echo $primaryColor; ?>-600 hover:text-<?php echo $primaryColor; ?>-800 text-sm">
                                        <i class="fas fa-eye mr-1"></i> Перегляд
                                    </a>
                                    <a href="?reply=<?php echo $msg['id']; ?>" class="text-<?php echo $primaryColor; ?>-600 hover:text-<?php echo $primaryColor; ?>-800 text-sm">
                                        <i class="fas fa-reply mr-1"></i> Відповісти
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Список надісланих повідомлень (прихований за замовчуванням) -->
                    <div id="sentMessages" class="hidden space-y-4">
                        <?php if (empty($sentMessages)): ?>
                        <p class="text-gray-500 text-center py-6">У вас немає надісланих повідомлень</p>
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
                                    <h3 class="text-lg text-gray-900 mb-1"><?php echo htmlspecialchars($msg['subject'] ? $msg['subject'] : '(без теми)'); ?></h3>
                                    <p class="text-gray-500 truncate">
                                        <?php echo htmlspecialchars(substr($msg['message'], 0, 100) . (strlen($msg['message']) > 100 ? '...' : '')); ?>
                                    </p>
                                </a>
                                <div class="mt-2 flex justify-end">
                                    <a href="?view=<?php echo $msg['id']; ?>" class="text-<?php echo $primaryColor; ?>-600 hover:text-<?php echo $primaryColor; ?>-800 text-sm">
                                        <i class="fas fa-eye mr-1"></i> Перегляд
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
            &copy; <?php echo date('Y'); ?> Винне виробництво. Система автоматизації процесів.
        </div>
    </footer>
    
    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Форма створення повідомлення
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
            
            // Перемикання між папками (вхідні/надіслані)
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
                    messageListTitle.textContent = 'Вхідні повідомлення';
                });
                
                sentTab.addEventListener('click', function(e) {
                    e.preventDefault();
                    inboxMessages.classList.add('hidden');
                    sentMessages.classList.remove('hidden');
                    inboxTab.classList.remove('active-tab');
                    sentTab.classList.add('active-tab');
                    messageListTitle.textContent = 'Надіслані повідомлення';
                });
            }
        });
    </script>
</body>
</html>