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
    $response = new stdClass();
    $config = load_config();
    if ( isset($config['ui']['language']) )
        $response->language = $config['ui']['language'];    
    else         
        $response->language = "ru";
    print_r(json_encode($response));
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = new stdClass();
    $contents = file_get_contents('php://input');
    $data = json_decode($contents);     
    if (isset($data->language)) {
        $config = load_config();

        $message = [
            'command' => 'set_language=' . $data->language
        ];
        
        send_to_socket($message);

        if (save_config($config) === false) {
            $response = [
                'success' => 0,
                'message' => 'Ошибка сохранения'
            ];
        } else {
            $response = [
                'success' => 1,
                'message' => 'Язык сохранен'
            ];
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }
}

?> 