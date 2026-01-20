<?php
require __DIR__ . "/../../app/db.php";
session_start();

header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["ok" => false, "error" => "Нет авторизации"]);
    exit;
}
$userId = (int)$_SESSION["user_id"];

$categoryId = (int)($_GET["category_id"] ?? 0);
if ($categoryId <= 0) {
    echo json_encode(["ok" => true, "products" => []]);
    exit;
}

// проверим, что категория принадлежит пользователю
$stmt = $pdo->prepare("SELECT category_id FROM expense_category WHERE category_id = :cid AND user_id = :uid");
$stmt->execute(["cid" => $categoryId, "uid" => $userId]);
if (!$stmt->fetch()) {
    echo json_encode(["ok" => false, "error" => "Категория не найдена"]);
    exit;
}

$stmt = $pdo->prepare("SELECT product_id, product_name FROM product WHERE category_id = :cid ORDER BY product_name");
$stmt->execute(["cid" => $categoryId]);
echo json_encode(["ok" => true, "products" => $stmt->fetchAll()]);
