<?php
// init.php
// Файл инициализации для настройки путей и общих настроек

// Проверяем, определена ли константа ROOT_PATH
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__);
}

// Функция для автоматической загрузки классов контроллеров
function autoloadController($className) {
    $path = ROOT_PATH . '/controllers/' . $className . '.php';
    if (file_exists($path)) {
        require_once $path;
    }
}

// Регистрируем функцию автозагрузки
spl_autoload_register('autoloadController');
?>