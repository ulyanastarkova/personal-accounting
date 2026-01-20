<?php
require __DIR__ . "/../../app/db.php";
session_start();

header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["ok" => false, "error" => "Нет авторизации"]);
    exit;
}
$userId = (int)$_SESSION["user_id"];

$expenseDateTimeRaw = $_POST["expense_datetime"] ?? "";

// input[type=datetime-local] отправляет формат вида 2026-01-11T14:35
// PostgreSQL ожидает пробел вместо 'T'.
$expenseDateTime = str_replace('T', ' ', $expenseDateTimeRaw);

$totalRaw = $_POST["total_amount"] ?? "";
$categoryIdRaw = $_POST["category_id"] ?? "";
$newCategory = trim($_POST["new_category"] ?? "");
// выбранные товары (могут прийти как product_ids или product_ids[])
$productIds = $_POST["product_ids"] ?? $_POST["product_ids[]"] ?? [];
// новые товары (каждый элемент — название товара)
$newProducts = $_POST["new_products"] ?? $_POST["new_products[]"] ?? [];

if ($expenseDateTimeRaw === "" || $totalRaw === "") {
    echo json_encode(["ok" => false, "error" => "Заполните дату/время и сумму"]);
    exit;
}

$total = (float)$totalRaw;
if ($total <= 0) {
    echo json_encode(["ok" => false, "error" => "Сумма должна быть больше 0"]);
    exit;
}

// 1) Определяем категорию
$categoryCreated = false;
$categoryId = null;
$categoryName = null;

if ($categoryIdRaw !== "") {
    $categoryId = (int)$categoryIdRaw;

    $stmt = $pdo->prepare("SELECT category_id, category_name FROM expense_category WHERE category_id = :cid AND user_id = :uid");
    $stmt->execute(["cid" => $categoryId, "uid" => $userId]);
    $cat = $stmt->fetch();

    if (!$cat) {
        echo json_encode(["ok" => false, "error" => "Категория не найдена"]);
        exit;
    }
    $categoryName = $cat["category_name"];
} else {
    if ($newCategory === "") {
        echo json_encode(["ok" => false, "error" => "Выберите категорию или введите новую"]);
        exit;
    }

    // Если такая категория уже есть — берём её
    $stmt = $pdo->prepare("SELECT category_id, category_name FROM expense_category WHERE user_id = :uid AND category_name = :name");
    $stmt->execute(["uid" => $userId, "name" => $newCategory]);
    $cat = $stmt->fetch();

    if ($cat) {
        $categoryId = (int)$cat["category_id"];
        $categoryName = $cat["category_name"];
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO expense_category (user_id, category_name)
             VALUES (:uid, :name)
             RETURNING category_id, category_name"
        );
        $stmt->execute(["uid" => $userId, "name" => $newCategory]);
        $cat = $stmt->fetch();
        $categoryId = (int)$cat["category_id"];
        $categoryName = $cat["category_name"];
        $categoryCreated = true;
    }
}

try {
    $pdo->beginTransaction();

    // 2) Новые товары: добавляем в справочник и включаем в состав расхода.
    // Поддержка нескольких названий за один расход.
    if (!is_array($productIds)) $productIds = [];
    if (!is_array($newProducts)) $newProducts = [$newProducts];

    foreach ($newProducts as $np) {
        $np = trim((string)$np);
        if ($np === "") continue;

        $stmt = $pdo->prepare("SELECT product_id FROM product WHERE category_id = :cid AND product_name = :pname");
        $stmt->execute(["cid" => $categoryId, "pname" => $np]);
        $existing = $stmt->fetch();

        if ($existing) {
            $pid = (int)$existing["product_id"];
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO product (category_id, product_name)
                 VALUES (:cid, :pname)
                 RETURNING product_id"
            );
            $stmt->execute(["cid" => $categoryId, "pname" => $np]);
            $pid = (int)$stmt->fetchColumn();
        }

        if ($pid > 0) $productIds[] = (string)$pid;
    }

    // уберём дубли
    $productIds = array_values(array_unique(array_map('strval', $productIds)));

    // 3) Добавляем сам расход
    $stmt = $pdo->prepare(
        "INSERT INTO expense (user_id, category_id, expense_datetime, total_amount)
         VALUES (:uid, :cid, :dt, :amt)
         RETURNING expense_id"
    );
    $stmt->execute([
        "uid" => $userId,
        "cid" => $categoryId,
        "dt"  => $expenseDateTime,
        "amt" => $total
    ]);
    $expenseId = (int)$stmt->fetchColumn();

    // 4) Привязываем выбранные товары (если выбраны)
    foreach ($productIds as $pidRaw) {
        $pid = (int)$pidRaw;
        if ($pid <= 0) continue;

        // проверим, что товар принадлежит этой категории (логично)
        $stmt = $pdo->prepare("SELECT product_id FROM product WHERE product_id = :pid AND category_id = :cid");
        $stmt->execute(["pid" => $pid, "cid" => $categoryId]);
        if (!$stmt->fetch()) continue;

        // на случай повторов — не падаем
        $stmt = $pdo->prepare(
            "INSERT INTO expense_product (expense_id, product_id)
             VALUES (:eid, :pid)
             ON CONFLICT DO NOTHING"
        );
        $stmt->execute(["eid" => $expenseId, "pid" => $pid]);
    }

    // 5) Обновляем баланс: баланс = баланс - total
    $stmt = $pdo->prepare('UPDATE "user" SET balance = balance - :amt WHERE user_id = :uid RETURNING balance');
    $stmt->execute(["amt" => $total, "uid" => $userId]);
    $newBalance = $stmt->fetchColumn();

    $pdo->commit();

    echo json_encode([
        "ok" => true,
        "balance" => (string)$newBalance,
        "expense_datetime" => $expenseDateTime,
        "total_amount" => number_format($total, 2, '.', ''),
        "category_id" => $categoryId,
        "category_name" => $categoryName,
        "category_created" => $categoryCreated
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(["ok" => false, "error" => "Ошибка БД"]);
}