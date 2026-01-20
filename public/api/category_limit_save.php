<?php
require __DIR__ . "/../../app/db.php";
session_start();

header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["ok" => false, "error" => "Нет авторизации"]);
    exit;
}
$userId = (int)$_SESSION["user_id"];

$categoryId = (int)($_POST["category_id"] ?? 0);
$year = (int)($_POST["year"] ?? 0);
$month = (int)($_POST["month"] ?? 0);
$limitRaw = $_POST["limit_amount"] ?? "";

if ($categoryId <= 0 || $year <= 0 || $month <= 0) {
    echo json_encode(["ok" => false, "error" => "Заполните категорию, год и месяц"]);
    exit;
}

// Пустое поле — снять лимит (удалить запись на период)
$limitRawTrimmed = trim((string)$limitRaw);
$removeLimit = ($limitRawTrimmed === "");

if (!$removeLimit) {
    $limit = (float)$limitRawTrimmed;
    if ($limit < 0) {
        echo json_encode(["ok" => false, "error" => "Сумма лимита не может быть меньше 0"]);
        exit;
    }
}

// категория пользователя?
$stmt = $pdo->prepare("SELECT 1 FROM expense_category WHERE category_id = :cid AND user_id = :uid");
$stmt->execute(["cid" => $categoryId, "uid" => $userId]);
if (!$stmt->fetchColumn()) {
    echo json_encode(["ok" => false, "error" => "Категория не найдена"]);
    exit;
}

try {
    if ($removeLimit) {
        $stmt = $pdo->prepare("
            DELETE FROM category_limit
            WHERE category_id = :cid AND year = :y AND month = :m
        ");
        $stmt->execute(["cid" => $categoryId, "y" => $year, "m" => $month]);
        echo json_encode(["ok" => true, "action" => "removed"]);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO category_limit (category_id, year, month, limit_amount)
        VALUES (:cid, :y, :m, :amt)
        ON CONFLICT (category_id, year, month)
        DO UPDATE SET limit_amount = EXCLUDED.limit_amount
    ");
    $stmt->execute(["cid" => $categoryId, "y" => $year, "m" => $month, "amt" => $limit]);

    echo json_encode(["ok" => true, "action" => "saved"]);
} catch (Exception $e) {
    echo json_encode(["ok" => false, "error" => "Ошибка БД"]);
}
