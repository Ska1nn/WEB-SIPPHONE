<?php

require __DIR__ . '/config.php';


function hash_pin_code($pinCode) {
    return sha1($pinCode);
}
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
    if ( isset($config['ui']['admin_login']) )
        $data->admin_login = $config['ui']['admin_login'];
    else
        $data->admin_login = "";

    if ( isset($config['ui']['pin_code_enabled']) )
        $data->pin_code_enabled = $config['ui']['pin_code_enabled'];
    else
        $data->pin_code_enabled = "False";
    print_r(json_encode($data));
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = new stdClass();   
    $contents = file_get_contents('php://input');
    $data = json_decode($contents);
    if ( isset($data->{'command'} ) ) {
        if ( $data->{'command'} == "save" ) { 
            $config = load_config();
            if (isset($data->authorization)) {
                if ($data->authorization == "1") {
                    send_to_socket([
                        "command" => "set_admin_login",
                        "login" => $data->admin_login
                    ]);
                    send_to_socket([
                        "command" => "set_admin_password_hash",
                        "hash" => md5($data->admin_password)
                    ]);
                } else {
                    send_to_socket([
                        "command" => "reset_admin_password"
                    ]);
                }
            }
            if (isset($data->pin_code_enabled)) {
                if ($data->pin_code_enabled == "1") {
                    send_to_socket([
                        "command" => "set_pin_code_enabled",
                        "enabled" => "True"
                    ]);
                    send_to_socket([
                        "command" => "set_pin_code",
                        "hash" => hash_pin_code($data->pin_code_hash)
                    ]);
                } else {
                    send_to_socket([
                        "command" => "reset_pin_code"
                    ]);
                }
            }

            $response->success = 1;
            $response->message = "Конфигурация сохранена";
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
        }
    }
}

?> 
