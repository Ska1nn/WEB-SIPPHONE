<?php

require __DIR__ . '/config.php';

function get_log_finish() {
    $log_file = '/opt/cumanphone/var/log/cumanphone1.log';
    if (!file_exists($log_file)) {
        throw new Exception("Log file not found");
    }

    $logs = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $last_update_time = "00:00:00";

    foreach (array_reverse($logs) as $log) {
        if (strpos($log, 'Book of Contacts is finished') !== false) {
            if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $log, $matches)) {
                $last_update_time = $matches[1];
                break;
            }
        }
    }

    return $last_update_time;
}

function get_log_number() {
    $log_file = '/opt/cumanphone/var/log/cumanphone1.log';
    if (!file_exists($log_file)) {
        throw new Exception("Log file not found");
    }

    $logs = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $contacts_count = 0;

    foreach (array_reverse($logs) as $log) {
        if (strpos($log, 'Number of contacts') !== false) {
            if (preg_match('/Number of contacts (\d+)/', $log, $matches)) {
                $contacts_count = intval($matches[1]);
                break;
            }
        }
    }

    return $contacts_count;
}

function get_contacts() {
    class MyDB extends SQLite3 {
        function __construct() {
            $this->open('/.local/share/CumanPhone/friends.db');
        }
    }

    $db = new MyDB();
    if (!$db) {
        http_response_code(500);
        echo json_encode(["error" => $db->lastErrorMsg()]);
        exit;
    }

    $query = "SELECT * FROM friends";
    $result = $db->query($query);

    $contacts = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $contacts[] = [
            'id' => $row['id'],
            'friend_list_id' => $row['friend_list_id'],
            'sip_uri' => $row['sip_uri'],
            'subscribe_policy' => $row['subscribe_policy'],
            'send_subscribe' => $row['send_subscribe'],
            'ref_key' => $row['ref_key'],
            'vCard' => $row['vCard'],
            'vCard_etag' => $row['vCard_etag'],
            'vCard_url' => $row['vCard_url'],
            'presence_received' => $row['presence_received'],
        ];
    }

    $db->close();
    return $contacts;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = new stdClass();
    $data->last_update_time = get_log_finish();
    $data->contacts_count = get_log_number();

    // Добавляем список контактов в ответ
    $data->contacts = get_contacts();

    if (isset($config['ui']['import_remote_contacts_enabled'])) {
        $data->protocol = $config['ui']['import_remote_contacts_enabled'];
    }

    $data->url = isset($config['ui']['import_from_server_url_address']) ? $config['ui']['import_from_server_url_address'] : "";
    $data->address_port = isset($config['ui']['import_from_server_ip_address_and_port']) ? $config['ui']['import_from_server_ip_address_and_port'] : "";
    $data->filename = isset($config['ui']['import_from_server_file_name']) ? $config['ui']['import_from_server_file_name'] : "";
    $data->type = isset($config['ui']['web_import_contacts_mode']) ? $config['ui']['web_import_contacts_mode'] : "";

    // Возвращаем данные в формате JSON
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
}

?>
