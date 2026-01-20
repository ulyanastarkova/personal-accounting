<?php
require __DIR__ . "/../app/db.php";
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: /auth/login.php");
    exit;
}
$userId = (int)$_SESSION["user_id"];

// категории пользователя
$stmt = $pdo->prepare("SELECT category_id, category_name FROM expense_category WHERE user_id = :uid ORDER BY category_name");
$stmt->execute(["uid" => $userId]);
$cats = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Лимиты — Личная бухгалтерия</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<h1>Лимиты</h1>

<div class="container">
    <div class="card">
        <p><a href="/dashboard.php">← Назад в кабинет</a></p>

        <p>
            <label>Категория:</label><br>
            <select id="category_id">
                <option value="">— выберите —</option>
                <?php foreach ($cats as $c): ?>
                    <option value="<?= (int)$c["category_id"] ?>"><?= htmlspecialchars($c["category_name"]) ?></option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label>Год:</label><br>
            <input type="number" id="year" value="<?= date('Y') ?>" min="2025" step="1">
        </p>

        <p>
            <label>Месяц:</label><br>
            <input type="number" id="month" value="<?= date('n') ?>" min="1" max="12" step="1">
        </p>

        <p>
            <label>Сумма лимита:</label><br>
            <input type="number" id="limit_amount" step="0.01" min="0" placeholder="Например: 10000.00">
            <small style="display:block; margin-top:6px; opacity:.8;">
                Если поле оставить пустым, лимит для выбранного месяца будет снят.
            </small>
        </p>

        <button type="button" id="btnSave">Сохранить / изменить лимит</button>

        <div id="info" class="message" style="display:none; margin-top:12px;"></div>
    </div>
</div>

<script>
const info = document.getElementById("info");
function showInfo(text) {
  info.style.display = "block";
  info.textContent = text;
}

async function loadLimit() {
  info.style.display = "none";

  const category_id = document.getElementById("category_id").value;
  const year = document.getElementById("year").value;
  const month = document.getElementById("month").value;

  if (!category_id) return;

  const res = await fetch(`/api/category_limit_get.php?category_id=${encodeURIComponent(category_id)}&year=${encodeURIComponent(year)}&month=${encodeURIComponent(month)}`);
  const data = await res.json();

  if (!data.ok) {
    showInfo(data.error || "Ошибка получения лимита");
    return;
  }

  document.getElementById("limit_amount").value = data.limit_amount;

  const limitText = data.limit_is_set ? data.limit_amount : "не задан";
  const remainingText = data.limit_is_set ? data.remaining : "—";
  let text = `Лимит: ${limitText} | Потрачено: ${data.spent_amount} | Осталось: ${remainingText}`;
  if (data.is_over) text += " ⚠ Превышен лимит!";
  showInfo(text);
}

document.getElementById("category_id").addEventListener("change", loadLimit);
document.getElementById("year").addEventListener("change", loadLimit);
document.getElementById("month").addEventListener("change", loadLimit);

document.getElementById("btnSave").addEventListener("click", async () => {
  info.style.display = "none";

  const category_id = document.getElementById("category_id").value;
  const year = document.getElementById("year").value;
  const month = document.getElementById("month").value;
  const limit_amount = document.getElementById("limit_amount").value;

  if (!category_id) {
    showInfo("Выберите категорию");
    return;
  }

  const fd = new FormData();
  fd.append("category_id", category_id);
  fd.append("year", year);
  fd.append("month", month);
  fd.append("limit_amount", limit_amount);

  const res = await fetch("/api/category_limit_save.php", { method: "POST", body: fd });
  const data = await res.json();

  if (!data.ok) {
    showInfo(data.error || "Ошибка сохранения");
    return;
  }

  if (data.action === "removed") {
    showInfo("Лимит снят ✅");
  } else {
    showInfo("Лимит сохранён ✅");
  }
  await loadLimit();
});
</script>
</body>
</html>
