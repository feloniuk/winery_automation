<?php
// controllers/WarehouseController.php
// Контролер для управління складом

if (!defined('ROOT_PATH')) {
    require_once dirname(__DIR__) . '/config.php';
}

require_once ROOT_PATH . '/config/database.php';

class WarehouseController {
    private $db;

    public function __construct() {
        $this->db = new Database();
        $this->db->getConnection();
    }

    // Отримання повного списку товарів на складі
    public function getInventorySummary() {
        $query = "SELECT * FROM products ORDER BY category, name";
        return $this->db->select($query);
    }

    // Отримання списку товарів з низьким запасом
    public function getLowStockItems() {
        $query = "SELECT * FROM products WHERE quantity <= min_stock ORDER BY (min_stock - quantity) DESC";
        return $this->db->select($query);
    }

    // Отримання останніх N транзакцій
    public function getRecentTransactions($limit = 10) {
        $query = "SELECT it.*, p.name as product_name, p.unit, u.name as user_name 
                 FROM inventory_transactions it
                 JOIN products p ON it.product_id = p.id
                 JOIN users u ON it.created_by = u.id
                 ORDER BY it.created_at DESC
                 LIMIT ?";
        return $this->db->select($query, [$limit]);
    }

    // Отримання топ N активних товарів
    public function getTopMovingItems($limit = 5) {
        $query = "SELECT p.id, p.name, COUNT(it.id) as transaction_count
                 FROM products p
                 JOIN inventory_transactions it ON p.id = it.product_id
                 GROUP BY p.id, p.name
                 ORDER BY transaction_count DESC
                 LIMIT ?";
        return $this->db->select($query, [$limit]);
    }

    // Отримання деталей конкретного товару
    public function getProductDetails($productId) {
        $query = "SELECT * FROM products WHERE id = ?";
        return $this->db->selectOne($query, [$productId]);
    }

// Наступні методи потрібно додати в controllers/WarehouseController.php

/**
 * Отримання відфільтрованих транзакцій
 */
public function getFilteredTransactions($type = '', $productId = '', $dateFrom = '', $dateTo = '', $userId = '', $referenceType = '') {
    // Створюємо базовий запит
    $query = "SELECT it.*, p.name as product_name, p.unit, u.name as user_name 
              FROM inventory_transactions it
              JOIN products p ON it.product_id = p.id
              JOIN users u ON it.created_by = u.id
              WHERE 1=1";
    
    $params = [];
    
    // Застосовуємо фільтри
    if (!empty($type)) {
        $query .= " AND it.transaction_type = ?";
        $params[] = $type;
    }
    
    if (!empty($productId)) {
        $query .= " AND it.product_id = ?";
        $params[] = $productId;
    }
    
    if (!empty($dateFrom)) {
        $query .= " AND DATE(it.created_at) >= ?";
        $params[] = $dateFrom;
    }
    
    if (!empty($dateTo)) {
        $query .= " AND DATE(it.created_at) <= ?";
        $params[] = $dateTo;
    }
    
    if (!empty($userId)) {
        $query .= " AND it.created_by = ?";
        $params[] = $userId;
    }
    
    if (!empty($referenceType)) {
        $query .= " AND it.reference_type = ?";
        $params[] = $referenceType;
    }
    
    $query .= " ORDER BY it.created_at DESC";
    
    return $this->db->select($query, $params);
}

/**
 * Отримання списку всіх користувачів для фільтрації
 */
public function getAllUsers() {
    $query = "SELECT id, name, role FROM users ORDER BY role, name";
    return $this->db->select($query);
}

/**
 * Отримання статистики по транзакціях
 */
public function getTransactionStatistics($dateFrom, $dateTo) {
    // Основна статистика
    $query = "SELECT 
                COUNT(*) as total_count,
                SUM(CASE WHEN transaction_type = 'in' THEN 1 ELSE 0 END) as in_count,
                SUM(CASE WHEN transaction_type = 'out' THEN 1 ELSE 0 END) as out_count,
                SUM(CASE WHEN reference_type = 'order' THEN 1 ELSE 0 END) as order_count,
                SUM(CASE WHEN reference_type = 'production' THEN 1 ELSE 0 END) as production_count,
                SUM(CASE WHEN reference_type = 'adjustment' THEN 1 ELSE 0 END) as adjustment_count
              FROM inventory_transactions
              WHERE DATE(created_at) BETWEEN ? AND ?";
    
    $stats = $this->db->selectOne($query, [$dateFrom, $dateTo]);
    
    // Дані для графіка - активність по днях
    $chartQuery = "
        SELECT 
            DATE(created_at) as date,
            SUM(CASE WHEN transaction_type = 'in' THEN 1 ELSE 0 END) as in_count,
            SUM(CASE WHEN transaction_type = 'out' THEN 1 ELSE 0 END) as out_count
        FROM inventory_transactions
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY DATE(created_at)
    ";
    
    $chartData = $this->db->select($chartQuery, [$dateFrom, $dateTo]);
    
    // Перетворюємо дані для графіка
    $labels = [];
    $inData = [];
    $outData = [];
    
    foreach ($chartData as $data) {
        $labels[] = date('d.m', strtotime($data['date']));
        $inData[] = $data['in_count'];
        $outData[] = $data['out_count'];
    }
    
    $stats['chart_data'] = [
        'labels' => $labels,
        'in_data' => $inData,
        'out_data' => $outData
    ];
    
    return $stats;
}

