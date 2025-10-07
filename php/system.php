<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

ini_set('upload_max_filesize', '800M');
ini_set('post_max_size', '800M');
ini_set('max_execution_time', '600');
ini_set('memory_limit', '1024M');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = new stdClass();
    $version = shell_exec("/opt/cumanphone/bin/CumanPhone --version");
    if (!empty($version) && str_contains($version, 'CumanPhone version: '))
        $data->version = substr(trim($version), 20);

    echo json_encode($data);
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
