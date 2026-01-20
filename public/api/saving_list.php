<?php
require __DIR__ . "/../../app/db.php";
session_start();

header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["ok" => false, "error" => "Нет авторизации"]);
    exit;
}
$userId = (int)$_SESSION["user_id"];

// баланс
$stmt = $pdo->prepare('SELECT balance FROM "user" WHERE user_id = :id');
$stmt->execute(["id" => $userId]);
$balance = $stmt->fetchColumn();

// копилки
$stmt = $pdo->prepare("
    SELECT saving_id, goal_name, target_amount, current_amount
    FROM saving
    WHERE user_id = :uid
    ORDER BY goal_name
");
$stmt->execute(["uid" => $userId]);
$savings = $stmt->fetchAll();

echo json_encode(["ok" => true, "balance" => (string)$balance, "savings" => $savings]);
