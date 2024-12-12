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



if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = new stdClass();

    $data->last_update_time = get_log_finish();

    $data->contacts_count = get_log_number();


    if (isset($config['ui']['import_remote_contacts_enabled'])) {
        $data->protocol = $config['ui']['import_remote_contacts_enabled'];
    }
    if(isset($config['ui']['import_from_server_url_address'])){
        $data->url = $config['ui']['import_from_server_url_address'];
    }
    else
        $data->url = "";


    if(isset($config['ui']['import_from_server_ip_address_and_port'])){
        $data->address_port = $config['ui']['import_from_server_ip_address_and_port'];
    }
    else
        $data->address_port = "";
    if(isset($config['ui']['import_from_server_file_name'])){
            $data->filename = $config['ui']['import_from_server_file_name'];
    }

    if(isset($config['ui']['web_import_contacts_mode'])){
        $data->type = $config['ui']['web_import_contacts_mode'];
    }
    

    print_r(json_encode($data));
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = new stdClass();
    $contents = file_get_contents('php://input');
    $data = json_decode($contents);

    if (isset($data->{'command'})) {
        if ($data->{'command'} == "delete") {
            $dbPath = escapeshellarg('/.local/share/CumanPhone/friends.db');
            $command = "> " . $dbPath;
            $result = shell_exec($command);

            if (file_exists('/.local/share/CumanPhone/friends.db')) {
                if (filesize('/.local/share/CumanPhone/friends.db') == 0) {
                    $response->success = 1;
                } else {
                    $response->success = 0;
                }
            } else {
                $response->success = 0;
            }

            print_r(json_encode($response));
        } elseif ($data->{'command'} == "save") {
            $config = load_config();
            if (isset($data->download)) {
                if ($data->download->status == true ) {
                    if ($data->download->download_type == "udp") {
                        $config['ui']['import_from_server_ip_address_and_port'] = $data->address_port;
                        $config['ui']['import_from_server_file_name'] = $data->filename;
                        $config['ui']['import_from_server_url_address'] = "";
                    }else if($data->download->download_type == "https") {
                        $config['ui']['import_from_server_url_address'] = $data->download->url;
                        $config['ui']['import_from_server_ip_address_and_port'] = "";
                        $config['ui']['import_from_server_file_name'] = "";
                    }
                    $config['ui']['web_import_contacts_mode'] = $data->download->type;
                } else {
                    if(isset($config['ui']['import_from_server_url_address'])){
                        $config['ui']['import_from_server_url_address'] = "";
                    }
                    if(isset($config['ui']['import_from_server_ip_address_and_port'])){
                        $config['ui']['import_from_server_ip_address_and_port'] = "";
                    }
                    if(isset($config['ui']['import_from_server_file_name'])){
                        $config['ui']['import_from_server_file_name'] = "";
                    }
                    if(isset($config['ui']['import_contacts_mode'])){
                        $config['ui']['import_contacts_mode'] = "";
                    }
                }
            }
        

            if (save_config($config) === false)
                $response->success = 0;
            else
                $response->success = 1;
            print_r(json_encode($response));

        }
    }
}
?>