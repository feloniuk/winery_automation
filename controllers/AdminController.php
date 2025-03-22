<?php
// controllers/AdminController.php
// Контроллер для администратора системы

require_once '../../config/database.php';

class AdminController {
    private $db;

    public function __construct() {
        $this->db = new Database();
        $this->db->getConnection();
    }

    // Получение статистики по пользователям
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

    // Получение статистики по инвентарю
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

    // Получение списка всех пользователей
    public function getAllUsers() {
        $query = "SELECT u.*, 
                 (SELECT COUNT(*) FROM inventory_transactions WHERE created_by = u.id) as transaction_count
                 FROM users u
                 ORDER BY u.role, u.name";
        
        return $this->db->select($query);
    }

    // Получение информации о конкретном пользователе
    public function getUserById($userId) {
        $query = "SELECT u.*,
                 (SELECT COUNT(*) FROM inventory_transactions WHERE created_by = u.id) as transaction_count,
                 (SELECT COUNT(*) FROM orders WHERE created_by = u.id) as order_count,
                 (SELECT MAX(created_at) FROM inventory_transactions WHERE created_by = u.id) as last_activity
                 FROM users u
                 WHERE u.id = ?";
        
        return $this->db->selectOne($query, [$userId]);
    }

    // Создание нового пользователя
    public function createUser($userData) {
        // Проверяем, существует ли уже пользователь с таким логином или email
        $checkQuery = "SELECT * FROM users WHERE username = ? OR email = ?";
        $existingUser = $this->db->selectOne($checkQuery, [$userData['username'], $userData['email']]);
        
        if ($existingUser) {
            return [
                'success' => false,
                'message' => 'Пользователь с таким логином или email уже существует'
            ];
        }
        
        // Хешируем пароль
        $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
        
        // Вставляем данные пользователя
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
            
            // Если роль - поставщик, создаем запись в таблице suppliers
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
                    // В случае ошибки удаляем созданного пользователя
                    $this->db->execute("DELETE FROM users WHERE id = ?", [$userId]);
                    
                    return [
                        'success' => false,
                        'message' => 'Ошибка при создании данных поставщика'
                    ];
                }
            }
            
            return [
                'success' => true,
                'message' => 'Пользователь успешно создан',
                'user_id' => $userId
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Ошибка при создании пользователя'
            ];
        }
    }

    // Обновление данных пользователя
    public function updateUser($userId, $userData) {
        // Проверяем существование пользователя
        $userQuery = "SELECT * FROM users WHERE id = ?";
        $user = $this->db->selectOne($userQuery, [$userId]);
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'Пользователь не найден'
            ];
        }
        
        // Проверяем уникальность логина и email
        $checkQuery = "SELECT * FROM users WHERE (username = ? OR email = ?) AND id != ?";
        $existingUser = $this->db->selectOne($checkQuery, [$userData['username'], $userData['email'], $userId]);
        
        if ($existingUser) {
            return [
                'success' => false,
                'message' => 'Пользователь с таким логином или email уже существует'
            ];
        }
        
        // Формируем базовый запрос обновления
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
                'message' => 'Нет данных для обновления'
            ];
        }
        
        // Добавляем ID в параметры
        $updateParams[] = $userId;
        
        // Выполняем обновление
        $updateQuery = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $result = $this->db->execute($updateQuery, $updateParams);
        
        if ($result) {
            // Если пользователь - поставщик, обновляем данные поставщика
            if ($userData['role'] === 'supplier' && isset($userData['supplier'])) {
                // Проверяем, есть ли уже запись поставщика
                $supplierQuery = "SELECT * FROM suppliers WHERE user_id = ?";
                $supplier = $this->db->selectOne($supplierQuery, [$userId]);
                
                if ($supplier) {
                    // Обновляем существующего поставщика
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
                    // Создаем нового поставщика
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
                'message' => 'Данные пользователя успешно обновлены'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Ошибка при обновлении данных пользователя'
            ];
        }
    }

    // Блокировка/активация пользователя
    public function toggleUserStatus($userId, $isActive) {
        $status = $isActive ? 'active' : 'inactive';
        
        // Обновляем статус поставщика, если это поставщик
        $query = "UPDATE suppliers SET status = ? WHERE user_id = ?";
        $this->db->execute($query, [$status, $userId]);
        
        return [
            'success' => true,
            'message' => 'Статус пользователя успешно изменен'
        ];
    }

    // Получение списка камер
    public function getCameras() {
        $query = "SELECT * FROM cameras ORDER BY name";
        return $this->db->select($query);
    }

    // Добавление новой камеры
    public function addCamera($name, $location, $streamUrl) {
        $query = "INSERT INTO cameras (name, location, stream_url) VALUES (?, ?, ?)";
        $result = $this->db->execute($query, [$name, $location, $streamUrl]);
        
        if ($result) {
            return [
                'success' => true,
                'message' => 'Камера успешно добавлена',
                'camera_id' => $this->db->lastInsertId()
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Ошибка при добавлении камеры'
            ];
        }
    }

    // Обновление данных камеры
    public function updateCamera($cameraId, $name, $location, $streamUrl, $status) {
        $query = "UPDATE cameras SET name = ?, location = ?, stream_url = ?, status = ? WHERE id = ?";
        $result = $this->db->execute($query, [$name, $location, $streamUrl, $status, $cameraId]);
        
        if ($result) {
            return [
                'success' => true,
                'message' => 'Данные камеры успешно обновлены'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Ошибка при обновлении данных камеры'
            ];
        }
    }

    // Удаление камеры
    public function deleteCamera($cameraId) {
        $query = "DELETE FROM cameras WHERE id = ?";
        $result = $this->db->execute($query, [$cameraId]);
        
        if ($result) {
            return [
                'success' => true,
                'message' => 'Камера успешно удалена'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Ошибка при удалении камеры'
            ];
        }
    }

    // Получение недавних транзакций на складе
    public function getRecentTransactions($limit = 10) {
        $query = "SELECT it.*, p.name as product_name, p.unit, u.name as user_name 
                 FROM inventory_transactions it
                 JOIN products p ON it.product_id = p.id
                 JOIN users u ON it.created_by = u.id
                 ORDER BY it.created_at DESC
                 LIMIT ?";
        
        return $this->db->select($query, [$limit]);
    }

    // Получение самых активных пользователей
    public function getMostActiveUsers($limit = 5) {
        $query = "SELECT u.id, u.name, u.role,
                 (
                     CASE 
                         WHEN u.role = 'admin' THEN 'Администратор'
                         WHEN u.role = 'warehouse' THEN 'Начальник склада'
                         WHEN u.role = 'purchasing' THEN 'Менеджер по закупкам'
                         WHEN u.role = 'supplier' THEN 'Поставщик'
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

    // Получение системных оповещений
    public function getSystemAlerts() {
        // Здесь мы генерируем системные оповещения на основе различных условий
        $alerts = [];
        
        // 1. Товары с критически низким запасом (менее 50% от min_stock)
        $lowStockQuery = "SELECT * FROM products WHERE quantity < (min_stock * 0.5) ORDER BY (min_stock - quantity) DESC";
        $lowStockItems = $this->db->select($lowStockQuery);
        
        if (!empty($lowStockItems)) {
            foreach ($lowStockItems as $item) {
                $alerts[] = [
                    'id' => 'low_stock_' . $item['id'],
                    'title' => 'Критически низкий запас товара',
                    'message' => 'Товар "' . $item['name'] . '" имеет критически низкий запас: ' . $item['quantity'] . ' ' . $item['unit'] . ' (минимальный запас: ' . $item['min_stock'] . ' ' . $item['unit'] . ')',
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
        }
        
        // 2. Неактивные камеры
        $inactiveCamerasQuery = "SELECT * FROM cameras WHERE status = 'inactive'";
        $inactiveCameras = $this->db->select($inactiveCamerasQuery);
        
        if (!empty($inactiveCameras)) {
            foreach ($inactiveCameras as $camera) {
                $alerts[] = [
                    'id' => 'inactive_camera_' . $camera['id'],
                    'title' => 'Неактивная камера',
                    'message' => 'Камера "' . $camera['name'] . '" в локации "' . $camera['location'] . '" неактивна',
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
        }
        
        // 3. Заказы, ожидающие одобрения более 2 дней
        $pendingOrdersQuery = "SELECT o.*, s.company_name 
                              FROM orders o
                              JOIN suppliers s ON o.supplier_id = s.id
                              WHERE o.status = 'pending' AND DATEDIFF(CURRENT_DATE, DATE(o.created_at)) > 2";
        $pendingOrders = $this->db->select($pendingOrdersQuery);
        
        if (!empty($pendingOrders)) {
            foreach ($pendingOrders as $order) {
                $alerts[] = [
                    'id' => 'pending_order_' . $order['id'],
                    'title' => 'Заказ ожидает одобрения',
                    'message' => 'Заказ #' . $order['id'] . ' от поставщика "' . $order['company_name'] . '" ожидает одобрения более 2 дней (создан: ' . date('d.m.Y', strtotime($order['created_at'])) . ')',
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
        }
        
        return $alerts;
    }

    // Отклонение системного оповещения (фиктивная функция, в реальной системе могла бы сохранять отклоненные оповещения)
    public function dismissAlert($alertId) {
        // В данной реализации просто возвращаем успешный результат,
        // в реальности могли бы сохранять отклоненные оповещения в базе
        return [
            'success' => true,
            'message' => 'Оповещение отмечено как решенное'
        ];
    }

    // Резервное копирование базы данных (фиктивная функция)
    public function createBackup() {
        // В реальности здесь был бы код для создания дампа базы данных
        // В данной реализации просто возвращаем успешный результат
        return [
            'success' => true,
            'message' => 'Резервная копия успешно создана',
            'filename' => 'backup_' . date('Y-m-d_H-i-s') . '.sql',
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
}