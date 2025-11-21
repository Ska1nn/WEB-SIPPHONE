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

    $data->ethernet_mode = $config['net']['ethernet_mode'] ?? 0;
    $data->ethernet_enabled = $config['net']['ethernet_enabled'] ?? 0;

    if ($data->ethernet_mode == 1) {
        $data->ip_address = $config['net']['ip_address'] ?? '';
        $data->netmask    = $config['net']['netmask'] ?? '';
        $data->gateway    = $config['net']['gateway'] ?? '';
        $data->dns1       = $config['net']['dns_first'] ?? '';
        $data->dns2       = $config['net']['dns_second'] ?? '';
        $data->mtu        = $config['net']['ipv4_mtu'] ?? '';
    }

    $audio_dscp_raw = $config['rtp']['audio_dscp'] ?? '';
    $video_dscp_raw = $config['rtp']['video_dscp'] ?? '';
    $sip_dscp_raw   = $config['sip']['dscp'] ?? '';

    $data->audio_dscp = ($audio_dscp_raw === '0x2e') ? '1' : '0';
    $data->video_dscp = ($video_dscp_raw === '0x22') ? '1' : '0';
    $data->sip_dscp   = ($sip_dscp_raw === '0x1a') ? '1' : '0';

    $data->mtu_status = $config['net']['path_mtu_enabled'] ?? '';

    echo json_encode($data);
    exit;
}

elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = new stdClass();
    $contents = file_get_contents('php://input');
    $data = json_decode($contents);

    if (isset($data->command) && $data->command === "save") {
        $message = [
            'type' => 'network',
            'event' => 'network_settings_updated',
            'ethernet_mode' => (int) ($data->ethernet_mode ?? 0),
            'ethernet_enabled' => (int) ($data->ethernet_enabled ?? 0),
            'ip_address' => $data->ip_address ?? '',
            'netmask' => $data->netmask ?? '',
            'gateway' => $data->gateway ?? '',
            'dns1' => $data->dns1 ?? '',
            'dns2' => $data->dns2 ?? '',
            'mtu' => $data->mtu ?? '',
            'mtu_status' => $data->mtu_status ?? '',
            'audio_dscp' => $data->audio_dscp ?? '0',
            'video_dscp' => $data->video_dscp ?? '0',
            'sip_dscp' => $data->sip_dscp ?? '0'
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