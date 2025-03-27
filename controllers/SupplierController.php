<?php
// controllers/SupplierController.php
// Контролер для управління функціоналом постачальника

if (!defined('ROOT_PATH')) {
    require_once dirname(__DIR__) . '/config.php';
}
require_once ROOT_PATH . '/config/database.php';

class SupplierController {
    public $db;

    public function __construct() {
        $this->db = new Database();
        $this->db->getConnection();
    }
    

    // Отримання ID постачальника за ID користувача
    public function getSupplierIdByUserId($userId) {
        $query = "SELECT id FROM suppliers WHERE user_id = ?";
        $result = $this->db->selectOne($query, [$userId]);
        return $result ? $result['id'] : null;
    }

    // Отримання інформації про постачальника
    public function getSupplierInfo($supplierId) {
        $query = "SELECT s.*, u.name, u.email 
                 FROM suppliers s
                 JOIN users u ON s.user_id = u.id
                 WHERE s.id = ?";
        return $this->db->selectOne($query, [$supplierId]);
    }

    // Отримання замовлень за статусом
    public function getOrdersByStatus($supplierId, $status) {
        $query = "SELECT * FROM orders WHERE supplier_id = ? AND status = ? ORDER BY created_at DESC";
        return $this->db->select($query, [$supplierId, $status]);
    }

    // Отримання останніх N замовлень
    public function getRecentOrders($supplierId, $limit = 5) {
        $query = "SELECT * FROM orders WHERE supplier_id = ? ORDER BY created_at DESC LIMIT ?";
        return $this->db->select($query, [$supplierId, $limit]);
    }

    // Отримання конкретного замовлення з його елементами
    public function getOrderWithItems($orderId, $supplierId) {
        // Перевіряємо, чи належить замовлення постачальнику
        $orderQuery = "SELECT o.*, u.name as created_by_name 
                      FROM orders o
                      JOIN users u ON o.created_by = u.id
                      WHERE o.id = ? AND o.supplier_id = ?";
        $order = $this->db->selectOne($orderQuery, [$orderId, $supplierId]);
        
        if (!$order) {
            return null;
        }
        
        // Отримуємо елементи замовлення
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

    // Оновлення профілю постачальника
    public function updateSupplierProfile($supplierId, $userData, $supplierData) {
        // Отримуємо ID користувача
        $query = "SELECT user_id FROM suppliers WHERE id = ?";
        $result = $this->db->selectOne($query, [$supplierId]);
        
        if (!$result) {
            return [
                'success' => false,
                'message' => 'Постачальника не знайдено'
            ];
        }
        
        $userId = $result['user_id'];
        
        // Починаємо транзакцію
        $this->db->execute("START TRANSACTION");
        
        // Оновлюємо дані користувача
        $userUpdateFields = [];
        $userUpdateParams = [];
        
        if (isset($userData['name'])) {
            $userUpdateFields[] = "name = ?";
            $userUpdateParams[] = $userData['name'];
        }
        
        if (isset($userData['email'])) {
            $userUpdateFields[] = "email = ?";
            $userUpdateParams[] = $userData['email'];
        }
        
        if (!empty($userData['password'])) {
            $userUpdateFields[] = "password = ?";
            $userUpdateParams[] = password_hash($userData['password'], PASSWORD_DEFAULT);
        }
        
        if (!empty($userUpdateFields)) {
            $userUpdateParams[] = $userId;
            $userUpdateQuery = "UPDATE users SET " . implode(', ', $userUpdateFields) . " WHERE id = ?";
            $userUpdateResult = $this->db->execute($userUpdateQuery, $userUpdateParams);
            
            if (!$userUpdateResult) {
                $this->db->execute("ROLLBACK");
                return [
                    'success' => false,
                    'message' => 'Помилка при оновленні даних користувача'
                ];
            }
        }
        
        // Оновлюємо дані постачальника
        $supplierUpdateFields = [];
        $supplierUpdateParams = [];
        
        if (isset($supplierData['company_name'])) {
            $supplierUpdateFields[] = "company_name = ?";
            $supplierUpdateParams[] = $supplierData['company_name'];
        }
        
        if (isset($supplierData['contact_person'])) {
            $supplierUpdateFields[] = "contact_person = ?";
            $supplierUpdateParams[] = $supplierData['contact_person'];
        }
        
        if (isset($supplierData['phone'])) {
            $supplierUpdateFields[] = "phone = ?";
            $supplierUpdateParams[] = $supplierData['phone'];
        }
        
        if (isset($supplierData['address'])) {
            $supplierUpdateFields[] = "address = ?";
            $supplierUpdateParams[] = $supplierData['address'];
        }
        
        if (!empty($supplierUpdateFields)) {
            $supplierUpdateParams[] = $supplierId;
            $supplierUpdateQuery = "UPDATE suppliers SET " . implode(', ', $supplierUpdateFields) . " WHERE id = ?";
            $supplierUpdateResult = $this->db->execute($supplierUpdateQuery, $supplierUpdateParams);
            
            if (!$supplierUpdateResult) {
                $this->db->execute("ROLLBACK");
                return [
                    'success' => false,
                    'message' => 'Помилка при оновленні даних постачальника'
                ];
            }
        }
        
        $this->db->execute("COMMIT");
        
        return [
            'success' => true,
            'message' => 'Профіль успішно оновлено'
        ];
    }

    // Отримання непрочитаних повідомлень
    public function getUnreadMessages($userId) {
        $query = "SELECT m.*, u.name as sender_name 
                 FROM messages m
                 JOIN users u ON m.sender_id = u.id
                 WHERE m.receiver_id = ? AND m.is_read = 0
                 ORDER BY m.created_at DESC";
        
        return $this->db->select($query, [$userId]);
    }

    // Отримання всіх повідомлень користувача
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
        
        // Об'єднуємо та сортуємо повідомлення за датою
        $allMessages = array_merge($receivedMessages, $sentMessages);
        usort($allMessages, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return $allMessages;
    }

    // Отримання конкретного повідомлення
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
            // Відмічаємо повідомлення як прочитане
            $updateQuery = "UPDATE messages SET is_read = 1 WHERE id = ?";
            $this->db->execute($updateQuery, [$messageId]);
        }
        
