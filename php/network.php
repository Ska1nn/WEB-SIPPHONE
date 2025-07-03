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

    $data->ethernet_mode = $config['net']['ethernet_mode'] ?? 0;
    $data->ethernet_enabled = $config['net']['ethernet_enabled'] ?? 0;

    if ($data->ethernet_enabled) {
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
        $config = load_config();

        $config['net']['ethernet_mode'] = $data->ethernet_mode ?? 0;
        $config['net']['ethernet_enabled'] = $data->ethernet_enabled ?? 0;
        $config['net']['path_mtu_enabled'] = $data->mtu_status ?? '';

        $config['net']['ip_address'] = $data->ip_address ?? '';
        $config['net']['netmask']    = $data->netmask ?? '';
        $config['net']['gateway']    = $data->gateway ?? '';
        $config['net']['dns_first']  = $data->dns1 ?? '';
        $config['net']['dns_second'] = $data->dns2 ?? '';
        $config['net']['ipv4_mtu']   = $data->mtu ?? '';

        // DSCP значения
        if (isset($data->audio_dscp)) {
            $value = ($data->audio_dscp === "1") ? "0x2e" : "0x0";
            shell_exec("sed -i 's/^audio_dscp=.*/audio_dscp=$value/' /opt/cumanphone/etc/config.conf");
            $config['rtp']['audio_dscp'] = $value;
        }
        if (isset($data->video_dscp)) {
            $value = ($data->video_dscp === "1") ? "0x22" : "0x0";
            shell_exec("sed -i 's/^video_dscp=.*/video_dscp=$value/' /opt/cumanphone/etc/config.conf");
            $config['rtp']['video_dscp'] = $value;
        }
        if (isset($data->sip_dscp)) {
            $value = ($data->sip_dscp === "1") ? "0x1a" : "0x0";
            shell_exec("sed -i 's/^dscp=.*/dscp=$value/' /opt/cumanphone/etc/config.conf");
            $config['sip']['dscp'] = $value;
        }

        // Сохраняем конфиг
        if (save_config($config) === false) {
            $response->success = 0;
            $response->error = "Failed to save config.";
            echo json_encode($response);
            exit;
        }

        // Подготовка и отправка JSON в сокет
        $socketData = [
            'type' => 'network',
            'event' => 'network_settings_updated',
            'ethernet_mode' => (int) $data->ethernet_mode,
            'ethernet_enabled' => (int) $data->ethernet_enabled,
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

        send_to_socket(json_encode($socketData, JSON_UNESCAPED_UNICODE));

        $response->success = 1;
        echo json_encode($response);
        exit;
    }
}