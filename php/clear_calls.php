<?php
ini_set('log_errors', 1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

$callLogDbPath = '/.local/share/CumanPhone/linphone.db';

if (!file_exists($callLogDbPath)) {
    die(json_encode(['error' => 'Файл не найден по пути: ' . $callLogDbPath]));
}

if (!is_writable($callLogDbPath)) {
    die(json_encode(['error' => 'Файл базы данных недоступен для записи: ' . $callLogDbPath]));
}

function send_to_socket($message) {
    $socketPath = '/tmp/qt_wayland_ipc.socket';

    if (!file_exists($socketPath)) {
        error_log("Socket file not found: $socketPath");
        return false;
    }

    $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
    if ($socket === false) {
        error_log("Socket creation failed: " . socket_strerror(socket_last_error()));
        return false;
    }

    if (!socket_connect($socket, $socketPath)) {
        error_log("Socket connection failed: " . socket_strerror(socket_last_error($socket)));
        socket_close($socket);
        return false;
    }

    $message .= "\n";
    socket_write($socket, $message, strlen($message));
    socket_close($socket);
    return true;
}

try {
    $pdo = new PDO("sqlite:$callLogDbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
    $existingTables = $result->fetchAll(PDO::FETCH_COLUMN);

    $tablesToClear = ['conference_call', 'history'];
    $clearedTables = [];

    foreach ($tablesToClear as $table) {
        if (in_array($table, $existingTables)) {
            $pdo->exec("DELETE FROM $table");
            $clearedTables[] = $table;
        }
    }

    $pdo->exec("VACUUM");

    $message = 'Очищены таблицы: ' . implode(', ', $clearedTables);
    $response = [
        'success' => true,
        'message' => $message
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

    $socketPayload = json_encode([
        'type' => 'call_log',
        'event' => 'log_cleared',
        'tables' => $clearedTables
    ]);
    send_to_socket($socketPayload);

} catch (Exception $e) {
    file_put_contents('php_error.log', $e->getMessage());

    http_response_code(500);
    echo json_encode(['error' => 'Ошибка: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>