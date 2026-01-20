<?php
require __DIR__ . "/../../app/db.php";
session_start();

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");       // НЕ приводим к нижнему регистру
    $password = trim($_POST["password"] ?? "");

    if ($name === "" || $email === "" || $password === "") {
        $message = "Заполните все поля.";
    } else {
        // Проверим, что email ещё не занят
        $stmt = $pdo->prepare('SELECT user_id FROM "user" WHERE email = :email');
        $stmt->execute(["email" => $email]);
        if ($stmt->fetch()) {
            $message = "Этот email уже зарегистрирован.";
        } else {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO "user" (email, password, name, balance)
                     VALUES (:email, :password, :name, 0)
                     RETURNING user_id'
                );
                $stmt->execute([
                    "email" => $email,
                    "password" => $password,
                    "name" => $name,
                ]);

                $_SESSION["user_id"] = (int)$stmt->fetchColumn();
                header("Location: /dashboard.php");
                exit;
            } catch (PDOException $e) {
                // Тут часто будет ошибка домена email, если есть большие буквы или неправильный формат
                $message = "Некорректный email (только маленькие латинские буквы, цифры, . _ -) или пароль короче 8 символов.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Регистрация — Личная бухгалтерия</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<h1>Регистрация</h1>

<div class="container">
    <div class="card">
        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="post">
            <p>
                <label>Имя:</label><br>
                <input type="text" name="name" required>
            </p>

            <p>
                <label>Email (только маленькие буквы):</label><br>
                <input type="email" name="email" required>
            </p>

            <p>
                <label>Пароль (мин. 8 символов):</label><br>
                <input type="password" name="password" minlength="8" required>
            </p>

            <button type="submit">Зарегистрироваться</button>
        </form>

        <p style="margin-top:12px;">
            Уже есть аккаунт? <a href="/auth/login.php">Войти</a>
        </p>
    </div>
</div>
</body>
</html>
