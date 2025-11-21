<?php

require __DIR__ . '/config.php';

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

        $internet_port_enabled = isset($data->vlan_internet_port) ? (int)$data->vlan_internet_port : 0;
        $internet_port_vid     = isset($data->vlan_internet_port_vid) ? (string)$data->vlan_internet_port_vid : "";
        $pc_port_enabled       = isset($data->vlan_pc_port) ? (int)$data->vlan_pc_port : 0;
        $pc_port_vid           = isset($data->vlan_pc_port_vid) ? (string)$data->vlan_pc_port_vid : "";

        $message = [
            'type' => 'vlan',
            'event' => 'vlan_settings_updated',
            'vlan_internet_port' => $internet_port_enabled,
            'vlan_internet_port_vid' => $internet_port_vid,
            'vlan_pc_port' => $pc_port_enabled,
            'vlan_pc_port_vid' => $pc_port_vid
        ];

        if (send_to_socket($message)) {
            $response->success = 1;
            $response->message = "Конфигурация отправлена через сокет.";
        } else {
            $response->success = 0;
            $response->message = "Не удалось отправить конфигурацию через сокет.";
        }

        $response->data = $message;

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
