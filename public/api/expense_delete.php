<?php
require __DIR__ . "/../../app/db.php";
session_start();

header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["ok" => false, "error" => "Нет авторизации"]);
    exit;
}
$userId = (int)$_SESSION["user_id"];

$expenseId = (int)($_POST["expense_id"] ?? 0);
if ($expenseId <= 0) {
    echo json_encode(["ok" => false, "error" => "Некорректный расход"]);
    exit;
}

// проверяем, что расход принадлежит пользователю
$stmt = $pdo->prepare("SELECT total_amount FROM expense WHERE expense_id = :eid AND user_id = :uid");
$stmt->execute(["eid" => $expenseId, "uid" => $userId]);
$total = $stmt->fetchColumn();

if ($total === false) {
    echo json_encode(["ok" => false, "error" => "Расход не найден"]);
    exit;
}

try {
    $pdo->beginTransaction();

    // удаляем связи с товарами
    $stmt = $pdo->prepare("DELETE FROM expense_product WHERE expense_id = :eid");
    $stmt->execute(["eid" => $expenseId]);

    // удаляем расход
    $stmt = $pdo->prepare("DELETE FROM expense WHERE expense_id = :eid AND user_id = :uid");
    $stmt->execute(["eid" => $expenseId, "uid" => $userId]);

    // откатываем баланс: возвращаем сумму расхода
    $stmt = $pdo->prepare('UPDATE "user" SET balance = balance + :amt WHERE user_id = :uid RETURNING balance');
    $stmt->execute(["amt" => (float)$total, "uid" => $userId]);
    $balance = $stmt->fetchColumn();

    $pdo->commit();
    echo json_encode(["ok" => true, "balance" => (string)$balance]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(["ok" => false, "error" => "Ошибка БД"]);
}
