<?php
// controllers/AuthController.php
// Контроллер для авторизации и регистрации пользователей

require_once 'config/database.php';

class AuthController {
    private $db;

    public function __construct() {
        $this->db = new Database();
        $this->db->getConnection();
        session_start();
    }

    // Метод для авторизации пользователя
    public function login($username, $password) {
        // Валидация входных данных
        if (empty($username) || empty($password)) {
            return [
                'success' => false,
                'message' => 'Пожалуйста, введите логин и пароль'
            ];
        }

        // SQL запрос для проверки пользователя
        $query = "SELECT * FROM users WHERE username = ?";
        $user = $this->db->selectOne($query, [$username]);

        // Проверяем существование пользователя и правильность пароля
        if ($user && password_verify($password, $user['password'])) {
            // Записываем данные пользователя в сессию
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];

            // Перенаправляем на соответствующую страницу в зависимости от роли
            return [
                'success' => true,
                'role' => $user['role'],
                'message' => 'Вы успешно вошли в систему'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Неверный логин или пароль'
            ];
        }
    }

    // Метод для регистрации нового поставщика
    public function registerSupplier($userData, $supplierData) {
        // Валидация входных данных
        if (empty($userData['username']) || empty($userData['password']) || 
            empty($userData['name']) || empty($userData['email']) ||
            empty($supplierData['company_name']) || empty($supplierData['contact_person']) ||
            empty($supplierData['phone']) || empty($supplierData['address'])) {
            return [
                'success' => false,
                'message' => 'Пожалуйста, заполните все обязательные поля'
            ];
        }

        // Проверяем, существует ли уже пользователь с таким username или email
        $query = "SELECT * FROM users WHERE username = ? OR email = ?";
        $existingUser = $this->db->selectOne($query, [$userData['username'], $userData['email']]);

        if ($existingUser) {
            return [
                'success' => false,
                'message' => 'Пользователь с таким логином или email уже существует'
            ];
        }

        // Хешируем пароль
        $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);

        // Начинаем транзакцию
        $this->db->execute("START TRANSACTION");

        // Вставляем запись в таблицу users
        $insertUserQuery = "INSERT INTO users (username, password, role, name, email) VALUES (?, ?, 'supplier', ?, ?)";
        $userInserted = $this->db->execute($insertUserQuery, [
            $userData['username'],
            $hashedPassword,
            $userData['name'],
            $userData['email']
        ]);

        // Если пользователь создан успешно, создаем запись поставщика
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
                    'message' => 'Регистрация успешно завершена. Теперь вы можете войти в систему.'
                ];
            } else {
                $this->db->execute("ROLLBACK");
                return [
                    'success' => false,
                    'message' => 'Ошибка при создании поставщика'
                ];
            }
        } else {
            $this->db->execute("ROLLBACK");
            return [
                'success' => false,
                'message' => 'Ошибка при создании пользователя'
            ];
        }
    }

    // Метод для выхода из системы
    public function logout() {
        // Уничтожаем все данные сессии
        session_unset();
        session_destroy();
        
        return [
            'success' => true,
            'message' => 'Вы успешно вышли из системы'
        ];
    }

    // Проверка авторизации
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    // Проверка роли пользователя
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

    // Метод для получения информации о текущем пользователе
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }

        $query = "SELECT id, username, role, name, email, created_at FROM users WHERE id = ?";
        return $this->db->selectOne($query, [$_SESSION['user_id']]);
    }
}