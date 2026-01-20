<?php

// --- DB config ---
// Локально можно оставить переменные окружения пустыми — тогда используются значения ниже.
// На хостинге (Render/Railway и т.п.) удобнее всего задать DATABASE_URL.

$DATABASE_URL = getenv('DATABASE_URL');

// Фолбэк для локального запуска
$DB_HOST = getenv('DB_HOST') ?: "127.0.0.1";
$DB_PORT = getenv('DB_PORT') ?: "5432";
$DB_NAME = getenv('DB_NAME') ?: "personal_accounting";
$DB_USER = getenv('DB_USER') ?: "postgres";
$DB_PASS = getenv('DB_PASS') ?: "1234";

if ($DATABASE_URL) {
    // Ожидаем формат: postgres://USER:PASSWORD@HOST:PORT/DBNAME
    $parts = parse_url($DATABASE_URL);
    if ($parts !== false) {
        $DB_HOST = $parts['host'] ?? $DB_HOST;
        $DB_PORT = (string)($parts['port'] ?? $DB_PORT);
        $DB_NAME = ltrim($parts['path'] ?? ("/".$DB_NAME), '/');
        $DB_USER = $parts['user'] ?? $DB_USER;
        $DB_PASS = $parts['pass'] ?? $DB_PASS;
    }
}

$dsn = "pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME";

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("DB connection error: " . $e->getMessage());
}
