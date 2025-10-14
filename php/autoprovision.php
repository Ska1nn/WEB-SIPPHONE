<?php

require __DIR__ . '/config.php';

function send_to_socket($message) {
    $socketPath = '/tmp/qt_wayland_ipc.socket';
    $message = trim($message) . "\n";

    file_put_contents('/tmp/socket_debug.log', date('[Y-m-d H:i:s] ') . "Sending: $message\n", FILE_APPEND);

    $socket = stream_socket_client("unix://$socketPath", $errno, $errstr);

    if (!$socket) {
        $error = "Socket connection error: $errstr ($errno)";
        file_put_contents('/tmp/socket_debug.log', $error . "\n", FILE_APPEND);
        error_log($error);
        return false;
    }

    $bytesWritten = fwrite($socket, $message);

    if ($bytesWritten === false) {
        $error = "Error: Unable to write to socket.";
        file_put_contents('/tmp/socket_debug.log', $error . "\n", FILE_APPEND);
        error_log($error);
        fclose($socket);
        return false;
    }

    fflush($socket);
    fclose($socket);

    file_put_contents('/tmp/socket_debug.log', "Message sent successfully\n", FILE_APPEND);
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = new stdClass();
    $config = load_config(); 

    $data->autoprovision_enabled = $config['ui']['autoprovision_enabled'] ?? 0;
    $data->autoprovision_ip_address = $config['ui']['autoprovision_ip_address'] ?? '';

    echo json_encode($data);
    exit;
}

elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = new stdClass();   
    $contents = file_get_contents('php://input');
    $data = json_decode($contents);

    if (isset($data->command) && $data->command === "save") {
        $config = load_config();

        $autoprovision_enabled = isset($data->autoprovision_enabled) && $data->autoprovision_enabled == 1 ? 1 : 0;
        $autoprovision_ip_address = $data->autoprovision_ip_address ?? '';

        $config['ui']['autoprovision_enabled'] = $autoprovision_enabled;
        $config['ui']['autoprovision_ip_address'] = $autoprovision_ip_address;

        send_to_socket("SET_AUTOPROVISION=" . $autoprovision_enabled);
        send_to_socket("SET_AUTOPROVISION_IP=" . $autoprovision_ip_address);

        if (save_config($config) === false) {
            $response->success = 0;
            echo json_encode($response);
            exit;
        }

        $socketData = [
            'type' => 'autoprovision',
            'autoprovision_enabled' => $autoprovision_enabled,
            'autoprovision_ip_address' => $autoprovision_ip_address
        ];

        send_to_socket(json_encode($socketData, JSON_UNESCAPED_UNICODE));

        $response->success = 1;
        $response->message = "Конфигурация сохранена.";
        $response->data = $socketData;
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }
}