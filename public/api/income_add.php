<?php
require __DIR__ . "/../../app/db.php";
session_start();

header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["ok" => false, "error" => "Нет авторизации"]);
    exit;
}

$userId = (int)$_SESSION["user_id"];

$incomeDateTimeRaw = $_POST["income_datetime"] ?? "";

// input[type=datetime-local] отправляет формат вида 2026-01-11T14:35
// PostgreSQL ожидает пробел вместо 'T'.
$incomeDateTime = str_replace('T', ' ', $incomeDateTimeRaw);

$amountRaw  = $_POST["amount"] ?? "";
$sourceIdRaw = $_POST["source_id"] ?? "";
$newSource = trim($_POST["new_source"] ?? "");

if ($incomeDateTimeRaw === "" || $amountRaw === "") {
    echo json_encode(["ok" => false, "error" => "Заполните дату/время и сумму"]);
    exit;
}

$amount = (float)$amountRaw;
if ($amount <= 0) {
    echo json_encode(["ok" => false, "error" => "Сумма должна быть больше 0"]);
    exit;
}

// Определяем источник дохода:
// - если выбран source_id, используем его
// - иначе создаём новый источник из new_source
$sourceId = null;

if ($sourceIdRaw !== "") {
    $sourceId = (int)$sourceIdRaw;

    // проверим, что источник принадлежит пользователю
    $stmt = $pdo->prepare("SELECT source_id, source_name FROM income_source WHERE source_id = :sid AND user_id = :uid");
    $stmt->execute(["sid" => $sourceId, "uid" => $userId]);
    $source = $stmt->fetch();

    if (!$source) {
        echo json_encode(["ok" => false, "error" => "Источник не найден"]);
        exit;
    }
} else {
    if ($newSource === "") {
        echo json_encode(["ok" => false, "error" => "Выберите источник или введите новый"]);
        exit;
    }

    // если такой источник уже есть — возьмём его
    $stmt = $pdo->prepare("SELECT source_id, source_name FROM income_source WHERE user_id = :uid AND source_name = :name");
    $stmt->execute(["uid" => $userId, "name" => $newSource]);
    $source = $stmt->fetch();

    if ($source) {
        $sourceId = (int)$source["source_id"];
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO income_source (user_id, source_name)
             VALUES (:uid, :name)
             RETURNING source_id, source_name"
        );
        $stmt->execute(["uid" => $userId, "name" => $newSource]);
        $source = $stmt->fetch();
        $sourceId = (int)$source["source_id"];
    }
}

// Записываем доход и обновляем баланс в одной транзакции
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "INSERT INTO income (user_id, source_id, income_datetime, amount)
         VALUES (:uid, :sid, :dt, :amt)
         RETURNING income_id"
    );
    $stmt->execute([
        "uid" => $userId,
        "sid" => $sourceId,
        "dt"  => $incomeDateTime,
        "amt" => $amount
    ]);

    // баланс = баланс + amount
    $stmt = $pdo->prepare('UPDATE "user" SET balance = balance + :amt WHERE user_id = :uid RETURNING balance');
    $stmt->execute(["amt" => $amount, "uid" => $userId]);
    $newBalance = $stmt->fetchColumn();

    $pdo->commit();

    echo json_encode([
        "ok" => true,
        "balance" => (string)$newBalance,
        "income_datetime" => $incomeDateTime,
        "amount" => number_format($amount, 2, '.', ''),
        "source_name" => $source["source_name"] ?? $newSource
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(["ok" => false, "error" => "Ошибка БД"]);
}
