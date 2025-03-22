<?php
// controllers/SupplierController.php
// Контроллер для управления функционалом поставщика

require_once '../../config/database.php';

class SupplierController {
    private $db;

    public function __construct() {
        $this->db = new Database();
        $this->db->getConnection();
    }

    // Получение ID поставщика по ID пользователя
    public function getSupplierIdByUserId($userId) {
        $query = "SELECT id FROM suppliers WHERE user_id = ?";
        $result = $this->db->selectOne($query, [$userId]);
        return $result ? $result['id'] : null;
    }

    // Получение информации о поставщике
    public function getSupplierInfo($supplierId) {
        $query = "SELECT s.*, u.name, u.email 
                 FROM suppliers s
                 JOIN users u ON s.user_id = u.id
                 WHERE s.id = ?";
        return $this->db->selectOne($query, [$supplierId]);
    }

    // Получение заказов по статусу
    public function getOrdersByStatus($supplierId, $status) {
        $query = "SELECT * FROM orders WHERE supplier_id = ? AND status = ? ORDER BY created_at DESC";
        return $this->db->select($query, [$supplierId, $status]);
    }

    // Получение последних N заказов
    public function getRecentOrders($supplierId, $limit = 5) {
        $query = "SELECT * FROM orders WHERE supplier_id = ? ORDER BY created_at DESC LIMIT ?";
        return $this->db->select($query, [$supplierId, $limit]);
    }

    // Получение конкретного заказа с его элементами
    public function getOrderWithItems($orderId, $supplierId) {
        // Проверяем, принадлежит ли заказ поставщику
        $orderQuery = "SELECT o.*, u.name as created_by_name 
                      FROM orders o
                      JOIN users u ON o.created_by = u.id
                      WHERE o.id = ? AND o.supplier_id = ?";
        $order = $this->db->selectOne($orderQuery, [$orderId, $supplierId]);
        
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

    // Обновление профиля поставщика
    public function updateSupplierProfile($supplierId, $userData, $supplierData) {
        // Получаем ID пользователя
        $query = "SELECT user_id FROM suppliers WHERE id = ?";
        $result = $this->db->selectOne($query, [$supplierId]);
        
        if (!$result) {
            return [
                'success' => false,
                'message' => 'Поставщик не найден'
            ];
        }
        
        $userId = $result['user_id'];
        
        // Начинаем транзакцию
        $this->db->execute("START TRANSACTION");
        
        // Обновляем данные пользователя
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
                    'message' => 'Ошибка при обновлении данных пользователя'
                ];
            }
        }
        
        // Обновляем данные поставщика
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
                    'message' => 'Ошибка при обновлении данных поставщика'
                ];
            }
        }
        
        $this->db->execute("COMMIT");
        
        return [
            'success' => true,
            'message' => 'Профиль успешно обновлен'
        ];
    }

    // Получение непрочитанных сообщений
    public function getUnreadMessages($userId) {
        $query = "SELECT m.*, u.name as sender_name 
                 FROM messages m
                 JOIN users u ON m.sender_id = u.id
                 WHERE m.receiver_id = ? AND m.is_read = 0
                 ORDER BY m.created_at DESC";
        
        return $this->db->select($query, [$userId]);
    }

    // Получение всех сообщений пользователя
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

    // Отправка сообщения
    public function sendMessage($senderId, $receiverId, $subject, $content) {
        $query = "INSERT INTO messages (sender_id, receiver_id, subject, message) VALUES (?, ?, ?, ?)";
        $result = $this->db->execute($query, [$senderId, $receiverId, $subject, $content]);
        
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

    // Получение статистики продаж по месяцам
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
        
        // Переворачиваем массив для отображения данных в хронологическом порядке
        return array_reverse($stats);
    }

    // Получение списка товаров поставщика
    public function getSupplierProducts($supplierId) {
        // В этой реализации мы предполагаем, что у поставщика нет прямой привязки к товарам
        // Вместо этого мы получаем товары, которые были в заказах этого поставщика
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

    // Принятие заказа поставщиком
    public function acceptOrder($orderId, $supplierId) {
        // Проверяем, принадлежит ли заказ поставщику и находится ли он в состоянии 'pending'
        $query = "SELECT * FROM orders WHERE id = ? AND supplier_id = ? AND status = 'pending'";
        $order = $this->db->selectOne($query, [$orderId, $supplierId]);
        
        if (!$order) {
            return [
                'success' => false,
                'message' => 'Заказ не найден или не может быть подтвержден'
            ];
        }
        
        // Обновляем статус заказа
        $updateQuery = "UPDATE orders SET status = 'approved', updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $result = $this->db->execute($updateQuery, [$orderId]);
        
        if ($result) {
            return [
                'success' => true,
                'message' => 'Заказ успешно подтвержден'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Ошибка при подтверждении заказа'
            ];
        }
    }

    // Отклонение заказа поставщиком
    public function rejectOrder($orderId, $supplierId, $reason) {
        // Проверяем, принадлежит ли заказ поставщику и находится ли он в состоянии 'pending'
        $query = "SELECT * FROM orders WHERE id = ? AND supplier_id = ? AND status = 'pending'";
        $order = $this->db->selectOne($query, [$orderId, $supplierId]);
        
        if (!$order) {
            return [
                'success' => false,
                'message' => 'Заказ не найден или не может быть отклонен'
            ];
        }
        
        // Обновляем статус заказа и добавляем причину отклонения
        $updateQuery = "UPDATE orders SET status = 'rejected', notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $result = $this->db->execute($updateQuery, [$reason, $orderId]);
        
        if ($result) {
            return [
                'success' => true,
                'message' => 'Заказ отклонен'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Ошибка при отклонении заказа'
            ];
        }
    }

    // Получение статистики по заказам
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