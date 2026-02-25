<?php

require __DIR__ . '/config.php';

function unicode_to_hex(string $str): string {
    $hex_str = '';
    $len = mb_strlen($str, 'UTF-8');
    for ($i = 0; $i < $len; $i++) {
        $char = mb_substr($str, $i, 1, 'UTF-8');
        $hex_str .= '\\x' . strtoupper(bin2hex(iconv('UTF-8', 'UCS-2BE', $char)));
    }
    return $hex_str;
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
function hex_to_unicode(string $str): string {
    $hex_str = str_replace('\\x', '', $str);
    $hex_str = preg_replace('/[^0-9A-Fa-f]/', '', $hex_str);

    $unistr = '';
    $j = 0;
    for ($i = 0; $i < strlen($hex_str);) {
        $tmp_str = $hex_str[$j];
        $j++;
        while ($j % 4 != 0) {
            $tmp_str .= $hex_str[$j];
            $j++;
        }
        $dec = hexdec($tmp_str);
        $unichar = mb_convert_encoding('&#' . intval($dec) . ';', 'UTF-8', 'HTML-ENTITIES');
        $unistr .= $unichar;
        $i = $j;
    }

    return $unistr;
}

function log_message(string $message): void {
    file_put_contents('app.log', date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
}

function getSipDomain(string $account, array $config): ?string {
    if (preg_match('/@([0-9a-zA-Z\.\-]+)/', $account, $matches)) {
        return $matches[1];
    }

    return $config['auth_info_0']['domain'] ?? null;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $data = new stdClass();
    $accounts = [];
    $config = load_config();
    foreach ($config as $key => $value) {
        if (strpos($key, 'proxy_') === 0 && isset($value['reg_identity'])) {
            preg_match('/<([^>]+)>/', $value['reg_identity'], $matches);
            if (isset($matches[1])) {
                $accounts[] = $matches[1];
            }
        }
    }
    $data->accounts = $accounts;

    $blf = load_blf();
    foreach ($blf as $key => $entry) {
        if (isset($entry['name'])) {
            $blf[$key]['name'] = hex_to_unicode($entry['name']);
        }
    }
    $data->blf = $blf;

    echo json_encode($data, JSON_UNESCAPED_UNICODE);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $response = new stdClass();
    $contents = file_get_contents('php://input');
    $data = json_decode($contents);

    if (isset($data->command)) {

        if ($data->command == "save") {

            $account = $data->account;

            if (isset($data->type, $data->subType) && $data->type == 1 && $data->subType == 1) {
                $account = 0;
            }

            $subType = $data->subType ?? 0;

            $message = [
                "page" => "blf",
                "command" => "save",
                "key" => $data->key,
                "type" => $data->type,
                "subType" => $subType,
                "account" => $account,
                "name" => $data->name ?? '',
                "number" => $data->number
            ];

            if (send_to_socket($message)) {
                $response->success = 1;
                $response->message = "BLF-данные отправлены в сокет";
            } else {
                $response->success = 0;
                $response->message = "Ошибка отправки BLF в сокет";
            }

            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }

        elseif ($data->command == "reset") {

            $message = [
                "page" => "blf",
                "command" => "reset",
                "key" => $data->key
            ];

            if (send_to_socket($message)) {
                $response->success = 1;
                $response->message = "Сброс клавиши отправлен в сокет";
            } else {
                $response->success = 0;
                $response->message = "Ошибка отправки сброса в сокет";
            }

            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;   // <-- ОБЯЗАТЕЛЬНО
        }
    }
}

?>
