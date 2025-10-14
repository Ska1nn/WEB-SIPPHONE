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

    $data->vlan_internet_port = $config['net']['vlan_internet_port_enabled'] ?? 0;
    $data->vlan_internet_port_vid = $config['net']['vlan_internet_port_vid_number'] ?? '';
    $data->vlan_pc_port = $config['net']['vlan_pc_port_enabled'] ?? 0;
    $data->vlan_pc_port_vid = $config['net']['vlan_pc_port_vid_number'] ?? '';

    echo json_encode($data);
    exit;
}

elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = new stdClass();   
    $contents = file_get_contents('php://input');
    $data = json_decode($contents);

    if (isset($data->command) && $data->command === "save") {
        $config = load_config();

        // Обновляем значения в конфиге
        $internet_port_enabled = isset($data->vlan_internet_port) ? (int)$data->vlan_internet_port : 0;
        $internet_port_vid = isset($data->vlan_internet_port_vid) ? (string)$data->vlan_internet_port_vid : "";

        $pc_port_enabled = isset($data->vlan_pc_port) ? (int)$data->vlan_pc_port : 0;
        $pc_port_vid = isset($data->vlan_pc_port_vid) ? (string)$data->vlan_pc_port_vid : "";

        $config['net']['vlan_internet_port_enabled'] = $internet_port_enabled;
        $config['net']['vlan_internet_port_vid_number'] = $internet_port_vid;

        $config['net']['vlan_pc_port_enabled'] = $pc_port_enabled;
        $config['net']['vlan_pc_port_vid_number'] = $pc_port_vid;

        if (save_config($config) === false) {
            $response->success = 0;
            echo json_encode($response);
            exit;
        }

        $socketData = [
            'type' => 'vlan',
            'event' => 'vlan_settings_updated',
            'vlan_internet_port' => $internet_port_enabled,
            'vlan_internet_port_vid' => $internet_port_vid,
            'vlan_pc_port' => $pc_port_enabled,
            'vlan_pc_port_vid' => $pc_port_vid
        ];

        send_to_socket(json_encode($socketData, JSON_UNESCAPED_UNICODE));

        $response->success = 1;
        $response->message = "Конфигурация сохранена.";
        $response->data = $socketData;
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }
}
