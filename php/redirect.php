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

    $response->unconditional = $config['forwarding']['unconditional'] ?? "";
    $response->busy          = $config['forwarding']['busy'] ?? "";
    $response->no_answer     = $config['forwarding']['no_answer'] ?? "";
    $response->no_answer_timeout = $config['forwarding']['no_answer_timeout'] ?? 30;

    print_r(json_encode($response));

}

elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $response = new stdClass();
    $contents = file_get_contents('php://input');
    $data = json_decode($contents);     

    $message = [
        "command" => "set_call_forwarding",
        "unconditional" => $data->unconditional,
        "busy" => $data->busy,
        "no_answer" => $data->no_answer,
        "no_answer_timeout" => $data->no_answer_timeout
    ];

    if (send_to_socket($message)) {
        $response->success = 1;
        $response->message = "Настройки отправлены в сокет";
    } else {
        $response->success = 0;
        $response->message = "Ошибка отправки в сокет";
    }

    print_r(json_encode($response, JSON_UNESCAPED_UNICODE));
}

?>
