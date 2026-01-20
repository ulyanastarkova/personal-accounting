<?php
require __DIR__ . "/../../app/db.php";
session_start();

header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["ok" => false, "error" => "Нет авторизации"]);
    exit;
}
$userId = (int)$_SESSION["user_id"];

$goal = trim($_POST["goal_name"] ?? "");
$targetRaw = $_POST["target_amount"] ?? "";

if ($goal === "" || $targetRaw === "") {
    echo json_encode(["ok" => false, "error" => "Заполните название и целевую сумму"]);
    exit;
}

$target = (float)$targetRaw;
if ($target <= 0) {
    echo json_encode(["ok" => false, "error" => "Целевая сумма должна быть больше 0"]);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO saving (user_id, goal_name, target_amount, current_amount)
         VALUES (:uid, :goal, :target, 0)"
    );
    $stmt->execute(["uid" => $userId, "goal" => $goal, "target" => $target]);

    echo json_encode(["ok" => true]);
} catch (Exception $e) {
    echo json_encode(["ok" => false, "error" => "Ошибка БД (возможно, копилка с таким названием уже есть)"]);
}
