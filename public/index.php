<?php
// Точка входа сайта.
// Требование: при заходе на домен пользователь сначала видит страницу входа.

session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit;
}

header('Location: /auth/login.php');
exit;
