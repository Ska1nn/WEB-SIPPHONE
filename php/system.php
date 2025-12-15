<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

ini_set('upload_max_filesize', '800M');
ini_set('post_max_size', '800M');
ini_set('max_execution_time', '600');
ini_set('memory_limit', '1024M');

function send_to_socket($message) {
    $socketPath = '/tmp/qt_wayland_ipc.socket';

    if (!file_exists($socketPath)) {
        error_log("Socket file not found: $socketPath");
        return false;
    }

    $socket = @stream_socket_client("unix://$socketPath", $errno, $errstr, 1);
    if (!$socket) {
        error_log("Socket connection error: $errstr ($errno)");
        return false;
    }

    $json = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        error_log("JSON encoding failed: " . json_last_error_msg());
        fclose($socket);
        return false;
    }

    $payload = $json . "\n";

    $bytesWritten = fwrite($socket, $payload);
    fflush($socket);
    fclose($socket);

    if ($bytesWritten === false || $bytesWritten !== strlen($payload)) {
        error_log("Failed to write full message to socket");
        return false;
    }

    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = new stdClass();

    // Версия
    $version = shell_exec("/opt/cumanphone/bin/CumanPhone --version");
    if (!empty($version) && str_contains($version, 'CumanPhone version: '))
        $data->version = substr(trim($version), 20);

    // Читаем update.conf как INI
    $file = "/opt/cumanphone/etc/update.conf";
    if (file_exists($file)) {
        $conf = parse_ini_file($file, true);

        $data->autoupdate = $conf['General']['autoupdate'] ?? 0;
        $data->domain = $conf['General']['domain'];
        $data->login = $conf['General']['username'];
        $data->pass = $conf['General']['password'];
    }

    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = new stdClass();

    $contents = file_get_contents('php://input');
    $data = json_decode($contents);

    // Команды без файла
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

            case "download-syslog":
                header("Cache-Control: public");
                header("Content-Description: File Transfer");
                header("Content-Disposition: attachment; filename=syslog");
                header("Content-Transfer-Encoding: binary");
                header("Content-Type: binary/octet-stream");
                readfile("/var/log/syslog");
                exit;

            case "download-log":
                header("Cache-Control: public");
                header("Content-Description: File Transfer");
                header("Content-Disposition: attachment; filename=cumanphone1.log");
                header("Content-Transfer-Encoding: binary");
                header("Content-Type: binary/octet-stream");
                readfile("/opt/cumanphone/var/log/cumanphone1.log");
                exit;

            case "toggle_usb":
                $state = isset($data->state) ? intval($data->state) : 1;
                $output = shell_exec("/usr/bin/toggle_usb $state 2>&1");
                $response->output = $output;
                $response->success = 1;
                break;
            
            case "save";
                send_to_socket([
                    "page" => "system",
                    "command" => "set_domain",
                    "value" => $data->domain
                ]);
                send_to_socket([
                    "page" => "system",
                    "command" => "set_username",
                    "value" => $data->login
                ]);
                send_to_socket([
                    "page" => "system",
                    "command" => "set_password",
                    "value" => $data->pass
                ]);
                send_to_socket([
                    "page" => "system",
                    "command" => "set_autoupdate_enabled",
                    "value" => (int)$data->autoupdate
                ]);
                $response->success = 1;
                $response->message = "Настройки отправлены в сокет";
                break;
        }
    }

    if (
        (isset($_POST['command']) && $_POST['command'] === 'update') ||
        (isset($_REQUEST['command']) && $_REQUEST['command'] === 'update') ||
        (isset($data->command) && $data->command === 'update')
    ) {

        if (!isset($_FILES['file'])) {
            echo json_encode(["success" => 0, "message" => "Файл не получен"]);
            exit;
        }

        $file = $_FILES['file'];

        $target = "/tmp/" . basename($file['name']);
        if (move_uploaded_file($file['tmp_name'], $target)) {
            shell_exec("sh $target > /dev/null 2>&1 &");
            echo json_encode(["success" => 1, "message" => "Файл успешно загружен и обновление запущено"]);
        } else {
            echo json_encode(["success" => 0, "message" => "Ошибка сохранения файла"]);
        }

        exit;
    }

    // get_usb_state
    if (isset($data->command) && $data->command == 'get_usb_state') {
        $filePath = '/etc/udev/rules.d/99-usb-block.rules';
        $response->state = !file_exists($filePath) ? 1 : 0;
        $response->success = 1;
    }

    // Ответ клиенту
    echo json_encode($response);
}
?>
