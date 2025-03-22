<?php
// config.php
// Центральный файл конфигурации 

// Определяем ROOT_PATH только если он еще не определен
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__);
}

// Режим отладки
define('DEBUG_MODE', true);

// SQL логирование
define('SQL_DEBUG', true);

// Функция для определения относительного пути к файлу
function get_relative_path($path) {
    return ROOT_PATH . '/' . $path;
}

// Функция для автоматической загрузки классов контроллеров
function autoload_controller($className) {
    $path = ROOT_PATH . '/controllers/' . $className . '.php';
    if (file_exists($path)) {
        require_once $path;
    }
}

// Настройка обработки ошибок в режиме отладки
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Создаем директорию для логов, если её нет
$logsDir = ROOT_PATH . '/logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

// Установка обработчика для неперехваченных исключений
set_exception_handler(function($exception) {
    // Путь к файлу лога ошибок
    $logFile = ROOT_PATH . '/logs/errors_' . date('Y-m-d') . '.log';
    
    // Формирование сообщения об ошибке
    $message = '[' . date('Y-m-d H:i:s') . '] Uncaught Exception: ' . $exception->getMessage() . PHP_EOL;
    $message .= 'File: ' . $exception->getFile() . ' on line ' . $exception->getLine() . PHP_EOL;
    $message .= 'Stack trace: ' . PHP_EOL . $exception->getTraceAsString() . PHP_EOL . PHP_EOL;
    
    // Запись в лог файл
    file_put_contents($logFile, $message, FILE_APPEND);
    
    // Если режим отладки включен, показываем ошибку
    if (DEBUG_MODE) {
        echo "<div style='background-color:#f8d7da; color:#721c24; padding:15px; margin:15px; border:1px solid #f5c6cb; border-radius:4px;'>";
        echo "<h3>Ошибка!</h3>";
        echo "<p>" . htmlspecialchars($exception->getMessage()) . "</p>";
        echo "<p>Файл: " . htmlspecialchars($exception->getFile()) . " на строке " . $exception->getLine() . "</p>";
        if (DEBUG_MODE) {
            echo "<h4>Stack Trace:</h4>";
            echo "<pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
        }
        echo "</div>";
    } else {
        // В продакшн режиме показываем общее сообщение об ошибке
        echo "<div style='background-color:#f8d7da; color:#721c24; padding:15px; margin:15px; border:1px solid #f5c6cb; border-radius:4px;'>";
        echo "<h3>Произошла ошибка</h3>";
        echo "<p>Пожалуйста, попробуйте позже или обратитесь к администратору.</p>";
        echo "</div>";
    }
});

// Регистрируем функцию автозагрузки (опционально)
// spl_autoload_register('autoload_controller');