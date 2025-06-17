<?php
ini_set('log_errors', 1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

$callLogDbPath = '/.local/share/CumanPhone/linphone.db';

// Проверка существования файла
if (!file_exists($callLogDbPath)) {
    die(json_encode(['error' => 'Файл не найден по пути: ' . $callLogDbPath]));
}

// Проверка прав на запись
if (!is_writable($callLogDbPath)) {
    die(json_encode(['error' => 'Файл базы данных недоступен для записи: ' . $callLogDbPath]));
}

try {
    $pdo = new PDO("sqlite:$callLogDbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Получаем список таблиц
    $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
    $existingTables = $result->fetchAll(PDO::FETCH_COLUMN);

    // Таблицы, которые нужно очистить
    $tablesToClear = ['conference_call', 'history'];
    $clearedTables = [];

    foreach ($tablesToClear as $table) {
        if (in_array($table, $existingTables)) {
            $pdo->exec("DELETE FROM $table"); // Удаление всех записей
            $clearedTables[] = $table;
        }
    }

    // Оптимизация базы после удаления
    $pdo->exec("VACUUM");

    echo json_encode([
        'success' => true,
        'message' => 'Очищены таблицы: ' . implode(', ', $clearedTables)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    file_put_contents('php_error.log', $e->getMessage());

    http_response_code(500);
    echo json_encode(['error' => 'Ошибка: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>