        return $message;
    }

    // Відправлення повідомлення
    public function sendMessage($senderId, $receiverId, $subject, $content) {
        $query = "INSERT INTO messages (sender_id, receiver_id, subject, message) VALUES (?, ?, ?, ?)";
        $result = $this->db->execute($query, [$senderId, $receiverId, $subject, $content]);
        
        if ($result) {
            return [
                'success' => true,
                'message' => 'Повідомлення успішно відправлено',
                'message_id' => $this->db->lastInsertId()
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Помилка при відправленні повідомлення'
            ];
        }
    }

    // Отримання статистики продажів по місяцях
    public function getSupplierSalesStats($supplierId) {
        $query = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month_key,
                    DATE_FORMAT(created_at, '%b %Y') as month_name,
                    SUM(total_amount) as total_amount,
                    COUNT(*) as order_count
                 FROM orders
                 WHERE supplier_id = ? AND status IN ('approved', 'received')
                 GROUP BY month_key, month_name
                 ORDER BY month_key DESC
                 LIMIT 6";
        
        $stats = $this->db->select($query, [$supplierId]);
        
        // Перевертаємо масив для відображення даних у хронологічному порядку
        return array_reverse($stats);
    }

    // Отримання списку товарів постачальника
    public function getSupplierProducts($supplierId) {
        // У цій реалізації ми припускаємо, що у постачальника немає прямої прив'язки до товарів
        // Замість цього ми отримуємо товари, які були в замовленнях цього постачальника
        $query = "SELECT DISTINCT p.*, 
                 (SELECT SUM(oi.quantity) FROM order_items oi 
                  JOIN orders o ON oi.order_id = o.id 
                  WHERE o.supplier_id = ? AND oi.product_id = p.id) as total_supplied
                 FROM products p
                 JOIN order_items oi ON p.id = oi.product_id
                 JOIN orders o ON oi.order_id = o.id
                 WHERE o.supplier_id = ?
                 ORDER BY p.name";
        
        return $this->db->select($query, [$supplierId, $supplierId]);
    }

    // Отримання дати останньої поставки товару
    public function getProductLastDelivery($supplierId, $productId) {
        $query = "SELECT MAX(it.created_at) as last_delivery 
                  FROM inventory_transactions it
                  JOIN orders o ON it.reference_id = o.id AND it.reference_type = 'order'
                  WHERE o.supplier_id = ? AND it.product_id = ? AND it.transaction_type = 'in'";
        
        $result = $this->db->selectOne($query, [$supplierId, $productId]);
        
        return $result && isset($result['last_delivery']) ? $result['last_delivery'] : null;
    }

    // Прийняття замовлення постачальником
    public function acceptOrder($orderId, $supplierId) {
        // Перевіряємо, чи належить замовлення постачальнику і чи знаходиться воно в стані 'pending'
        $query = "SELECT * FROM orders WHERE id = ? AND supplier_id = ? AND status = 'pending'";
        $order = $this->db->selectOne($query, [$orderId, $supplierId]);
        
        if (!$order) {
            return [
                'success' => false,
                'message' => 'Замовлення не знайдено або не може бути підтверджено'
            ];
        }
        
        // Оновлюємо статус замовлення
        $updateQuery = "UPDATE orders SET status = 'approved', updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $result = $this->db->execute($updateQuery, [$orderId]);
        
        if ($result) {
            return [
                'success' => true,
                'message' => 'Замовлення успішно підтверджено'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Помилка при підтвердженні замовлення'
            ];
        }
    }

    // Відхилення замовлення постачальником
    public function rejectOrder($orderId, $supplierId, $reason) {
        // Перевіряємо, чи належить замовлення постачальнику і чи знаходиться воно в стані 'pending'
        $query = "SELECT * FROM orders WHERE id = ? AND supplier_id = ? AND status = 'pending'";
        $order = $this->db->selectOne($query, [$orderId, $supplierId]);
        
        if (!$order) {
            return [
                'success' => false,
                'message' => 'Замовлення не знайдено або не може бути відхилено'
            ];
        }
        
        // Оновлюємо статус замовлення та додаємо причину відхилення
        $updateQuery = "UPDATE orders SET status = 'rejected', notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $result = $this->db->execute($updateQuery, [$reason, $orderId]);
        
        if ($result) {
            return [
                'success' => true,
                'message' => 'Замовлення відхилено'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Помилка при відхиленні замовлення'
            ];
        }
    }

    // Отримання статистики по замовленнях
    public function getOrderStats($supplierId) {
        $query = "SELECT 
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                    SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END) as received_count,
                    SUM(total_amount) as total_amount
                 FROM orders
                 WHERE supplier_id = ?";
        
        $result = $this->db->selectOne($query, [$supplierId]);
        return $result ?: [
            'total_orders' => 0,
            'pending_count' => 0,
            'approved_count' => 0,
            'rejected_count' => 0,
            'received_count' => 0,
            'total_amount' => 0
        ];
    }
}
