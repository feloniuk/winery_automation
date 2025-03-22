<?php
// index.php
// Главная страница с автоматическим редиректом на страницу авторизации

// Подключаем конфигурационный файл
require_once 'config.php';

// Переадресация на страницу логина
header('Location: views/auth/login.php');
exit;
?>