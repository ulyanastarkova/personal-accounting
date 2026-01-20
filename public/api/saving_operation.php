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
$type = $_POST["operation_type"] ?? "";
$operationDateTime = null; // будет фиксироваться текущим временем сервера (NOW())

$amountRaw = $_POST["amount"] ?? "";

if ($savingId <= 0 || $type === "" || $amountRaw === "") {
    echo json_encode(["ok" => false, "error" => "Заполните все поля"]);
    exit;
}
if ($type !== "IN" && $type !== "OUT") {
    echo json_encode(["ok" => false, "error" => "Неверный тип операции"]);
    exit;
}

$amount = (float)$amountRaw;
if ($amount <= 0) {
    echo json_encode(["ok" => false, "error" => "Сумма должна быть больше 0"]);
    exit;
}

// проверим, что копилка пользователя
$stmt = $pdo->prepare("SELECT saving_id, current_amount FROM saving WHERE saving_id = :sid AND user_id = :uid");
$stmt->execute(["sid" => $savingId, "uid" => $userId]);
$saving = $stmt->fetch();

if (!$saving) {
    echo json_encode(["ok" => false, "error" => "Копилка не найдена"]);
    exit;
}

$current = (float)$saving["current_amount"];

try {
    $pdo->beginTransaction();

    if ($type === "IN") {
        // копилка +amount, баланс -amount
        $stmt = $pdo->prepare("UPDATE saving SET current_amount = current_amount + :amt WHERE saving_id = :sid");
        $stmt->execute(["amt" => $amount, "sid" => $savingId]);

        $stmt = $pdo->prepare('UPDATE "user" SET balance = balance - :amt WHERE user_id = :uid');
        $stmt->execute(["amt" => $amount, "uid" => $userId]);
    } else {
        // OUT: нельзя снять больше чем есть
        if ($amount > $current) {
            $pdo->rollBack();
            echo json_encode(["ok" => false, "error" => "Нельзя снять больше, чем накоплено"]);
            exit;
        }

        // копилка -amount, баланс +amount
        $stmt = $pdo->prepare("UPDATE saving SET current_amount = current_amount - :amt WHERE saving_id = :sid");
        $stmt->execute(["amt" => $amount, "sid" => $savingId]);

        $stmt = $pdo->prepare('UPDATE "user" SET balance = balance + :amt WHERE user_id = :uid');
        $stmt->execute(["amt" => $amount, "uid" => $userId]);
    }

    // записываем операцию: время фиксируем автоматически
    $stmt = $pdo->prepare(
        "INSERT INTO saving_operation (saving_id, operation_type, operation_datetime, amount)
         VALUES (:sid, :t, NOW(), :amt)"
    );
    $stmt->execute(["sid" => $savingId, "t" => $type, "amt" => $amount]);

    $pdo->commit();

    echo json_encode(["ok" => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(["ok" => false, "error" => "Ошибка БД"]);
}
