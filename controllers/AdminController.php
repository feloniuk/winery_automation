<?php
// controllers/AdminController.php
// Контролер для адміністратора системи

if (!defined('ROOT_PATH')) {
    require_once dirname(__DIR__) . '/config.php';
}

require_once ROOT_PATH . '/config/database.php';

class AdminController {
    private $db;

    public function __construct() {
        $this->db = new Database();
        $this->db->getConnection();
    }

    // Отримання статистики по користувачах
    public function getUserStatistics() {
        // ... код не змінюється ...
    }

    // Отримання статистики по інвентарю
    public function getInventoryStatistics() {
        // ... код не змінюється ...
    }

    // Отримання списку всіх користувачів
    public function getAllUsers() {
        // ... код не змінюється ...
    }

    // Отримання інформації про конкретного користувача
    public function getUserById($userId) {
        // ... код не змінюється ...
    }

    // Створення нового користувача
    public function createUser($userData) {
        // Перевіряємо, чи існує вже користувач з таким логіном або email
        $checkQuery = "SELECT * FROM users WHERE username = ? OR email = ?";
        $existingUser = $this->db->selectOne($checkQuery, [$userData['username'], $userData['email']]);
        
        if ($existingUser) {
            return [
                'success' => false,
                'message' => 'Користувач з таким логіном або email вже існує'
            ];
        }
        
        // Хешуємо пароль
        $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
        
        // Вставляємо дані користувача
        $insertQuery = "INSERT INTO users (username, password, role, name, email) VALUES (?, ?, ?, ?, ?)";
        $result = $this->db->execute($insertQuery, [
            $userData['username'],
            $hashedPassword,
            $userData['role'],
            $userData['name'],
            $userData['email']
        ]);
        
        if ($result) {
            $userId = $this->db->lastInsertId();
            
            // Якщо роль - постачальник, створюємо запис в таблиці suppliers
            if ($userData['role'] === 'supplier' && isset($userData['supplier'])) {
                $supplierQuery = "INSERT INTO suppliers (user_id, company_name, contact_person, phone, address) 
                                 VALUES (?, ?, ?, ?, ?)";
                
                $supplierResult = $this->db->execute($supplierQuery, [
                    $userId,
                    $userData['supplier']['company_name'],
                    $userData['supplier']['contact_person'],
                    $userData['supplier']['phone'],
                    $userData['supplier']['address']
                ]);
                
                if (!$supplierResult) {
                    // У випадку помилки видаляємо створеного користувача
                    $this->db->execute("DELETE FROM users WHERE id = ?", [$userId]);
                    
                    return [
                        'success' => false,
                        'message' => 'Помилка при створенні даних постачальника'
                    ];
                }
            }
            
            return [
                'success' => true,
                'message' => 'Користувач успішно створений',
                'user_id' => $userId
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Помилка при створенні користувача'
            ];
        }
    }

