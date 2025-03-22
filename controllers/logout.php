<?php
// controllers/logout.php
// Скрипт для выхода из системы

// Подключаем контроллер авторизации
require_once 'AuthController.php';

$authController = new AuthController();

// Выполняем выход из системы
$result = $authController->logout();

// Перенаправляем на главную страницу
header('Location: ../index.php');
exit;
?>