    // Додавання нової транзакції (прихід/видаток)
    public function addTransaction($productId, $quantity, $transactionType, $referenceId, $referenceType, $notes, $userId) {
        // Перевіряємо наявність товару
        $product = $this->getProductDetails($productId);
        if (!$product) {
            return [
                'success' => false,
                'message' => 'Товар не знайдено'
            ];
        }

        // Для транзакції типу "видаток" перевіряємо, чи достатньо товару на складі
        if ($transactionType === 'out' && $product['quantity'] < $quantity) {
            return [
                'success' => false,
                'message' => 'Недостатньо товару на складі'
            ];
        }

        // Починаємо транзакцію
        $this->db->execute("START TRANSACTION");

        // Додаємо запис в таблицю транзакцій
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
                'message' => 'Помилка при додаванні транзакції'
            ];
        }

        // Оновлюємо кількість товару на складі
        $newQuantity = $transactionType === 'in' 
            ? $product['quantity'] + $quantity 
            : $product['quantity'] - $quantity;

        $updateQuery = "UPDATE products SET quantity = ? WHERE id = ?";
        $updateResult = $this->db->execute($updateQuery, [$newQuantity, $productId]);

        if (!$updateResult) {
            $this->db->execute("ROLLBACK");
            return [
                'success' => false,
                'message' => 'Помилка при оновленні кількості товару'
            ];
        }

        // Якщо все пройшло успішно, фіксуємо транзакцію
        $this->db->execute("COMMIT");

        return [
            'success' => true,
            'message' => $transactionType === 'in' 
                ? 'Товар успішно прийнято на склад'
                : 'Товар успішно видано зі складу',
            'transaction_id' => $this->db->lastInsertId()
        ];
    }

    // Отримання історії транзакцій для конкретного товару
    public function getProductTransactions($productId, $limit = 20) {
        $query = "SELECT it.*, u.name as user_name 
                 FROM inventory_transactions it
                 JOIN users u ON it.created_by = u.id
                 WHERE it.product_id = ?
                 ORDER BY it.created_at DESC
                 LIMIT ?";
        return $this->db->select($query, [$productId, $limit]);
    }

    // Отримання товарів за категорією
    public function getProductsByCategory($category) {
        $query = "SELECT * FROM products WHERE category = ? ORDER BY name";
        return $this->db->select($query, [$category]);
    }

    // Додавання нового товару
    public function addProduct($name, $category, $quantity, $unit, $minStock, $description) {
        $query = "INSERT INTO products (name, category, quantity, unit, min_stock, description)
                 VALUES (?, ?, ?, ?, ?, ?)";
        
        $result = $this->db->execute($query, [
            $name, $category, $quantity, $unit, $minStock, $description
        ]);

        if ($result) {
            return [
                'success' => true,
                'message' => 'Товар успішно додано',
                'product_id' => $this->db->lastInsertId()
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Помилка при додаванні товару'
            ];
        }
    }

    // Оновлення даних товару
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
                'message' => 'Дані товару успішно оновлено'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Помилка при оновленні даних товару'
            ];
        }
    }

    // Обробка прийому товарів від постачальників (за замовленням)
    public function receiveOrderItems($orderId, $userId) {
        // Отримуємо інформацію про замовлення
        $orderQuery = "SELECT * FROM orders WHERE id = ?";
        $order = $this->db->selectOne($orderQuery, [$orderId]);

        if (!$order || $order['status'] !== 'approved') {
            return [
                'success' => false,
                'message' => 'Замовлення не знайдено або ще не схвалено'
            ];
        }

        // Отримуємо елементи замовлення
        $itemsQuery = "SELECT oi.*, p.name as product_name, p.quantity as current_stock
                      FROM order_items oi
                      JOIN products p ON oi.product_id = p.id
                      WHERE oi.order_id = ?";
        $items = $this->db->select($itemsQuery, [$orderId]);

        if (empty($items)) {
            return [
                'success' => false,
                'message' => 'У замовленні відсутні товари'
            ];
        }

        // Починаємо транзакцію
        $this->db->execute("START TRANSACTION");

        $success = true;
        $errors = [];

        foreach ($items as $item) {
            // Додаємо транзакцію для товару
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
                $errors[] = "Помилка при обробці товару: " . $item['product_name'];
                break;
            }

            // Оновлюємо кількість товару на складі
            $newQuantity = $item['current_stock'] + $item['quantity'];
            $updateQuery = "UPDATE products SET quantity = ? WHERE id = ?";
            
            $updateResult = $this->db->execute($updateQuery, [
                $newQuantity,
                $item['product_id']
            ]);

            if (!$updateResult) {
                $success = false;
                $errors[] = "Помилка при оновленні кількості товару: " . $item['product_name'];
                break;
            }
        }

        if ($success) {
            // Оновлюємо статус замовлення
            $updateOrderQuery = "UPDATE orders SET status = 'received' WHERE id = ?";
            $updateOrderResult = $this->db->execute($updateOrderQuery, [$orderId]);

            if ($updateOrderResult) {
                $this->db->execute("COMMIT");
                return [
                    'success' => true,
                    'message' => 'Товари успішно прийнято на склад'
                ];
            } else {
                $this->db->execute("ROLLBACK");
                return [
                    'success' => false,
                    'message' => 'Помилка при оновленні статусу замовлення'
                ];
            }
        } else {
            $this->db->execute("ROLLBACK");
            return [
                'success' => false,
                'message' => 'Виникли помилки при прийомі товарів: ' . implode(', ', $errors)
            ];
        }
    }
}
