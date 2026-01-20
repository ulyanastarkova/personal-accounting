<?php
require __DIR__ . "/../../app/db.php";
session_start();

header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["ok" => false, "error" => "Нет авторизации"]);
    exit;
}
$userId = (int)$_SESSION["user_id"];

$incomeId = (int)($_POST["income_id"] ?? 0);
if ($incomeId <= 0) {
    echo json_encode(["ok" => false, "error" => "Некорректный доход"]);
    exit;
}

// проверяем, что доход принадлежит пользователю
$stmt = $pdo->prepare("SELECT amount FROM income WHERE income_id = :iid AND user_id = :uid");
$stmt->execute(["iid" => $incomeId, "uid" => $userId]);
$amount = $stmt->fetchColumn();

if ($amount === false) {
    echo json_encode(["ok" => false, "error" => "Доход не найден"]);
    exit;
}

try {
    $pdo->beginTransaction();

    // удаляем доход
    $stmt = $pdo->prepare("DELETE FROM income WHERE income_id = :iid AND user_id = :uid");
    $stmt->execute(["iid" => $incomeId, "uid" => $userId]);

    // откатываем баланс: минусуем сумму дохода
    $stmt = $pdo->prepare('UPDATE "user" SET balance = balance - :amt WHERE user_id = :uid RETURNING balance');
    $stmt->execute(["amt" => (float)$amount, "uid" => $userId]);
    $balance = $stmt->fetchColumn();

    $pdo->commit();
    echo json_encode(["ok" => true, "balance" => (string)$balance]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(["ok" => false, "error" => "Ошибка БД"]);
}
