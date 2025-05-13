<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

$callLogDbPath = '/.local/share/CumanPhone/linphone.db';

try {
    if (!file_exists($callLogDbPath)) {
        throw new Exception("Файл базы данных не найден.");
    }

    $pdo = new PDO("sqlite:$callLogDbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tablesToClear = ['conference_call', 'history'];

    foreach ($tablesToClear as $table) {
        $pdo->exec("DELETE FROM $table");
        $pdo->exec("VACUUM");
    }

    echo json_encode(['success' => true, 'message' => 'История звонков успешно очищена.'], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>
