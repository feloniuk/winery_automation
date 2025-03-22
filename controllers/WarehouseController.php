<?php
// controllers/WarehouseController.php
// Контроллер для управления складом

require_once '../../config/database.php';

class WarehouseController {
    private $db;

    public function __construct() {
        $this->db = new Database();
        $this->db->getConnection();
    }

    // Получение полного списка товаров на складе
    public function getInventorySummary() {
        $query = "SELECT * FROM products ORDER BY category, name";
        return $this->db->select($query);
    }

    // Получение списка товаров с низким запасом
    public function getLowStockItems() {
        $query = "SELECT * FROM products WHERE quantity <= min_stock ORDER BY (min_stock - quantity) DESC";
        return $this->db->select($query);
    }

    // Получение последних N транзакций
    public function getRecentTransactions($limit = 10) {
        $query = "SELECT it.*, p.name as product_name, p.unit, u.name as user_name 
                 FROM inventory_transactions it
                 JOIN products p ON it.product_id = p.id
                 JOIN users u ON it.created_by = u.id
                 ORDER BY it.created_at DESC
                 LIMIT ?";
        return $this->db->select($query, [$limit]);
    }

    // Получение топ N активных товаров
    public function getTopMovingItems($limit = 5) {
        $query = "SELECT p.id, p.name, COUNT(it.id) as transaction_count
                 FROM products p
                 JOIN inventory_transactions it ON p.id = it.product_id
                 GROUP BY p.id, p.name
                 ORDER BY transaction_count DESC
                 LIMIT ?";
        return $this->db->select($query, [$limit]);
    }

    // Получение деталей конкретного товара
    public function getProductDetails($productId) {
        $query = "SELECT * FROM products WHERE id = ?";
        return $this->db->selectOne($query, [$productId]);
    }

    // Добавление новой транзакции (приход/расход)
    public function addTransaction($productId, $quantity, $transactionType, $referenceId, $referenceType, $notes, $userId) {
        // Проверяем наличие товара
        $product = $this->getProductDetails($productId);
        if (!$product) {
            return [
                'success' => false,
                'message' => 'Товар не найден'
            ];
        }

        // Для транзакции типа "расход" проверяем, достаточно ли товара на складе
        if ($transactionType === 'out' && $product['quantity'] < $quantity) {
            return [
                'success' => false,
                'message' => 'Недостаточно товара на складе'
            ];
        }

        // Начинаем транзакцию
        $this->db->execute("START TRANSACTION");

        // Добавляем запись в таблицу транзакций
        $insertQuery = "INSERT INTO inventory_transactions 
                       (product_id, quantity, transaction_type, reference_id, reference_type, notes, created_by)
                       VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $insertResult = $this->db->execute($insertQuery, [
            $productId, 
            $quantity, 
            $transactionType, 
            $referenceId, 
            $referenceType, 
            $notes, 
            $userId
        ]);

        if (!$insertResult) {
            $this->db->execute("ROLLBACK");
            return [
                'success' => false,
                'message' => 'Ошибка при добавлении транзакции'
            ];
        }

        // Обновляем количество товара на складе
        $newQuantity = $transactionType === 'in' 
            ? $product['quantity'] + $quantity 
            : $product['quantity'] - $quantity;

        $updateQuery = "UPDATE products SET quantity = ? WHERE id = ?";
        $updateResult = $this->db->execute($updateQuery, [$newQuantity, $productId]);

        if (!$updateResult) {
            $this->db->execute("ROLLBACK");
            return [
                'success' => false,
                'message' => 'Ошибка при обновлении количества товара'
            ];
        }

        // Если всё прошло успешно, фиксируем транзакцию
        $this->db->execute("COMMIT");

        return [
            'success' => true,
            'message' => $transactionType === 'in' 
                ? 'Товар успешно принят на склад'
                : 'Товар успешно выдан со склада',
            'transaction_id' => $this->db->lastInsertId()
        ];
    }

    // Получение истории транзакций для конкретного товара
    public function getProductTransactions($productId, $limit = 20) {
        $query = "SELECT it.*, u.name as user_name 
                 FROM inventory_transactions it
                 JOIN users u ON it.created_by = u.id
                 WHERE it.product_id = ?
                 ORDER BY it.created_at DESC
                 LIMIT ?";
        return $this->db->select($query, [$productId, $limit]);
    }

