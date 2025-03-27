<?php
// controllers/AuthController.php
// Контролер для авторизації та реєстрації користувачів

if (!defined('ROOT_PATH')) {
    require_once dirname(__DIR__) . '/config.php';
}

require_once ROOT_PATH . '/config/database.php';
class AuthController {
    private $db;

    public function __construct() {
        $this->db = new Database();
        $this->db->getConnection();
        session_start();
    }

    // Метод для авторизації користувача
    public function login($username, $password) {
        // Валідація вхідних даних
        if (empty($username) || empty($password)) {
            return [
                'success' => false,
                'message' => 'Будь ласка, введіть логін та пароль'
            ];
        }

        // SQL запит для перевірки користувача
        $query = "SELECT * FROM users WHERE username = ?";
        $user = $this->db->selectOne($query, [$username]);

        // Перевіряємо існування користувача та правильність пароля
        if ($user && password_verify($password, $user['password'])) {
            // Записуємо дані користувача в сесію
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];

            // Перенаправляємо на відповідну сторінку залежно від ролі
            return [
                'success' => true,
                'role' => $user['role'],
                'message' => 'Ви успішно увійшли в систему'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Невірний логін або пароль'
            ];
        }
    }

    // Метод для реєстрації нового постачальника
    public function registerSupplier($userData, $supplierData) {
        // Валідація вхідних даних
        if (empty($userData['username']) || empty($userData['password']) || 
            empty($userData['name']) || empty($userData['email']) ||
            empty($supplierData['company_name']) || empty($supplierData['contact_person']) ||
            empty($supplierData['phone']) || empty($supplierData['address'])) {
            return [
                'success' => false,
                'message' => 'Будь ласка, заповніть усі обов\'язкові поля'
            ];
        }

        // Перевіряємо, чи існує вже користувач з таким username або email
        $query = "SELECT * FROM users WHERE username = ? OR email = ?";
        $existingUser = $this->db->selectOne($query, [$userData['username'], $userData['email']]);

        if ($existingUser) {
            return [
                'success' => false,
                'message' => 'Користувач з таким логіном або email вже існує'
            ];
        }

        // Хешуємо пароль
        $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);

        // Починаємо транзакцію
        $this->db->execute("START TRANSACTION");

        // Вставляємо запис в таблицю users
        $insertUserQuery = "INSERT INTO users (username, password, role, name, email) VALUES (?, ?, 'supplier', ?, ?)";
        $userInserted = $this->db->execute($insertUserQuery, [
            $userData['username'],
            $hashedPassword,
            $userData['name'],
            $userData['email']
        ]);

        // Якщо користувач створений успішно, створюємо запис постачальника
        if ($userInserted) {
            $userId = $this->db->lastInsertId();

            $insertSupplierQuery = "INSERT INTO suppliers (user_id, company_name, contact_person, phone, address) VALUES (?, ?, ?, ?, ?)";
            $supplierInserted = $this->db->execute($insertSupplierQuery, [
                $userId,
                $supplierData['company_name'],
                $supplierData['contact_person'],
                $supplierData['phone'],
                $supplierData['address']
            ]);

            if ($supplierInserted) {
                $this->db->execute("COMMIT");
                return [
                    'success' => true,
                    'message' => 'Реєстрація успішно завершена. Тепер ви можете увійти в систему.'
                ];
            } else {
                $this->db->execute("ROLLBACK");
                return [
                    'success' => false,
                    'message' => 'Помилка при створенні постачальника'
                ];
            }
        } else {
            $this->db->execute("ROLLBACK");
            return [
                'success' => false,
                'message' => 'Помилка при створенні користувача'
            ];
        }
    }

    // Метод для виходу з системи
    public function logout() {
        // Знищуємо всі дані сесії
        session_unset();
        session_destroy();
        
        return [
            'success' => true,
            'message' => 'Ви успішно вийшли з системи'
        ];
    }

    // Перевірка авторизації
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    // Перевірка ролі користувача
    public function checkRole($role) {
        if (!$this->isLoggedIn()) {
            return false;
        }

        if (is_array($role)) {
            return in_array($_SESSION['role'], $role);
        } else {
            return $_SESSION['role'] === $role;
        }
    }

    // Метод для отримання інформації про поточного користувача
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }

        $query = "SELECT id, username, role, name, email, created_at FROM users WHERE id = ?";
        return $this->db->selectOne($query, [$_SESSION['user_id']]);
    }
}
