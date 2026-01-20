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

// Копилки
$savings = $pdo->prepare("
    SELECT saving_id, goal_name, target_amount, current_amount
    FROM saving
    WHERE user_id = :uid
    ORDER BY goal_name
");
$savings->execute(["uid" => $userId]);
$savings = $savings->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Копилка — Личная бухгалтерия</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<h1>Копилка</h1>

<div class="container">
    <div class="card">
        <p>Баланс: <b id="balance"><?= htmlspecialchars($balance) ?></b></p>
        <p><a href="/dashboard.php">← Назад в кабинет</a></p>
    </div>

    <div class="card" style="margin-top:16px;">
        <h3>Создать копилку</h3>

        <p>
            <label>Название цели:</label><br>
            <input type="text" id="goal_name" placeholder="Например: Ноутбук">
        </p>

        <p>
            <label>Целевая сумма:</label><br>
            <input type="number" id="target_amount" step="0.01" min="0.01" placeholder="Например: 50000.00">
        </p>

        <button id="btnCreate">Создать</button>

        <div id="msgCreate" class="message" style="display:none; margin-top:12px;"></div>
    </div>

    <div class="card" style="margin-top:16px;">
        <h3>Операция по копилке (IN/OUT)</h3>

        <p>
            <label>Копилка:</label><br>
            <select id="saving_id">
                <option value="">— выберите —</option>
                <?php foreach ($savings as $s): ?>
                    <option value="<?= (int)$s["saving_id"] ?>">
                        <?= htmlspecialchars($s["goal_name"]) ?> (<?= htmlspecialchars($s["current_amount"]) ?> / <?= htmlspecialchars($s["target_amount"]) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label>Тип операции:</label><br>
            <select id="operation_type">
                <option value="IN">IN (пополнение)</option>
                <option value="OUT">OUT (снятие)</option>
            </select>
        </p>

        <p class="hint" style="margin-top:8px;">
            <small>Время операции фиксируется автоматически (текущее время сервера).</small>
        </p>

        <p>
            <label>Сумма:</label><br>
            <input type="number" id="operation_amount" step="0.01" min="0.01" placeholder="Например: 1000.00">
        </p>

        <button id="btnOp">Выполнить</button>

        <div id="msgOp" class="message" style="display:none; margin-top:12px;"></div>
    </div>

    <div class="card" style="margin-top:16px;">
        <h3>Мои копилки</h3>
        <ul id="saving_list">
            <?php foreach ($savings as $s): ?>
                <li class="row-item" data-id="<?= (int)$s["saving_id"] ?>">
                    <span class="row-item__text">
                      <b><?= htmlspecialchars($s["goal_name"]) ?></b> —
                      накоплено: <?= htmlspecialchars($s["current_amount"]) ?> /
                      цель: <?= htmlspecialchars($s["target_amount"]) ?>
                    </span>
                    <button class="btn-delete" type="button" title="Удалить" onclick="deleteSaving(<?= (int)$s["saving_id"] ?>)">×</button>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<script>
function show(el, text) {
  el.style.display = "block";
  el.textContent = text;
}

async function deleteSaving(id) {
  const msg = document.getElementById("msgOp");
  msg.style.display = "none";

  if (!confirm("Удалить копилку? Накопленная сумма вернётся на баланс.")) return;

  const fd = new FormData();
  fd.append("saving_id", id);

  const res = await fetch("/api/saving_delete.php", { method: "POST", body: fd });
  const data = await res.json();

  if (!data.ok) {
    show(msg, data.error || "Ошибка");
    return;
  }

  show(msg, "Копилка удалена ✅");
  await refreshSavings();
}

async function refreshSavings() {
  const res = await fetch("/api/saving_list.php");
  const data = await res.json();
  if (!data.ok) return;

  // баланс
  document.getElementById("balance").textContent = data.balance;

  // список копилок
  const ul = document.getElementById("saving_list");
  ul.innerHTML = "";
  data.savings.forEach(s => {
    const li = document.createElement("li");
    li.className = "row-item";
    li.dataset.id = s.saving_id;
    li.innerHTML = `
      <span class="row-item__text"><b>${s.goal_name}</b> — накоплено: ${s.current_amount} / цель: ${s.target_amount}</span>
      <button class="btn-delete" type="button" title="Удалить">×</button>
    `;
    li.querySelector("button").addEventListener("click", () => deleteSaving(s.saving_id));
    ul.appendChild(li);
  });

  // select копилок
  const sel = document.getElementById("saving_id");
  const current = sel.value;
  sel.innerHTML = `<option value="">— выберите —</option>`;
  data.savings.forEach(s => {
    const opt = document.createElement("option");
    opt.value = s.saving_id;
    opt.textContent = `${s.goal_name} (${s.current_amount} / ${s.target_amount})`;
    sel.appendChild(opt);
  });
  // если была выбранная копилка — попробуем вернуть
  sel.value = current;
}

document.getElementById("btnCreate").addEventListener("click", async () => {
  const msg = document.getElementById("msgCreate");
  msg.style.display = "none";

  const goal_name = document.getElementById("goal_name").value;
  const target_amount = document.getElementById("target_amount").value;

  const fd = new FormData();
  fd.append("goal_name", goal_name);
  fd.append("target_amount", target_amount);

  const res = await fetch("/api/saving_create.php", { method: "POST", body: fd });
  const data = await res.json();

  if (!data.ok) {
    show(msg, data.error || "Ошибка");
    return;
  }

  document.getElementById("goal_name").value = "";
  document.getElementById("target_amount").value = "";
  show(msg, "Копилка создана ✅");

  await refreshSavings();
});

document.getElementById("btnOp").addEventListener("click", async () => {
  const msg = document.getElementById("msgOp");
  msg.style.display = "none";

  const saving_id = document.getElementById("saving_id").value;
  const operation_type = document.getElementById("operation_type").value;
  const amount = document.getElementById("operation_amount").value;

  const fd = new FormData();
  fd.append("saving_id", saving_id);
  fd.append("operation_type", operation_type);
  fd.append("amount", amount);

  const res = await fetch("/api/saving_operation.php", { method: "POST", body: fd });
  const data = await res.json();

  if (!data.ok) {
    show(msg, data.error || "Ошибка");
    return;
  }

  document.getElementById("operation_amount").value = "";
  show(msg, "Операция выполнена ✅");

  await refreshSavings();
});
</script>
</body>
</html>
