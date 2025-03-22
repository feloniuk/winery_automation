<?php
// utils/Logger.php
// Класс для логирования ошибок и событий

// Подключаем конфигурационный файл, если еще не подключен
if (!defined('ROOT_PATH')) {
    require_once dirname(__DIR__) . '/config.php';
}

class Logger {
    // Типы логов
    const ERROR = 'ERROR';
    const WARNING = 'WARNING';
    const INFO = 'INFO';
    const DEBUG = 'DEBUG';
    
    // Директория для хранения логов
    private static $logDir;
    
    // Инициализация класса
    public static function init() {
        // Устанавливаем директорию для логов
        self::$logDir = ROOT_PATH . '/logs';
        
        // Создаем директорию, если её нет
        if (!is_dir(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }
    }
    
    /**
     * Запись сообщения в лог
     * 
     * @param string $message Текст сообщения
     * @param string $type Тип лога
     * @param string $file Имя файла лога (без расширения)
     * @return bool Результат записи
     */
    public static function log($message, $type = self::INFO, $file = 'app') {
        // Инициализируем, если еще не было
        if (!isset(self::$logDir)) {
            self::init();
        }
        
        // Формируем имя файла лога
        $logFile = self::$logDir . '/' . $file . '_' . date('Y-m-d') . '.log';
        
        // Формируем строку лога
        $logMessage = '[' . date('Y-m-d H:i:s') . '] [' . $type . '] ' . $message . PHP_EOL;
        
        // Записываем в файл
        return file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * Запись SQL-ошибки в лог
     * 
     * @param string $query SQL-запрос
     * @param array $params Параметры запроса
     * @param \PDOException $exception Объект исключения
     * @param string $file Имя файла лога (без расширения)
     * @return bool Результат записи
     */
    public static function logSqlError($query, $params, $exception, $file = 'sql_errors') {
        // Инициализируем, если еще не было
        if (!isset(self::$logDir)) {
            self::init();
        }
        
        // Формируем сообщение об ошибке
        $message = "SQL Error: " . $exception->getMessage() . PHP_EOL;
        $message .= "Query: " . $query . PHP_EOL;
        $message .= "Params: " . json_encode($params) . PHP_EOL;
        $message .= "Stack trace: " . PHP_EOL . $exception->getTraceAsString();
        
        // Записываем в лог
        return self::log($message, self::ERROR, $file);
    }
    
    /**
     * Запись запроса SQL в лог (для отладки)
     * 
     * @param string $query SQL-запрос
     * @param array $params Параметры запроса
     * @param string $file Имя файла лога (без расширения)
     * @return bool Результат записи
     */
    public static function logSqlQuery($query, $params, $file = 'sql_queries') {
        // Формируем сообщение
        $message = "Query: " . $query . PHP_EOL;
        $message .= "Params: " . json_encode($params);
        
        // Записываем в лог
        return self::log($message, self::DEBUG, $file);
    }
}