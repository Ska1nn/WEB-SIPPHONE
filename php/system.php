<?php

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

ini_set('upload_max_filesize', '20M');
ini_set('post_max_size', '20M');
ini_set('max_execution_time', '60');
ini_set('memory_limit', '256M');

header('Content-Type: application/json; charset=utf-8');

function send_to_socket($message)
{
    $socketPath = '/tmp/qt_wayland_ipc.socket';

    if (!file_exists($socketPath)) {
        error_log("Socket file not found");
        return false;
    }

    $socket = @stream_socket_client("unix://$socketPath", $errno, $errstr, 1);

    if (!$socket) {
        error_log("Socket connection error: $errstr ($errno)");
        return false;
    }

    $json = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        fclose($socket);
        return false;
    }

    fwrite($socket, $json . "\n");
    fflush($socket);
    fclose($socket);

    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $data = new stdClass();

    $version = shell_exec("/opt/cumanphone/bin/CumanPhone --version");
    if (!empty($version) && str_contains($version, 'CumanPhone version: ')) {
        $data->version = substr(trim($version), 20);
    }

    $file = "/opt/cumanphone/etc/update.conf";
    if (file_exists($file)) {
        $conf = parse_ini_file($file, true);
        $data->autoupdate = $conf['General']['autoupdate'] ?? 0;
        $data->domain = $conf['General']['domain'] ?? "";
    }

    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['command']) && $_POST['command'] === 'upload_ca') {

        if (!isset($_FILES['file'])) {
            echo json_encode(["success" => 0, "message" => "Файл не получен"]);
            exit;
        }

        $file = $_FILES['file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(["success" => 0, "message" => "Ошибка загрузки файла"]);
            exit;
        }

        $allowed = ['crt', 'pem'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            echo json_encode(["success" => 0, "message" => "Разрешены только .crt и .pem"]);
            exit;
        }

        $certData = file_get_contents($file['tmp_name']);
        if ($certData === false) {
            echo json_encode(["success" => 0, "message" => "Не удалось прочитать файл"]);
            exit;
        }

        $cert = @openssl_x509_read($certData);
        if ($cert === false) {
            echo json_encode(["success" => 0, "message" => "Файл не является валидным X509 сертификатом"]);
            exit;
        }

        openssl_x509_free($cert);

        $targetDir = "/opt/cumanphone/share/linphone/certs/";
        $targetFile = $targetDir . "ca." . $ext;
        $tmpFile = $targetDir . "ca." . $ext . ".tmp";

        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true)) {
                echo json_encode(["success" => 0, "message" => "Не удалось создать директорию"]);
                exit;
            }
        }

        if (file_put_contents($tmpFile, $certData) === false) {
            echo json_encode(["success" => 0, "message" => "Ошибка записи временного файла"]);
            exit;
        }

        chmod($tmpFile, 0644);

        if (!rename($tmpFile, $targetFile)) {
            unlink($tmpFile);
            echo json_encode(["success" => 0, "message" => "Ошибка сохранения сертификата"]);
            exit;
        }

        echo json_encode([
            "success" => 1,
            "message" => "CA-сертификат успешно загружен"
        ]);

        exit;
    }

    $response = new stdClass();

    $contents = file_get_contents('php://input');
    $data = json_decode($contents);

    if (isset($data->command)) {

        switch ($data->command) {

            case "reboot":
                exec("reboot", $output, $retval);
                $response->success = ($retval === 0) ? 1 : 0;
                break;

            case "reset":
                exec('/opt/cumanphone/share/default/reset.sh', $output, $retval);
                $response->success = ($retval === 0) ? 1 : 0;
                break;

            case "restart":
                exec('systemctl restart cumanphone', $output, $retval);
                $response->success = ($retval === 0) ? 1 : 0;
                break;

            case "toggle_usb":
                $state = isset($data->state) ? intval($data->state) : 1;
                $output = shell_exec("/usr/bin/toggle_usb $state 2>&1");
                $response->success = 1;
                $response->output = $output;
                break;

            case "save":
                send_to_socket([
                    "page" => "system",
                    "command" => "set_domain",
                    "value" => $data->domain
                ]);

                send_to_socket([
                    "page" => "system",
                    "command" => "set_autoupdate_enabled",
                    "value" => (int)$data->autoupdate
                ]);

                $response->success = 1;
                break;

            case "get_usb_state":
                $filePath = '/etc/udev/rules.d/99-usb-block.rules';
                $response->state = !file_exists($filePath) ? 1 : 0;
                $response->success = 1;
                break;

            default:
                $response->success = 0;
                $response->message = "Неизвестная команда";
                break;
        }
    } else {
        $response->success = 0;
        $response->message = "Команда не передана";
    }

    echo json_encode($response);
}
?>