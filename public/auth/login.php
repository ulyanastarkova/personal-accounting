<?php
require __DIR__ . "/../../app/db.php";
session_start();

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");     // НЕ приводим к нижнему регистру
    $password = trim($_POST["password"] ?? "");

    $stmt = $pdo->prepare('SELECT user_id, password, name FROM "user" WHERE email = :email');
    $stmt->execute(["email" => $email]);
    $user = $stmt->fetch();

    if (!$user || $user["password"] !== $password) {
        $message = "Неверный email или пароль.";
    } else {
        $_SESSION["user_id"] = (int)$user["user_id"];
        header("Location: /dashboard.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход — Личная бухгалтерия</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<h1>Вход</h1>

<div class="container">
    <div class="card">
        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="post">
            <p>
                <label>Email:</label><br>
                <input type="email" name="email" required>
            </p>

            <p>
                <label>Пароль:</label><br>
                <input type="password" name="password" required>
            </p>

            <button type="submit">Войти</button>
        </form>

        <p style="margin-top:12px;">
            Нет аккаунта? <a href="/auth/register.php">Регистрация</a>
        </p>
    </div>
</div>
</body>
</html>
