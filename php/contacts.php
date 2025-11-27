<?php

require __DIR__ . '/config.php';
ini_set('memory_limit', '512M');
set_time_limit(60);
ini_set('log_errors', 1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

function send_to_socket($message) {
    $socketPath = '/tmp/qt_wayland_ipc.socket';

    if (!file_exists($socketPath)) {
        error_log("Socket file not found: $socketPath");
        return false;
    }

    $socket = @socket_create(AF_UNIX, SOCK_STREAM, 0);
    if ($socket === false) {
        error_log("Socket creation failed: " . socket_strerror(socket_last_error()));
        return false;
    }

    if (!@socket_connect($socket, $socketPath)) {
        error_log("Socket connection failed: " . socket_strerror(socket_last_error($socket)));
        socket_close($socket);
        return false;
    }

    $json = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        error_log("Failed to encode message to JSON: " . json_last_error_msg());
        socket_close($socket);
        return false;
    }

    $payload = $json . "\n";
    $bytes = socket_write($socket, $payload, strlen($payload));
    socket_close($socket);

    if ($bytes === false || $bytes !== strlen($payload)) {
        error_log("Failed to write full message to socket");
        return false;
    }

    return true;
}

function clean_field($value) {
    $value = trim($value);
    $value = strip_tags($value);
    return preg_replace('/[^\p{L}\p{N}\s@._:-]/u', '', $value);
}

function get_log_finish() {
    $log_file = '/opt/cumanphone/var/log/cumanphone1.log';
    if (!file_exists($log_file)) {
        return "00:00:00";
    }

    $fp = fopen($log_file, 'r');
    if (!$fp) return "00:00:00";

    fseek($fp, 0, SEEK_END);
    $position = ftell($fp);
    $buffer = '';
    $lines = [];

    while ($position > 0 && count($lines) < 5000) {
        $position--;
        fseek($fp, $position);
        $char = fgetc($fp);
        if ($char === "\n") {
            if ($buffer !== '') {
                $lines[] = strrev($buffer);
                $buffer = '';
            }
        } else {
            $buffer .= $char;
        }
    }
    if ($buffer !== '') $lines[] = strrev($buffer);

    fclose($fp);

    foreach ($lines as $log) {
        if (strpos($log, 'Merging contacts') !== false) {
            if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $log, $matches)) {
                $dt = DateTime::createFromFormat('Y-m-d H:i:s', $matches[1]);
                return $dt ? $dt->format('H:i:s') : "00:00:00";
            }
        }
    }
    return "00:00:00";
}

function get_log_number() {
    $log_file = '/opt/cumanphone/var/log/cumanphone1.log';
    if (!file_exists($log_file)) {
        return 0;
    }

    $logs = @file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$logs) return 0;

    foreach (array_reverse($logs) as $log) {
        if (strpos($log, 'vcardModelList.count:') !== false) {
            if (preg_match('/vcardModelList\.count:\s+(\d+)/', $log, $matches)) {
                return intval($matches[1]);
            }
        }
    }

    return 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = new stdClass();

    $data->last_update_time = get_log_finish();
    $data->contacts_count = get_log_number();
    $data->contacts = [];

    $db_file = '/.local/share/CumanPhone/friends.db';
    if (file_exists($db_file)) {
        $db = new SQLite3($db_file);
        $results = $db->query('SELECT * FROM friends LIMIT 15000');
        if ($results) {
            while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
                $id = (int)$row['id'];
                $sip = clean_field($row['sip_uri']);
                $vCard = $row['vCard'];

                if (strpos($vCard, 'BEGIN:VCARD') !== false && strpos($vCard, 'END:VCARD') !== false) {
                    $data->contacts[] = [
                        'id' => $id,
                        'sip_uri' => $sip,
                        'vCard' => $vCard
                    ];
                }
            }
        }
    }

    $config = load_config();
    if (($config['ui']['import_remote_contacts_enabled'] ?? "0") === "1") {
        $data->status = "1";

        if (($config['ui']['import_remote_protocol_name'] ?? "0") === "0") {
            $data->protocol = "0";
            $data->url = $config['ui']['import_from_server_url_address'] ?? "";
        } else {
            $data->protocol = "1";
            $data->address_port = $config['ui']['import_from_server_ip_address_and_port'] ?? "";
            $data->filename = $config['ui']['import_from_server_file_name'] ?? "";
        }

        $type = $config['ui']['web_import_contacts_mode'] ?? "";
        $data->type = ($type === "Add") ? "1" : "0";
        $data->update_interval = $config['ui']['contacts_update_interval'] ?? "10";
    } else {
        $data->status = "0";
    }

    send_to_socket(json_encode([
        'type' => 'contacts',
        'event' => 'contacts_fetched',
        'contacts_count' => count($data->contacts),
        'last_update_time' => $data->last_update_time
    ]));

    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = new stdClass();
    $contents = file_get_contents('php://input');
    $data = json_decode($contents);

    if (isset($data->command)) {
        if ($data->command === "delete") {
            $dbPath = '/.local/share/CumanPhone/friends.db';
            
            if (file_exists($dbPath)) {
                file_put_contents($dbPath, '');
            }

            $response->success = 1;

            send_to_socket([
                'type' => 'contacts',
                'event' => 'contacts_deleted'
            ]);

            echo json_encode($response);
            exit;
        }

        if ($data->command === "save") {
            $response = new stdClass();

            $status = $data->status == "1" ? "1" : "0";
            $protocol = (string)($data->protocol ?? "");
            $url = (string)($data->url ?? "");
            $address_port = (string)($data->address_port ?? "");
            $filename = (string)($data->filename ?? "");
            $type = $data->type == "1" ? "Add" : "Replace";
            $update_interval = isset($data->update_interval) ? (string)$data->update_interval : "10";

            $message = [
                'type' => 'contacts',
                'event' => 'contacts_updated',
                'config' => [
                    'status' => $status,
                    'protocol' => $protocol,
                    'url' => $url,
                    'address_port' => $address_port,
                    'filename' => $filename,
                    'type' => $status === "1" ? $type : "",
                    'update_interval' => $update_interval
                ]
            ];

            if (send_to_socket($message)) {
                $response->success = 1;
                $response->message = "Конфигурация отправлена через сокет.";
            } else {
                $response->success = 0;
                $response->message = "Не удалось отправить конфигурацию через сокет.";
            }

            $response->success = 1;
            $response->message = "Конфигурация сохранена";
            $response->data = $message;

            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}
?>
