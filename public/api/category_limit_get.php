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
$year = (int)($_GET["year"] ?? 0);
$month = (int)($_GET["month"] ?? 0);

if ($categoryId <= 0 || $year <= 0 || $month <= 0) {
    echo json_encode(["ok" => false, "error" => "Некорректные параметры"]);
    exit;
}

// категория пользователя?
$stmt = $pdo->prepare("SELECT 1 FROM expense_category WHERE category_id = :cid AND user_id = :uid");
$stmt->execute(["cid" => $categoryId, "uid" => $userId]);
if (!$stmt->fetchColumn()) {
    echo json_encode(["ok" => false, "error" => "Категория не найдена"]);
    exit;
}

// лимит (если нет записи, считаем что лимит не задан)
$stmt = $pdo->prepare("
    SELECT limit_amount
    FROM category_limit
    WHERE category_id = :cid AND year = :y AND month = :m
");
$stmt->execute(["cid" => $categoryId, "y" => $year, "m" => $month]);
$limit = $stmt->fetchColumn();
if ($limit === false) $limit = null;

// потрачено за месяц по категории
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total_amount), 0)
    FROM expense
    WHERE user_id = :uid
      AND category_id = :cid
      AND EXTRACT(YEAR FROM expense_datetime) = :y
      AND EXTRACT(MONTH FROM expense_datetime) = :m
");
$stmt->execute(["uid" => $userId, "cid" => $categoryId, "y" => $year, "m" => $month]);
$spent = (float)$stmt->fetchColumn();

$limitIsSet = ($limit !== null);
$limitF = $limitIsSet ? (float)$limit : 0.0;
$remaining = $limitIsSet ? ($limitF - $spent) : null;
// Важно: лимит 0 означает "тратить нельзя".
$isOver = $limitIsSet ? ($spent > $limitF) : false;

echo json_encode([
    "ok" => true,
    "limit_is_set" => $limitIsSet,
    "limit_amount" => $limitIsSet ? number_format($limitF, 2, '.', '') : "",
    "spent_amount" => number_format($spent, 2, '.', ''),
    "remaining" => ($remaining === null) ? "" : number_format($remaining, 2, '.', ''),
    "is_over" => $isOver
]);
