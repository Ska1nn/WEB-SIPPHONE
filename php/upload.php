<?php
ini_set('memory_limit', '-1');
ini_set('upload_max_filesize', '512M');
ini_set('post_max_size', '512M');
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

$socketPath = '/tmp/qt_wayland_ipc.socket';

function send_to_socket($message) {
    global $socketPath;
    $message = trim($message) . "\n";

    $socket = @stream_socket_client("unix://$socketPath", $errno, $errstr);
    if (!$socket) return "Socket error: $errstr ($errno)";

    $bytesWritten = fwrite($socket, $message);
    if ($bytesWritten === false) {
        fclose($socket);
        return "Socket write error";
    }

    fflush($socket);
    fclose($socket);
    return true;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Неверный метод запроса.");
    }

    // ---------- Импорт BLF (отдельно, ничего не трогаем для ZIP) ----------
    if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
        $blfContent = file_get_contents($_FILES['import_file']['tmp_name']);
        $uploadDir = '/opt/cumanphone/share/blf/';
        $uploadFile = $uploadDir . 'blf.conf';

        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        if (file_put_contents($uploadFile, $blfContent) === false) {
            throw new Exception("Ошибка при сохранении blf.conf.");
        }

        echo json_encode(['success' => true, 'message' => "BLF импортирован в blf.conf"]);
        exit;
    }

    // ---------- Импорт ZIP ----------
    if (isset($_FILES['import_zip']) && $_FILES['import_zip']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '/tmp/autoprov/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $fileName = basename($_FILES['import_zip']['name']);
        $targetZip = $uploadDir . $fileName;

        // Удаляем все старые ZIP-файлы в папке
        $existingZips = glob($uploadDir . '*.zip');
        foreach ($existingZips as $zip) {
            if (realpath($zip) !== realpath($targetZip)) {
                @unlink($zip);
            }
        }

        if (!move_uploaded_file($_FILES['import_zip']['tmp_name'], $targetZip)) {
            throw new Exception("Не удалось сохранить загруженный ZIP.");
        }

        // Отправка уведомления в сокет
        $result = send_to_socket("IMPORT_ZIP=" . $targetZip);
        if ($result !== true) {
            throw new Exception("Ошибка при отправке в сокет: $result");
        }

        // Дополнительно отправляем сообщение о том, что ZIP загружен
        send_to_socket("ZIP_LOADED=" . $targetZip);

        echo json_encode([
            'success' => true,
            'message' => "ZIP загружен и передан в сокет",
            'path'    => $targetZip
        ]);
        exit;
    }

    throw new Exception("Нет данных для импорта (нужен import_file или import_zip).");

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