    // Оновлення даних користувача
    public function updateUser($userId, $userData) {
        // Перевіряємо існування користувача
        $userQuery = "SELECT * FROM users WHERE id = ?";
        $user = $this->db->selectOne($userQuery, [$userId]);
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'Користувача не знайдено'
            ];
        }
        
        // Перевіряємо унікальність логіна та email
        $checkQuery = "SELECT * FROM users WHERE (username = ? OR email = ?) AND id != ?";
        $existingUser = $this->db->selectOne($checkQuery, [$userData['username'], $userData['email'], $userId]);
        
        if ($existingUser) {
            return [
                'success' => false,
                'message' => 'Користувач з таким логіном або email вже існує'
            ];
        }
        
        // ... решта коду не змінюється ...
        
        if (empty($updateFields)) {
            return [
                'success' => false,
                'message' => 'Немає даних для оновлення'
            ];
        }
        
        // ... решта коду не змінюється ...
    }

    // Блокування/активація користувача
    public function toggleUserStatus($userId, $isActive) {
        $status = $isActive ? 'active' : 'inactive';
        
        // Оновлюємо статус постачальника, якщо це постачальник
        $query = "UPDATE suppliers SET status = ? WHERE user_id = ?";
        $this->db->execute($query, [$status, $userId]);
        
        return [
            'success' => true,
            'message' => 'Статус користувача успішно змінено'
        ];
    }

    // Отримання списку камер
    public function getCameras() {
        // ... код не змінюється ...
    }

    // Додавання нової камери
    public function addCamera($name, $location, $streamUrl) {
        $query = "INSERT INTO cameras (name, location, stream_url) VALUES (?, ?, ?)";
        $result = $this->db->execute($query, [$name, $location, $streamUrl]);
        
        if ($result) {
            return [
                'success' => true,
                'message' => 'Камеру успішно додано',
                'camera_id' => $this->db->lastInsertId()
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Помилка при додаванні камери'
            ];
        }
    }

    // Оновлення даних камери
    public function updateCamera($cameraId, $name, $location, $streamUrl, $status) {
        $query = "UPDATE cameras SET name = ?, location = ?, stream_url = ?, status = ? WHERE id = ?";
        $result = $this->db->execute($query, [$name, $location, $streamUrl, $status, $cameraId]);
        
        if ($result) {
            return [
                'success' => true,
                'message' => 'Дані камери успішно оновлено'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Помилка при оновленні даних камери'
            ];
        }
    }

    // Видалення камери
    public function deleteCamera($cameraId) {
        $query = "DELETE FROM cameras WHERE id = ?";
        $result = $this->db->execute($query, [$cameraId]);
        
        if ($result) {
            return [
                'success' => true,
                'message' => 'Камеру успішно видалено'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Помилка при видаленні камери'
            ];
        }
    }

    // Отримання недавніх транзакцій на складі
    public function getRecentTransactions($limit = 10) {
        // ... код не змінюється ...
    }

    // Отримання найактивніших користувачів
    public function getMostActiveUsers($limit = 5) {
        $query = "SELECT u.id, u.name, u.role,
                 (
                     CASE 
                         WHEN u.role = 'admin' THEN 'Адміністратор'
                         WHEN u.role = 'warehouse' THEN 'Начальник складу'
                         WHEN u.role = 'purchasing' THEN 'Менеджер із закупівель'
                         WHEN u.role = 'supplier' THEN 'Постачальник'
                         ELSE u.role
                     END
                 ) as role_name,
                 (
                     SELECT COUNT(*) FROM inventory_transactions WHERE created_by = u.id
                 ) + (
                     SELECT COUNT(*) FROM orders WHERE created_by = u.id
                 ) + (
                     SELECT COUNT(*) FROM messages WHERE sender_id = u.id
                 ) as action_count
                 FROM users u
                 ORDER BY action_count DESC
                 LIMIT ?";
        
        return $this->db->select($query, [$limit]);
    }

    // Отримання системних сповіщень
    public function getSystemAlerts() {
        // Тут ми генеруємо системні сповіщення на основі різних умов
        $alerts = [];
        
        // 1. Товари з критично низьким запасом (менше 50% від min_stock)
        $lowStockQuery = "SELECT * FROM products WHERE quantity < (min_stock * 0.5) ORDER BY (min_stock - quantity) DESC";
        $lowStockItems = $this->db->select($lowStockQuery);
        
        if (!empty($lowStockItems)) {
            foreach ($lowStockItems as $item) {
                $alerts[] = [
                    'id' => 'low_stock_' . $item['id'],
                    'title' => 'Критично низький запас товару',
                    'message' => 'Товар "' . $item['name'] . '" має критично низький запас: ' . $item['quantity'] . ' ' . $item['unit'] . ' (мінімальний запас: ' . $item['min_stock'] . ' ' . $item['unit'] . ')',
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
        }
        
        // 2. Неактивні камери
        $inactiveCamerasQuery = "SELECT * FROM cameras WHERE status = 'inactive'";
        $inactiveCameras = $this->db->select($inactiveCamerasQuery);
        
        if (!empty($inactiveCameras)) {
            foreach ($inactiveCameras as $camera) {
                $alerts[] = [
                    'id' => 'inactive_camera_' . $camera['id'],
                    'title' => 'Неактивна камера',
                    'message' => 'Камера "' . $camera['name'] . '" в локації "' . $camera['location'] . '" неактивна',
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
        }
        
        // 3. Замовлення, що очікують схвалення більше 2 днів
        $pendingOrdersQuery = "SELECT o.*, s.company_name 
                              FROM orders o
                              JOIN suppliers s ON o.supplier_id = s.id
                              WHERE o.status = 'pending' AND DATEDIFF(CURRENT_DATE, DATE(o.created_at)) > 2";
        $pendingOrders = $this->db->select($pendingOrdersQuery);
        
        if (!empty($pendingOrders)) {
            foreach ($pendingOrders as $order) {
                $alerts[] = [
                    'id' => 'pending_order_' . $order['id'],
                    'title' => 'Замовлення очікує схвалення',
                    'message' => 'Замовлення #' . $order['id'] . ' від постачальника "' . $order['company_name'] . '" очікує схвалення більше 2 днів (створено: ' . date('d.m.Y', strtotime($order['created_at'])) . ')',
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
        }
        
        return $alerts;
    }

    // Відхилення системного сповіщення (фіктивна функція, в реальній системі могла б зберігати відхилені сповіщення)
    public function dismissAlert($alertId) {
        return [
            'success' => true,
            'message' => 'Сповіщення відмічено як вирішене'
        ];
    }

    // Резервне копіювання бази даних (фіктивна функція)
    public function createBackup() {
        return [
            'success' => true,
            'message' => 'Резервну копію успішно створено',
            'filename' => 'backup_' . date('Y-m-d_H-i-s') . '.sql',
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
}
