<?php
// controllers/PurchasingController.php
// Контроллер для управления закупками

if (!defined('ROOT_PATH')) {
    require_once dirname(__DIR__) . '/config.php';
}
require_once ROOT_PATH . '/config/database.php';

class PurchasingController {
    private $db;

    public function __construct() {
        $this->db = new Database();
        $this->db->getConnection();
    }

// Методы для добавления в controllers/PurchasingController.php


/**
 * Получение товаров по категории
 */
public function getProductsByCategory($category) {
    $query = "SELECT * FROM products WHERE category = ? ORDER BY name";
    return $this->db->select($query, [$category]);
}

/**
 * Получение пользователей, с которыми можно вести переписку
 */
public function getPotentialMessageRecipients() {
    $query = "SELECT u.id, u.name, u.role, 
             CASE 
                 WHEN u.role = 'admin' THEN 'Администратор'
                 WHEN u.role = 'warehouse' THEN 'Начальник склада'
                 WHEN u.role = 'purchasing' THEN 'Менеджер по закупкам'
                 WHEN u.role = 'supplier' THEN 'Поставщик'
                 ELSE u.role
             END as role_name,
             s.company_name
             FROM users u
             LEFT JOIN suppliers s ON u.id = s.user_id
             WHERE u.id != ?
             ORDER BY u.role, u.name";
    return $this->db->select($query, [$_SESSION['user_id']]);
}

/**
 * Отправка сообщения пользователю
 */
public function sendMessage($senderId, $receiverId, $subject, $message) {
    // Сохраняем сообщение
    $insertQuery = "INSERT INTO messages (sender_id, receiver_id, subject, message) 
                   VALUES (?, ?, ?, ?)";
    
    $result = $this->db->execute($insertQuery, [
        $senderId,
        $receiverId,
        $subject,
        $message
    ]);
    
    if ($result) {
        return [
            'success' => true,
            'message' => 'Сообщение успешно отправлено',
            'message_id' => $this->db->lastInsertId()
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Ошибка при отправке сообщения'
        ];
    }
}



/**
 * Получение всех сообщений пользователя
 */
public function getAllMessages($userId) {
    $receivedQuery = "SELECT m.*, u.name as sender_name, 'received' as type 
                     FROM messages m
                     JOIN users u ON m.sender_id = u.id
                     WHERE m.receiver_id = ?";
    
    $sentQuery = "SELECT m.*, u.name as receiver_name, 'sent' as type 
                 FROM messages m
                 JOIN users u ON m.receiver_id = u.id
                 WHERE m.sender_id = ?";
    
    $receivedMessages = $this->db->select($receivedQuery, [$userId]);
    $sentMessages = $this->db->select($sentQuery, [$userId]);
    
    // Объединяем и сортируем сообщения по дате
    $allMessages = array_merge($receivedMessages, $sentMessages);
    usort($allMessages, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    return $allMessages;
}



/**
 * Получение последних заказов поставщика
 */
public function getSupplierRecentOrders($supplierId, $limit = 5) {
    $query = "SELECT * FROM orders WHERE supplier_id = ? ORDER BY created_at DESC LIMIT ?";
    return $this->db->select($query, [$supplierId, (int)$limit]);
}

/**
 * Получение общего количества заказов
 */
public function getTotalOrdersCount() {
    $query = "SELECT COUNT(*) as count FROM orders";
    $result = $this->db->selectOne($query);
    return $result ? $result['count'] : 0;
}

/**
 * Получение среднего времени отклика поставщиков (в часах)
 */
public function getAverageResponseTime() {
    $query = "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_time 
              FROM orders 
              WHERE status IN ('approved', 'rejected') 
              AND TIMESTAMPDIFF(HOUR, created_at, updated_at) < 168"; // Ограничиваем неделей
    
    $result = $this->db->selectOne($query);
    if ($result && !is_null($result['avg_time'])) {
        return round($result['avg_time'], 1);
    }
    return 0;
}

/**
 * Получение топ поставщиков по количеству заказов
 */
public function getTopSuppliers($limit = 5) {
    $query = "SELECT s.id, s.company_name, COUNT(o.id) as order_count
              FROM suppliers s
              JOIN orders o ON s.id = o.supplier_id
              GROUP BY s.id, s.company_name
              ORDER BY order_count DESC
              LIMIT ?";
    
    return $this->db->select($query, [(int)$limit]);
}

/**
 * Получение числа заказов поставщика
 */
public function getSupplierOrdersCount($supplierId) {
    $query = "SELECT COUNT(*) as count FROM orders WHERE supplier_id = ?";
    $result = $this->db->selectOne($query, [$supplierId]);
    return $result ? $result['count'] : 0;
}

/**
 * Получение времени последней активности поставщика
 */
public function getSupplierLastActivity($supplierId) {
    $query = "SELECT MAX(updated_at) as last_activity FROM orders WHERE supplier_id = ?";
    $result = $this->db->selectOne($query, [$supplierId]);
    return $result && isset($result['last_activity']) ? $result['last_activity'] : null;
}

    // Получение списка поставщиков
    public function getActiveSuppliers() {
        $query = "SELECT s.*, u.name as user_name, u.email 
                 FROM suppliers s
                 JOIN users u ON s.user_id = u.id
                 WHERE s.status = 'active'
                 ORDER BY s.company_name";
        return $this->db->select($query);
    }

    // Получение информации о конкретном поставщике
    public function getSupplierById($supplierId) {
        $query = "SELECT s.*, u.name as user_name, u.email 
                 FROM suppliers s
                 JOIN users u ON s.user_id = u.id
                 WHERE s.id = ?";
        return $this->db->selectOne($query, [$supplierId]);
    }

    // Получение товаров с низким запасом
    public function getLowStockItems() {
        $query = "SELECT * FROM products WHERE quantity <= min_stock ORDER BY (min_stock - quantity) DESC";
        return $this->db->select($query);
    }

    // Получение заказов по статусу
    public function getOrdersByStatus($status) {
        $query = "SELECT o.*, s.company_name 
                 FROM orders o
                 JOIN suppliers s ON o.supplier_id = s.id
                 WHERE o.status = ?
                 ORDER BY o.created_at DESC";
        return $this->db->select($query, [$status]);
    }

    // Получение последних полученных заказов с лимитом
    public function getRecentReceivedOrders($limit = 5) {
        $query = "SELECT o.*, s.company_name 
                 FROM orders o
                 JOIN suppliers s ON o.supplier_id = s.id
                 WHERE o.status = 'received'
                 ORDER BY o.updated_at DESC
                 LIMIT ?";
        return $this->db->select($query, [$limit]);
    }

    // Получение конкретного заказа с его элементами
    public function getOrderWithItems($orderId) {
        // Получаем информацию о заказе
        $orderQuery = "SELECT o.*, s.company_name, u.name as created_by_name 
                      FROM orders o
                      JOIN suppliers s ON o.supplier_id = s.id
                      JOIN users u ON o.created_by = u.id
                      WHERE o.id = ?";
        $order = $this->db->selectOne($orderQuery, [$orderId]);
        
        if (!$order) {
            return null;
        }
        
        // Получаем элементы заказа
        $itemsQuery = "SELECT oi.*, p.name as product_name, p.category, p.unit 
                      FROM order_items oi
                      JOIN products p ON oi.product_id = p.id
                      WHERE oi.order_id = ?";
        $items = $this->db->select($itemsQuery, [$orderId]);
        
        return [
            'order' => $order,
            'items' => $items
        ];
    }

    // Создание нового заказа
    public function createOrder($supplierId, $createdBy, $items) {
        // Проверяем существование поставщика
        $supplierQuery = "SELECT * FROM suppliers WHERE id = ?";
        $supplier = $this->db->selectOne($supplierQuery, [$supplierId]);
        
        if (!$supplier) {
            return [
                'success' => false,
                'message' => 'Поставщик не найден'
            ];
        }
        
        // Начинаем транзакцию
        $this->db->execute("START TRANSACTION");
        
        // Создаем заказ
        $orderQuery = "INSERT INTO orders (supplier_id, created_by, status) VALUES (?, ?, 'pending')";
        $orderCreated = $this->db->execute($orderQuery, [$supplierId, $createdBy]);
        
        if (!$orderCreated) {
            $this->db->execute("ROLLBACK");
            return [
                'success' => false,
                'message' => 'Ошибка при создании заказа'
            ];
        }
        
        $orderId = $this->db->lastInsertId();
        $totalAmount = 0;
        
        // Добавляем элементы заказа
        foreach ($items as $item) {
            $itemQuery = "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
            $itemParams = [
                $orderId,
                $item['product_id'],
                $item['quantity'],
                $item['price']
            ];
            
            $itemCreated = $this->db->execute($itemQuery, $itemParams);
            
            if (!$itemCreated) {
                $this->db->execute("ROLLBACK");
                return [
                    'success' => false,
                    'message' => 'Ошибка при добавлении элемента заказа'
                ];
            }
            
            $totalAmount += $item['quantity'] * $item['price'];
        }
        
        // Обновляем общую сумму заказа
        $updateOrderQuery = "UPDATE orders SET total_amount = ? WHERE id = ?";
        $orderUpdated = $this->db->execute($updateOrderQuery, [$totalAmount, $orderId]);
        
        if (!$orderUpdated) {
            $this->db->execute("ROLLBACK");
            return [
                'success' => false,
                'message' => 'Ошибка при обновлении суммы заказа'
            ];
        }
        
        $this->db->execute("COMMIT");
        
        return [
            'success' => true,
            'message' => 'Заказ успешно создан',
            'order_id' => $orderId
        ];
    }

    // Обновление статуса заказа
    public function updateOrderStatus($orderId, $status) {
        $validStatuses = ['pending', 'approved', 'rejected', 'received'];
        
        if (!in_array($status, $validStatuses)) {
            return [
                'success' => false,
                'message' => 'Недопустимый статус заказа'
            ];
        }
        
        $query = "UPDATE orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $result = $this->db->execute($query, [$status, $orderId]);
        
        if ($result) {
            return [
                'success' => true,
                'message' => 'Статус заказа успешно обновлен'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Ошибка при обновлении статуса заказа'
            ];
        }
    }

    // Отправка сообщения поставщику
    public function sendMessageToSupplier($senderId, $supplierId, $subject, $message) {
        // Получаем ID пользователя-поставщика
        $query = "SELECT user_id FROM suppliers WHERE id = ?";
        $supplier = $this->db->selectOne($query, [$supplierId]);
        
        if (!$supplier || empty($supplier['user_id'])) {
            return [
                'success' => false,
                'message' => 'Поставщик не найден'
            ];
        }
        
        $receiverId = $supplier['user_id'];
        
        // Сохраняем сообщение
        $insertQuery = "INSERT INTO messages (sender_id, receiver_id, subject, message) 
                       VALUES (?, ?, ?, ?)";
        
        $result = $this->db->execute($insertQuery, [
            $senderId,
            $receiverId,
            $subject,
            $message
        ]);
        
        if ($result) {
            return [
                'success' => true,
                'message' => 'Сообщение успешно отправлено'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Ошибка при отправке сообщения'
            ];
        }
    }

    // Получение количества непрочитанных сообщений
    public function getUnreadMessagesCount($userId) {
        $query = "SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0";
        $result = $this->db->selectOne($query, [$userId]);
        return $result ? $result['count'] : 0;
    }

    // Получение сообщений пользователя
    public function getUserMessages($userId, $isReceived = true) {
        $query = $isReceived
            ? "SELECT m.*, u.name as sender_name 
               FROM messages m
               JOIN users u ON m.sender_id = u.id
               WHERE m.receiver_id = ?
               ORDER BY m.created_at DESC"
            : "SELECT m.*, u.name as receiver_name 
               FROM messages m
               JOIN users u ON m.receiver_id = u.id
               WHERE m.sender_id = ?
               ORDER BY m.created_at DESC";
        
        return $this->db->select($query, [$userId]);
    }

    // Получение конкретного сообщения
    public function getMessage($messageId, $userId) {
        $query = "SELECT m.*, 
                 sender.name as sender_name, sender.email as sender_email,
                 receiver.name as receiver_name, receiver.email as receiver_email
                 FROM messages m
                 JOIN users sender ON m.sender_id = sender.id
                 JOIN users receiver ON m.receiver_id = receiver.id
                 WHERE m.id = ? AND (m.sender_id = ? OR m.receiver_id = ?)";
        
        $message = $this->db->selectOne($query, [$messageId, $userId, $userId]);
        
        if ($message && $message['receiver_id'] == $userId && !$message['is_read']) {
            // Отмечаем сообщение как прочитанное
            $updateQuery = "UPDATE messages SET is_read = 1 WHERE id = ?";
            $this->db->execute($updateQuery, [$messageId]);
        }
        
        return $message;
    }

    // Получение количества заказов за указанный период
    public function getOrdersCountForPeriod($startDate, $endDate) {
        $query = "SELECT COUNT(*) as count 
                 FROM orders 
                 WHERE created_at BETWEEN ? AND ?";
        
        $result = $this->db->selectOne($query, [$startDate, $endDate]);
        return $result ? $result['count'] : 0;
    }

    // Получение общей суммы заказов за указанный период
    public function getTotalSpendingForPeriod($startDate, $endDate) {
        $query = "SELECT SUM(total_amount) as total 
                 FROM orders 
                 WHERE created_at BETWEEN ? AND ?";
        
        $result = $this->db->selectOne($query, [$startDate, $endDate]);
        return $result ? ($result['total'] ?? 0) : 0;
    }

    // Получение количества заказов по месяцам за последние N месяцев
    public function getOrderCountByMonth($monthsCount = 6) {
        $query = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month_key,
                    DATE_FORMAT(created_at, '%b %Y') as month_name,
                    COUNT(*) as count
                 FROM orders
                 WHERE created_at >= DATE_SUB(CURRENT_DATE, INTERVAL ? MONTH)
                 GROUP BY month_key, month_name
                 ORDER BY month_key";
        
        return $this->db->select($query, [$monthsCount]);
    }
}