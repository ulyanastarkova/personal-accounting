<?php
require __DIR__ . "/../../app/db.php";
session_start();

header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["ok" => false, "error" => "Нет авторизации"]);
    exit;
}
$userId = (int)$_SESSION["user_id"];

$savingId = (int)($_POST["saving_id"] ?? 0);
if ($savingId <= 0) {
    echo json_encode(["ok" => false, "error" => "Некорректная копилка"]);
    exit;
}

// проверяем, что копилка пользователя
$stmt = $pdo->prepare("SELECT current_amount FROM saving WHERE saving_id = :sid AND user_id = :uid");
$stmt->execute(["sid" => $savingId, "uid" => $userId]);
$current = $stmt->fetchColumn();

if ($current === false) {
    echo json_encode(["ok" => false, "error" => "Копилка не найдена"]);
    exit;
}

try {
    $pdo->beginTransaction();

    // удаляем операции копилки
    $stmt = $pdo->prepare("DELETE FROM saving_operation WHERE saving_id = :sid");
    $stmt->execute(["sid" => $savingId]);

    // удаляем копилку
    $stmt = $pdo->prepare("DELETE FROM saving WHERE saving_id = :sid AND user_id = :uid");
    $stmt->execute(["sid" => $savingId, "uid" => $userId]);

    // возвращаем накопленное в баланс
    $stmt = $pdo->prepare('UPDATE "user" SET balance = balance + :amt WHERE user_id = :uid RETURNING balance');
    $stmt->execute(["amt" => (float)$current, "uid" => $userId]);
    $balance = $stmt->fetchColumn();

    $pdo->commit();
    echo json_encode(["ok" => true, "balance" => (string)$balance]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(["ok" => false, "error" => "Ошибка БД"]);
}
