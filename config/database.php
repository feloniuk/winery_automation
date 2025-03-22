<?php
// config/database.php
// Конфигурация подключения к базе данных

class Database {
    private $host = 'localhost';
    private $db_name = 'winery_automation';
    private $username = 'root';
    private $password = '';
    private $conn;

    // Метод подключения к базе данных
    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $e) {
            echo "Ошибка подключения к базе данных: " . $e->getMessage();
        }

        return $this->conn;
    }

    // Метод для выполнения запроса SELECT и получения результатов
    public function select($query, $params = []) {
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            echo "Ошибка выполнения запроса: " . $e->getMessage();
            return false;
        }
    }

    // Метод для выполнения запроса и получения одной строки
    public function selectOne($query, $params = []) {
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            echo "Ошибка выполнения запроса: " . $e->getMessage();
            return false;
        }
    }

    // Метод для выполнения запросов INSERT, UPDATE, DELETE
    public function execute($query, $params = []) {
        try {
            $stmt = $this->conn->prepare($query);
            return $stmt->execute($params);
        } catch(PDOException $e) {
            echo "Ошибка выполнения запроса: " . $e->getMessage();
            return false;
        }
    }

    // Получить ID последней вставленной записи
    public function lastInsertId() {
        return $this->conn->lastInsertId();
    }
}