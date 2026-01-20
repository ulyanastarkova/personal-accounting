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

// Категории расходов
$cats = $pdo->prepare("SELECT category_id, category_name FROM expense_category WHERE user_id = :uid ORDER BY category_name");
$cats->execute(["uid" => $userId]);
$cats = $cats->fetchAll();

// Последние расходы
$expenses = $pdo->prepare("
    SELECT e.expense_id,
           to_char(e.expense_datetime, 'YYYY-MM-DD HH24:MI') AS expense_datetime,
           e.total_amount,
           c.category_name
    FROM expense e
    JOIN expense_category c ON c.category_id = e.category_id
    WHERE e.user_id = :uid
    ORDER BY e.expense_datetime DESC, e.expense_id DESC
    LIMIT 20
");
$expenses->execute(["uid" => $userId]);
$expenses = $expenses->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Расходы — Личная бухгалтерия</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<h1>Расходы</h1>

<div class="container">
    <div class="card">
        <p>Баланс: <b id="balance"><?= htmlspecialchars($balance) ?></b></p>
        <p><a href="/dashboard.php">← Назад в кабинет</a></p>
    </div>

    <div class="card" style="margin-top:16px;">
        <h3>Добавить расход</h3>

        <p>
            <label>Дата и время:</label><br>
            <input type="datetime-local" id="expense_datetime" value="<?= date('Y-m-d\\TH:i') ?>">
        </p>

        <p>
            <label>Сумма:</label><br>
            <input type="number" id="total_amount" step="0.01" min="0.01" placeholder="Например: 450.00">
        </p>

        <p>
            <label>Категория:</label><br>
            <select id="category_id">
                <option value="">— выберите —</option>
                <?php foreach ($cats as $c): ?>
                    <option value="<?= (int)$c["category_id"] ?>"><?= htmlspecialchars($c["category_name"]) ?></option>
                <?php endforeach; ?>
            </select>
        </p>

        <p style="margin-top:8px;">
            <label>Новая категория (если нет в списке):</label><br>
            <input type="text" id="new_category" placeholder="Например: Продукты">
        </p>

        <hr>

        <h4>Товары (необязательно)</h4>

        <p>
            <label>Выберите товары из справочника (можно несколько):</label><br>
            <div id="product_box" class="product-box"></div>
            <small class="muted">Список товаров зависит от выбранной категории.</small>
        </p>

        <p style="margin-top:8px;">
            <label>Новые товары (каждый с новой строки):</label><br>
            <textarea id="new_products" rows="3" style="width:100%;" placeholder="Например:
Хлеб
Молоко
Яйца"></textarea>
        </p>

        <button id="btnAddExpense">Добавить расход</button>

        <div id="msg" class="message" style="display:none; margin-top:12px;"></div>
    </div>

    <div class="card" style="margin-top:16px;">
        <h3>Последние расходы</h3>
        <ul id="expense_list">
            <?php foreach ($expenses as $e): ?>
                <li class="row-item" data-id="<?= (int)$e["expense_id"] ?>">
                    <span class="row-item__text">
                        <?= htmlspecialchars($e["expense_datetime"]) ?> —
                        <?= htmlspecialchars($e["category_name"]) ?> —
                        <b><?= htmlspecialchars($e["total_amount"]) ?></b>
                    </span>
                    <button class="btn-delete" type="button" title="Удалить" onclick="deleteExpense(<?= (int)$e["expense_id"] ?>)">×</button>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<script>
const msg = document.getElementById("msg");
function showMessage(text) {
  msg.style.display = "block";
  msg.textContent = text;
}

async function deleteExpense(id) {
  if (!confirm("Удалить расход?")) return;

  const fd = new FormData();
  fd.append("expense_id", id);

  try {
    const res = await fetch("/api/expense_delete.php", { method: "POST", body: fd });
    const data = await res.json();

    if (!data.ok) {
      showMessage(data.error || "Ошибка");
      return;
    }

    document.getElementById("balance").textContent = data.balance;

    const li = document.querySelector(`#expense_list li[data-id='${id}']`);
    if (li) li.remove();

    showMessage("Расход удалён ✅");
  } catch (e) {
    showMessage("Ошибка сети/сервера");
  }
}

async function loadProducts(categoryId) {
  const box = document.getElementById("product_box");
  box.innerHTML = "";

  if (!categoryId) return;

  const res = await fetch("/api/product_list.php?category_id=" + encodeURIComponent(categoryId));
  const data = await res.json();

  if (!data.ok) {
    showMessage(data.error || "Ошибка загрузки товаров");
    return;
  }

  if (!data.products.length) {
    const empty = document.createElement("div");
    empty.className = "muted";
    empty.textContent = "В этой категории пока нет товаров.";
    box.appendChild(empty);
    return;
  }

  data.products.forEach(p => {
    const row = document.createElement("label");
    row.className = "product-item";

    const cb = document.createElement("input");
    cb.type = "checkbox";
    cb.name = "product_ids[]";
    cb.value = p.product_id;

    const text = document.createElement("span");
    text.textContent = p.product_name;

    const del = document.createElement("button");
    del.type = "button";
    del.className = "btn-delete";
    del.title = "Удалить товар";
    del.textContent = "×";
    del.addEventListener("click", async (ev) => {
      ev.preventDefault();
      ev.stopPropagation();
      if (!confirm("Удалить товар?")) return;
      const fd = new FormData();
      fd.append("product_id", p.product_id);
      const r = await fetch("/api/product_delete.php", { method: "POST", body: fd });
      const d = await r.json();
      if (!d.ok) {
        showMessage(d.error || "Ошибка");
        return;
      }
      showMessage("Товар удалён ✅");
      await loadProducts(categoryId);
    });

    row.appendChild(cb);
    row.appendChild(text);
    row.appendChild(del);
    box.appendChild(row);
  });
}

document.getElementById("category_id").addEventListener("change", (e) => {
  msg.style.display = "none";
  loadProducts(e.target.value);
});

document.getElementById("btnAddExpense").addEventListener("click", async () => {
  msg.style.display = "none";

  const expense_datetime = document.getElementById("expense_datetime").value;
  const total_amount = document.getElementById("total_amount").value;
  const category_id = document.getElementById("category_id").value;
  const new_category = document.getElementById("new_category").value;

  const selected = Array.from(document.querySelectorAll('input[name="product_ids[]"]:checked')).map(cb => cb.value);
  const newProductsRaw = document.getElementById("new_products").value;

  const formData = new FormData();
  formData.append("expense_datetime", expense_datetime);
  formData.append("total_amount", total_amount);
  formData.append("category_id", category_id);
  formData.append("new_category", new_category);

  // выбранные товары
  selected.forEach(id => formData.append("product_ids[]", id));

  // новые товары (массив)
  const newProducts = newProductsRaw
      .split(/\r?\n/)
      .map(s => s.trim())
      .filter(s => s.length > 0);
  newProducts.forEach(name => formData.append("new_products[]", name));

  try {
    const res = await fetch("/api/expense_add.php", { method: "POST", body: formData });
    const data = await res.json();

    if (!data.ok) {
      showMessage(data.error || "Ошибка");
      return;
    }

    document.getElementById("balance").textContent = data.balance;

    const li = document.createElement("li");
    li.className = "row-item";
    li.dataset.id = data.expense_id;
    li.innerHTML = `
      <span class="row-item__text">${data.expense_datetime} — ${data.category_name} — <b>${data.total_amount}</b></span>
      <button class="btn-delete" type="button" title="Удалить">×</button>
    `;
    li.querySelector("button").addEventListener("click", () => deleteExpense(data.expense_id));
    document.getElementById("expense_list").prepend(li);

    // очистка
    document.getElementById("total_amount").value = "";
    document.getElementById("new_products").value = "";
    document.getElementById("new_category").value = "";

    // если создали категорию — добавим её в select
    if (data.category_created) {
      const sel = document.getElementById("category_id");
      const opt = document.createElement("option");
      opt.value = data.category_id;
      opt.textContent = data.category_name;
      sel.appendChild(opt);
      sel.value = data.category_id;
    }

    // перезагрузим товары для текущей категории (чтобы увидеть новый товар)
    await loadProducts(document.getElementById("category_id").value);

    showMessage("Расход добавлен ✅");
  } catch (e) {
    showMessage("Ошибка сети/сервера");
  }
});
</script>
</body>
</html>
