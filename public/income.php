<?php
require __DIR__ . "/../app/db.php";
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: /auth/login.php");
    exit;
}

$userId = (int)$_SESSION["user_id"];

// Баланс
$stmt = $pdo->prepare('SELECT balance FROM "user" WHERE user_id = :id');
$stmt->execute(["id" => $userId]);
$balance = $stmt->fetchColumn();

// Источники дохода (для выпадающего списка)
$sources = $pdo->prepare("SELECT source_id, source_name FROM income_source WHERE user_id = :uid ORDER BY source_name");
$sources->execute(["uid" => $userId]);
$sources = $sources->fetchAll();

// Последние доходы
$incomes = $pdo->prepare("
    SELECT i.income_id,
           to_char(i.income_datetime, 'YYYY-MM-DD HH24:MI') AS income_datetime,
           i.amount,
           s.source_name
    FROM income i
    JOIN income_source s ON s.source_id = i.source_id
    WHERE i.user_id = :uid
    ORDER BY i.income_datetime DESC, i.income_id DESC
    LIMIT 20
");
$incomes->execute(["uid" => $userId]);
$incomes = $incomes->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Доходы — Личная бухгалтерия</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<h1>Доходы</h1>

<div class="container">
    <div class="card">
        <p>Баланс: <b id="balance"><?= htmlspecialchars($balance) ?></b></p>
        <p><a href="/dashboard.php">← Назад в кабинет</a></p>
    </div>

    <div class="card" style="margin-top:16px;">
        <h3>Добавить доход</h3>

        <p>
            <label>Дата и время:</label><br>
            <input type="datetime-local" id="income_datetime" value="<?= date('Y-m-d\\TH:i') ?>">
        </p>

        <p>
            <label>Сумма:</label><br>
            <input type="number" id="amount" step="0.01" min="0.01" placeholder="Например: 1500.00">
        </p>

        <p>
            <label>Источник:</label><br>
            <select id="source_id">
                <option value="">— выберите —</option>
                <?php foreach ($sources as $s): ?>
                    <option value="<?= (int)$s["source_id"] ?>"><?= htmlspecialchars($s["source_name"]) ?></option>
                <?php endforeach; ?>
            </select>
        </p>

        <p style="margin-top:8px;">
            <label>Новый источник (если нет в списке):</label><br>
            <input type="text" id="new_source" placeholder="Например: Стипендия">
        </p>

        <button id="btnAdd">Добавить</button>

        <div id="msg" class="message" style="display:none; margin-top:12px;"></div>
    </div>

    <div class="card" style="margin-top:16px;">
        <h3>Последние доходы</h3>
        <ul id="income_list">
            <?php foreach ($incomes as $inc): ?>
                <li class="row-item" data-id="<?= (int)$inc["income_id"] ?>">
                    <span class="row-item__text">
                        <?= htmlspecialchars($inc["income_datetime"]) ?> —
                        <?= htmlspecialchars($inc["source_name"]) ?> —
                        <b><?= htmlspecialchars($inc["amount"]) ?></b>
                    </span>
                    <button class="btn-delete" type="button" title="Удалить" onclick="deleteIncome(<?= (int)$inc["income_id"] ?>)">×</button>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<script>
const btn = document.getElementById("btnAdd");
const msg = document.getElementById("msg");

function showMessage(text) {
  msg.style.display = "block";
  msg.textContent = text;
}

async function deleteIncome(id) {
  if (!confirm("Удалить доход?")) return;

  const fd = new FormData();
  fd.append("income_id", id);

  try {
    const res = await fetch("/api/income_delete.php", { method: "POST", body: fd });
    const data = await res.json();

    if (!data.ok) {
      showMessage(data.error || "Ошибка");
      return;
    }

    document.getElementById("balance").textContent = data.balance;

    const li = document.querySelector(`#income_list li[data-id='${id}']`);
    if (li) li.remove();

    showMessage("Доход удалён ✅");
  } catch (e) {
    showMessage("Ошибка сети/сервера");
  }
}

btn.addEventListener("click", async () => {
  msg.style.display = "none";

  const income_datetime = document.getElementById("income_datetime").value;
  const amount = document.getElementById("amount").value;
  const source_id = document.getElementById("source_id").value;
  const new_source = document.getElementById("new_source").value;

  const formData = new FormData();
  formData.append("income_datetime", income_datetime);
  formData.append("amount", amount);
  formData.append("source_id", source_id);
  formData.append("new_source", new_source);

  try {
    const res = await fetch("/api/income_add.php", {
      method: "POST",
      body: formData
    });

    const data = await res.json();

    if (!data.ok) {
      showMessage(data.error || "Ошибка");
      return;
    }

    // Обновляем баланс
    document.getElementById("balance").textContent = data.balance;

    // Добавляем строку в список
    const li = document.createElement("li");
    li.className = "row-item";
    li.dataset.id = data.income_id;
    li.innerHTML = `
      <span class="row-item__text">${data.income_datetime} — ${data.source_name} — <b>${data.amount}</b></span>
      <button class="btn-delete" type="button" title="Удалить">×</button>
    `;
    li.querySelector("button").addEventListener("click", () => deleteIncome(data.income_id));
    document.getElementById("income_list").prepend(li);

    // Очистим сумму (чтобы удобнее добавлять следующий)
    document.getElementById("amount").value = "";
    document.getElementById("new_source").value = "";
    document.getElementById("source_id").value = "";

    showMessage("Доход добавлен ✅");
  } catch (e) {
    showMessage("Ошибка сети/сервера");
  }
});
</script>
</body>
</html>