    // Получение товаров по категории
    public function getProductsByCategory($category) {
        $query = "SELECT * FROM products WHERE category = ? ORDER BY name";
        return $this->db->select($query, [$category]);
    }

    // Добавление нового товара
    public function addProduct($name, $category, $quantity, $unit, $minStock, $description) {
        $query = "INSERT INTO products (name, category, quantity, unit, min_stock, description)
                 VALUES (?, ?, ?, ?, ?, ?)";
        
        $result = $this->db->execute($query, [
            $name, $category, $quantity, $unit, $minStock, $description
        ]);

        if ($result) {
            return [
                'success' => true,
                'message' => 'Товар успешно добавлен',
                'product_id' => $this->db->lastInsertId()
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Ошибка при добавлении товара'
            ];
        }
    }

    // Обновление данных товара
    public function updateProduct($id, $name, $category, $unit, $minStock, $description) {
        $query = "UPDATE products 
                 SET name = ?, category = ?, unit = ?, min_stock = ?, description = ?
                 WHERE id = ?";
        
        $result = $this->db->execute($query, [
            $name, $category, $unit, $minStock, $description, $id
        ]);

        if ($result) {
            return [
                'success' => true,
                'message' => 'Данные товара успешно обновлены'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Ошибка при обновлении данных товара'
            ];
        }
    }

    // Обработка приема товаров от поставщиков (по заказу)
    public function receiveOrderItems($orderId, $userId) {
        // Получаем информацию о заказе
        $orderQuery = "SELECT * FROM orders WHERE id = ?";
        $order = $this->db->selectOne($orderQuery, [$orderId]);

        if (!$order || $order['status'] !== 'approved') {
            return [
                'success' => false,
                'message' => 'Заказ не найден или еще не одобрен'
            ];
        }

        // Получаем элементы заказа
        $itemsQuery = "SELECT oi.*, p.name as product_name, p.quantity as current_stock
                      FROM order_items oi
                      JOIN products p ON oi.product_id = p.id
                      WHERE oi.order_id = ?";
        $items = $this->db->select($itemsQuery, [$orderId]);

        if (empty($items)) {
            return [
                'success' => false,
                'message' => 'В заказе отсутствуют товары'
            ];
        }

        // Начинаем транзакцию
        $this->db->execute("START TRANSACTION");

        $success = true;
        $errors = [];

        foreach ($items as $item) {
            // Добавляем транзакцию для товара
            $transactionQuery = "INSERT INTO inventory_transactions 
                               (product_id, quantity, transaction_type, reference_id, reference_type, created_by)
                               VALUES (?, ?, 'in', ?, 'order', ?)";
            
            $transResult = $this->db->execute($transactionQuery, [
                $item['product_id'],
                $item['quantity'],
                $orderId,
                $userId
            ]);

            if (!$transResult) {
                $success = false;
                $errors[] = "Ошибка при обработке товара: " . $item['product_name'];
                break;
            }

            // Обновляем количество товара на складе
            $newQuantity = $item['current_stock'] + $item['quantity'];
            $updateQuery = "UPDATE products SET quantity = ? WHERE id = ?";
            
            $updateResult = $this->db->execute($updateQuery, [
                $newQuantity,
                $item['product_id']
            ]);

            if (!$updateResult) {
                $success = false;
                $errors[] = "Ошибка при обновлении количества товара: " . $item['product_name'];
                break;
            }
        }

        if ($success) {
            // Обновляем статус заказа
            $updateOrderQuery = "UPDATE orders SET status = 'received' WHERE id = ?";
            $updateOrderResult = $this->db->execute($updateOrderQuery, [$orderId]);

            if ($updateOrderResult) {
                $this->db->execute("COMMIT");
                return [
                    'success' => true,
                    'message' => 'Товары успешно приняты на склад'
                ];
            } else {
                $this->db->execute("ROLLBACK");
                return [
                    'success' => false,
                    'message' => 'Ошибка при обновлении статуса заказа'
                ];
            }
        } else {
            $this->db->execute("ROLLBACK");
            return [
                'success' => false,
                'message' => 'Произошли ошибки при приеме товаров: ' . implode(', ', $errors)
            ];
        }
    }
}