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
        $query = "SELECT 
                    COUNT(*) as total_users,
                    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
                    SUM(CASE WHEN role = 'warehouse' THEN 1 ELSE 0 END) as warehouse_count,
                    SUM(CASE WHEN role = 'purchasing' THEN 1 ELSE 0 END) as purchasing_count,
                    SUM(CASE WHEN role = 'supplier' THEN 1 ELSE 0 END) as supplier_count
                 FROM users";
        
        $result = $this->db->selectOne($query);
        return $result ?: [
            'total_users' => 0,
            'admin_count' => 0,
            'warehouse_count' => 0,
            'purchasing_count' => 0,
            'supplier_count' => 0
        ];
    }

    // Отримання статистики по інвентарю
    public function getInventoryStatistics() {
        $query = "SELECT 
                    COUNT(*) as total_products,
                    SUM(CASE WHEN category = 'raw_material' THEN 1 ELSE 0 END) as raw_material_count,
                    SUM(CASE WHEN category = 'packaging' THEN 1 ELSE 0 END) as packaging_count,
                    SUM(CASE WHEN category = 'finished_product' THEN 1 ELSE 0 END) as finished_product_count,
                    SUM(CASE WHEN quantity <= min_stock THEN 1 ELSE 0 END) as low_stock_count
                 FROM products";
        
        $result = $this->db->selectOne($query);
        return $result ?: [
            'total_products' => 0,
            'raw_material_count' => 0,
            'packaging_count' => 0,
            'finished_product_count' => 0,
            'low_stock_count' => 0
        ];
    }

    // Отримання списку всіх користувачів
    public function getAllUsers() {
        $query = "SELECT u.*, 
                 (SELECT COUNT(*) FROM inventory_transactions WHERE created_by = u.id) as transaction_count
                 FROM users u
                 ORDER BY u.role, u.name";
        
        return $this->db->select($query);
    }

    // Отримання інформації про конкретного користувача
    public function getUserById($userId) {
        $query = "SELECT u.*,
                 (SELECT COUNT(*) FROM inventory_transactions WHERE created_by = u.id) as transaction_count,
                 (SELECT COUNT(*) FROM orders WHERE created_by = u.id) as order_count,
                 (SELECT MAX(created_at) FROM inventory_transactions WHERE created_by = u.id) as last_activity
                 FROM users u
                 WHERE u.id = ?";
        
        return $this->db->selectOne($query, [$userId]);
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
            
            // Якщо роль - постачальник, створюємо запис у таблиці suppliers
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
        
        // Перевіряємо унікальність логіну та email
        $checkQuery = "SELECT * FROM users WHERE (username = ? OR email = ?) AND id != ?";
        $existingUser = $this->db->selectOne($checkQuery, [$userData['username'], $userData['email'], $userId]);
        
        if ($existingUser) {
            return [
                'success' => false,
                'message' => 'Користувач з таким логіном або email вже існує'
            ];
        }
        
        // Формуємо базовий запит оновлення
        $updateFields = [];
        $updateParams = [];
        
        if (isset($userData['username'])) {
            $updateFields[] = "username = ?";
            $updateParams[] = $userData['username'];
        }
        
        if (isset($userData['name'])) {
            $updateFields[] = "name = ?";
            $updateParams[] = $userData['name'];
        }
        
        if (isset($userData['email'])) {
            $updateFields[] = "email = ?";
            $updateParams[] = $userData['email'];
        }
        
        if (isset($userData['role'])) {
            $updateFields[] = "role = ?";
            $updateParams[] = $userData['role'];
        }
        
        if (!empty($userData['password'])) {
            $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
            $updateFields[] = "password = ?";
            $updateParams[] = $hashedPassword;
        }
        
        if (empty($updateFields)) {
            return [
                'success' => false,
                'message' => 'Немає даних для оновлення'
            ];
        }
        
        // Додаємо ID в параметри
        $updateParams[] = $userId;
        
        // Виконуємо оновлення
        $updateQuery = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $result = $this->db->execute($updateQuery, $updateParams);
        
        if ($result) {
            // Якщо користувач - постачальник, оновлюємо дані постачальника
            if ($userData['role'] === 'supplier' && isset($userData['supplier'])) {
                // Перевіряємо, чи є вже запис постачальника
                $supplierQuery = "SELECT * FROM suppliers WHERE user_id = ?";
                $supplier = $this->db->selectOne($supplierQuery, [$userId]);
                
                if ($supplier) {
                    // Оновлюємо існуючого постачальника
                    $updateSupplierQuery = "UPDATE suppliers SET 
                                          company_name = ?, contact_person = ?, phone = ?, address = ?
                                          WHERE user_id = ?";
                    
                    $this->db->execute($updateSupplierQuery, [
                        $userData['supplier']['company_name'],
                        $userData['supplier']['contact_person'],
                        $userData['supplier']['phone'],
                        $userData['supplier']['address'],
                        $userId
                    ]);
                } else {
                    // Створюємо нового постачальника
                    $insertSupplierQuery = "INSERT INTO suppliers (user_id, company_name, contact_person, phone, address) 
                                          VALUES (?, ?, ?, ?, ?)";
                    
                    $this->db->execute($insertSupplierQuery, [
                        $userId,
                        $userData['supplier']['company_name'],
                        $userData['supplier']['contact_person'],
                        $userData['supplier']['phone'],
                        $userData['supplier']['address']
                    ]);
                }
            }
            
            return [
                'success' => true,
                'message' => 'Дані користувача успішно оновлені'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Помилка при оновленні даних користувача'
            ];
        }
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
        $query = "SELECT * FROM cameras ORDER BY name";
        return $this->db->select($query);
    }

    // Додавання нової камери
    public function addCamera($name, $location, $streamUrl) {
        $query = "INSERT INTO cameras (name, location, stream_url) VALUES (?, ?, ?)";
        $result = $this->db->execute($query, [$name, $location, $streamUrl]);
        
        if ($result) {
            return [
                'success' => true,
                'message' => 'Камера успішно додана',
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
                'message' => 'Дані камери успішно оновлені'
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
                'message' => 'Камера успішно видалена'
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
        $query = "SELECT it.*, p.name as product_name, p.unit, u.name as user_name 
                 FROM inventory_transactions it
                 JOIN products p ON it.product_id = p.id
                 JOIN users u ON it.created_by = u.id
                 ORDER BY it.created_at DESC
                 LIMIT ?";
        
        return $this->db->select($query, [$limit]);
    }

    // Отримання найбільш активних користувачів
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
        // В даній реалізації просто повертаємо успішний результат,
        // в реальності могли б зберігати відхилені сповіщення в базі
        return [
            'success' => true,
            'message' => 'Сповіщення позначено як вирішене'
        ];
    }

    // Резервне копіювання бази даних (фіктивна функція)
    public function createBackup() {
        // В реальності тут був би код для створення дампу бази даних
        // В даній реалізації просто повертаємо успішний результат
        return [
            'success' => true,
            'message' => 'Резервна копія успішно створена',
            'filename' => 'backup_' . date('Y-m-d_H-i-s') . '.sql',
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
}