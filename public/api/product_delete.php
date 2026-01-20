<?php
require __DIR__ . "/../../app/db.php";
session_start();

header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["ok" => false, "error" => "Нет авторизации"]);
    exit;
}
$userId = (int)$_SESSION["user_id"];

$productId = (int)($_POST["product_id"] ?? 0);
if ($productId <= 0) {
    echo json_encode(["ok" => false, "error" => "Некорректный товар"]);
    exit;
}

// проверяем, что товар относится к категории пользователя
$stmt = $pdo->prepare(
    "SELECT p.product_id
     FROM product p
     JOIN expense_category c ON c.category_id = p.category_id
     WHERE p.product_id = :pid AND c.user_id = :uid"
);
$stmt->execute(["pid" => $productId, "uid" => $userId]);
if (!$stmt->fetch()) {
    echo json_encode(["ok" => false, "error" => "Товар не найден"]);
    exit;
}

// если товар уже использован в расходах, не удаляем (студенческий вариант: просто запрещаем)
$stmt = $pdo->prepare("SELECT 1 FROM expense_product WHERE product_id = :pid LIMIT 1");
$stmt->execute(["pid" => $productId]);
if ($stmt->fetch()) {
    echo json_encode(["ok" => false, "error" => "Нельзя удалить: товар уже использован в расходах"]);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM product WHERE product_id = :pid");
    $stmt->execute(["pid" => $productId]);
    echo json_encode(["ok" => true]);
} catch (Exception $e) {
    echo json_encode(["ok" => false, "error" => "Ошибка БД"]);
}
