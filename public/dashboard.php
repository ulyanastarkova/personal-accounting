<?php
require __DIR__ . "/../app/db.php";
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: /auth/login.php");
    exit;
}

$stmt = $pdo->prepare('SELECT name, balance, email FROM "user" WHERE user_id = :id');
$stmt->execute(["id" => $_SESSION["user_id"]]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: /auth/login.php");
    exit;
}

$year = (int)date('Y');
$month = (int)date('n');

$stmt = $pdo->prepare("
    SELECT c.category_name,
           cl.limit_amount,
           COALESCE(SUM(e.total_amount), 0) AS spent
    FROM category_limit cl
    JOIN expense_category c ON c.category_id = cl.category_id
    LEFT JOIN expense e
      ON e.category_id = cl.category_id
     AND e.user_id = c.user_id
     AND EXTRACT(YEAR FROM e.expense_datetime) = cl.year
     AND EXTRACT(MONTH FROM e.expense_datetime) = cl.month
    WHERE c.user_id = :uid
      AND cl.year = :y
      AND cl.month = :m
      AND cl.limit_amount > 0
    GROUP BY c.category_name, cl.limit_amount
    HAVING COALESCE(SUM(e.total_amount), 0) > cl.limit_amount
    ORDER BY (COALESCE(SUM(e.total_amount), 0) - cl.limit_amount) DESC
");
$stmt->execute(["uid" => (int)$_SESSION["user_id"], "y" => $year, "m" => $month]);
$overLimits = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Кабинет — Личная бухгалтерия</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="topbar">
    <div class="topbar__inner">
        <div class="brand">Личная бухгалтерия</div>
        <a class="link" href="/auth/logout.php">Выйти</a>
    </div>
</header>

<div class="container">
    <div class="card">
        <h2 style="margin-top:0;">Личный кабинет</h2>

        <p>Пользователь: <b><?= htmlspecialchars($user["name"]) ?></b></p>
        <p>Email: <b><?= htmlspecialchars($user["email"]) ?></b></p>
        <p>Баланс: <b><?= htmlspecialchars($user["balance"]) ?></b></p>
        <?php if (!empty($overLimits)): ?>
          <div class="message" style="margin-top:12px;">
            <b>⚠ Превышены лимиты за <?= htmlspecialchars($month) ?>/<?= htmlspecialchars($year) ?>:</b><br>
            <?php foreach ($overLimits as $row): ?>
              <?= htmlspecialchars($row["category_name"]) ?>:
              лимит <?= htmlspecialchars($row["limit_amount"]) ?>,
              потрачено <?= htmlspecialchars($row["spent"]) ?><br>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="actions">
            <a class="btn btn-primary" href="/income.php">Добавить доходы</a>
            <a class="btn btn-secondary" href="/expense.php">Добавить расходы</a>
            <a class="btn btn-saving" href="/saving.php">Мои копилки</a>
            <a class="btn btn-limit" href="/limits.php">Лимиты категорий</a>
        </div>
    </div>
</div>

</body>
</html>
