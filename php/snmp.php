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

    $snmp_process = shell_exec("ps aux | grep '[s]nmpd'");
    $data->snmp_status = !empty($snmp_process) ? "active" : "inactive";

    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = new stdClass();
    $contents = file_get_contents('php://input');
    $data = json_decode($contents);

    if (isset($data->snmp_action)) {
        $action = $data->snmp_action === "start" ? "start" : "stop";
        exec("systemctl $action snmpd", $output, $retval);

        $snmp_process = shell_exec("ps aux | grep '[s]nmpd'");
        $snmp_status = !empty($snmp_process) ? "active" : "inactive";

        $response->snmp_success = ($retval === 0);
        $response->snmp_status = $snmp_status;
        echo json_encode($response);
        exit;
    }

    if (isset($data->command) && $data->command === "save") {
        $config = load_config();

        $snmp_enabled = isset($data->snmp_enabled) ? (int)$data->snmp_enabled : 0;
        $config['snmp']['enabled'] = $snmp_enabled;

        // Сохраняем конфиг
        if (save_config($config) === false) {
            $response->success = 0;
            $response->error = "Failed to save config.";
            echo json_encode($response);
            exit;
        }

        $action = $snmp_enabled ? "start" : "stop";
        exec("systemctl $action snmpd", $output, $retval);

        $socketData = [
            'type' => 'snmp',
            'event' => 'snmp_settings_updated',
            'snmp_enabled' => $snmp_enabled
        ];
        send_to_socket(json_encode($socketData, JSON_UNESCAPED_UNICODE));

        $response->success = 1;
        echo json_encode($response);
        exit;
    }
}
