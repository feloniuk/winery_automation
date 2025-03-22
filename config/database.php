<?php
// config/database.php
// Конфигурация подключения к базе данных

// Подключаем конфигурационный файл, если еще не подключен
if (!defined('ROOT_PATH')) {
    require_once dirname(__DIR__) . '/config.php';
}

// Подключаем класс логирования
require_once ROOT_PATH . '/utils/Logger.php';

class Database {
    private $host = 'localhost';
    private $db_name = 'winery_automation';
    private $username = 'root';
    private $password = '';
    private $conn;
    
    // Режим отладки - логировать все SQL запросы
    private $debug = false;

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
            Logger::logSqlError("Connection attempt", [], $e, 'database_errors');
            echo "Ошибка подключения к базе данных: " . $e->getMessage();
        }

        return $this->conn;
    }

    // Метод для выполнения запроса SELECT и получения результатов
    public function select($query, $params = []) {
        try {
            // В режиме отладки логируем запрос
            if ($this->debug) {
                Logger::logSqlQuery($query, $params);
            }
            
            $stmt = $this->conn->prepare($query);
            $this->bindParams($stmt, $params);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            // Логируем ошибку SQL
            Logger::logSqlError($query, $params, $e);
            echo "Ошибка выполнения запроса: " . $e->getMessage();
            return false;
        }
    }

    // Метод для выполнения запроса и получения одной строки
    public function selectOne($query, $params = []) {
        try {
            // В режиме отладки логируем запрос
            if ($this->debug) {
                Logger::logSqlQuery($query, $params);
            }
            
            $stmt = $this->conn->prepare($query);
            $this->bindParams($stmt, $params);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            // Логируем ошибку SQL
            Logger::logSqlError($query, $params, $e);
            echo "Ошибка выполнения запроса: " . $e->getMessage();
            return false;
        }
    }

    // Метод для выполнения запросов INSERT, UPDATE, DELETE
    public function execute($query, $params = []) {
        try {
            // В режиме отладки логируем запрос
            if ($this->debug) {
                Logger::logSqlQuery($query, $params);
            }
            
            $stmt = $this->conn->prepare($query);
            $this->bindParams($stmt, $params);
            return $stmt->execute();
        } catch(PDOException $e) {
            // Логируем ошибку SQL
            Logger::logSqlError($query, $params, $e);
            echo "Ошибка выполнения запроса: " . $e->getMessage();
            return false;
        }
    }

    // Получить ID последней вставленной записи
    public function lastInsertId() {
        return $this->conn->lastInsertId();
    }
    
    // Включить/выключить режим отладки
    public function setDebug($debug) {
        $this->debug = $debug;
    }
    
    // Метод для правильного связывания параметров с учетом их типов
    private function bindParams($stmt, $params) {
        if (is_array($params)) {
            // Если $params - это обычный массив (не ассоциативный), связываем параметры по порядку
            if (array_keys($params) === range(0, count($params) - 1)) {
                foreach ($params as $i => $param) {
                    $position = $i + 1; // PDO использует позиции с 1
                    if (is_null($param)) {
                        $stmt->bindValue($position, $param, PDO::PARAM_NULL);
                    } elseif (is_int($param)) {
                        $stmt->bindValue($position, $param, PDO::PARAM_INT);
                    } elseif (is_bool($param)) {
                        $stmt->bindValue($position, $param, PDO::PARAM_BOOL);
                    } else {
                        $stmt->bindValue($position, $param, PDO::PARAM_STR);
                    }
                }
            } else {
                // Если $params - ассоциативный массив, связываем по именам параметров
                foreach ($params as $key => $param) {
                    if (is_null($param)) {
                        $stmt->bindValue(':' . $key, $param, PDO::PARAM_NULL);
                    } elseif (is_int($param)) {
                        $stmt->bindValue(':' . $key, $param, PDO::PARAM_INT);
                    } elseif (is_bool($param)) {
                        $stmt->bindValue(':' . $key, $param, PDO::PARAM_BOOL);
                    } else {
                        $stmt->bindValue(':' . $key, $param, PDO::PARAM_STR);
                    }
                }
            }
        }
    }
}