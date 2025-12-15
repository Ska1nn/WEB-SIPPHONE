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
    $ringtons = array();
    $files = array_diff(scandir('/opt/cumanphone/share/sounds/rings/'), array('.', '..'));
    foreach ($files as &$file) { 
        array_push($ringtons, $file);
    }  
    $data->ringtons = $ringtons;

    $config = load_config();
    if ( isset($config['sound']['local_ring']) ) {
        $ringtone = $config['sound']['local_ring'];  
        $data->ringtone = basename($ringtone);   

    }  
    else         
        $data->ringtone = "notes_of_the_optimistic.mkv";  

    if ( isset($config['ui']['auto_answer']) ) {
        $data->auto_answer = $config['ui']['auto_answer'];   
    }  
    else 
        $data->auto_answer = 0;

    if ( isset($config['ui']['conference_mxone']) ) {
        $data->mxone = $config['ui']['conference_mxone'];   
    }  
    else 
        $data->mxone = 0;

    if ( isset($config['ui']['do_not_disturb']) ) {
        $data->do_not_disturb = $config['ui']['do_not_disturb'];   
    }  else 
        $data->do_not_disturb = 0;  // grep 'do_not_disturb' /opt/cumanphone/etc/config.conf | tail -n 1 | awk -F= '{print $2}'

    if ( isset($config['ui']['unknown_phone_block']) ) {
        $data->unknown_phone_block = $config['ui']['unknown_phone_block'];
    }  else             
        $data->unknown_phone_block = 0;   // grep 'unknown_phone_block' /opt/cumanphone/etc/config.conf | tail -n 1 | awk -F= '{print $2}'

    if ( isset($config['ui']['show_name_from_contacts_enabled']) ) {
        $data->show_name_from_contacts_enabled = $config['ui']['show_name_from_contacts_enabled'];   
    }  else 
        $data->show_name_from_contacts_enabled = 0; // grep 'show_name_from_contacts_enabled' /opt/cumanphone/etc/config.conf | tail -n 1 | awk -F= '{print $2}'

    if ( isset($config['ui']['block_anonymous_calls_enabled']) ) {
        $data->block_anonymous_calls_enabled = $config['ui']['block_anonymous_calls_enabled'];   
    }  else 
        $data->block_anonymous_calls_enabled = 0;    // grep 'block_anonymous_calls_enabled' /opt/cumanphone/etc/config.conf | tail -n 1 | awk -F= '{print $2}'

    if ( isset($config['ui']['auto_call_number_enabled']) ) {
        $data->auto_call_number_enabled = $config['ui']['auto_call_number_enabled'];   
    }  else 
        $data->auto_call_number_enabled = 0;    // grep 'auto_call_number_enabled' /opt/cumanphone/etc/config.conf | tail -n 1 | awk -F= '{print $2}'

    if ( isset($config['ui']['save_microphone_off_state_enabled']) ) {
        $data->save_microphone_off_state_enabled = $config['ui']['save_microphone_off_state_enabled'];   
    }  
    else 
        $data->save_microphone_off_state_enabled = "";   // grep 'save_microphone_off_state_enabled' /opt/cumanphone/etc/config.conf | tail -n 1 | awk -F= '{print $2}'

    if ( isset($config['ui']['auto_call_number']) ) {
        $data->auto_call_number = $config['ui']['auto_call_number'];   
    }
    
    print_r(json_encode($data));
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = new stdClass();   
    $contents = file_get_contents('php://input');
    $data = json_decode($contents);

    if (isset($data->{'command'})) {
        if ($data->command == "save") {

            // Формируем сообщение для сокета
            $ringtone = "/opt/cumanphone/share/sounds/rings/".$data->ringtone;
            send_to_socket([
                "page" => "calls",
                "command" => "set_auto_answer",
                "value" => (int)$data->auto_answer
            ]);

            send_to_socket([
                "page" => "calls",
                "command" => "set_do_not_disturb",
                "value" => (int)$data->do_not_disturb
            ]);

            send_to_socket([
                "page" => "calls",
                "command" => "set_unknown_phone_block",
                "value" => (int)$data->unknown_phone_block
            ]);

            send_to_socket([
                "page" => "calls",
                "command" => "set_show_name_from_contacts_enabled",
                "value" => (int)$data->show_name_from_contacts_enabled
            ]);

            send_to_socket([
                "page" => "calls",
                "command" => "set_auto_call_number_enabled",
                "value" => (int)$data->auto_call_number_enabled
            ]);

            send_to_socket([
                "page" => "calls",
                "command" => "set_block_anonymous_calls_enabled",
                "value" => (int)$data->block_anonymous_calls_enabled
            ]);

            send_to_socket([
                "page" => "calls",
                "command" => "set_conference_mxone",
                "value" => (int)$data->mxone
            ]);

            send_to_socket([
                "page" => "calls",
                "command" => "set_local_ring",
                "value" => $ringtone
            ]);
            
            send_to_socket([
                "page" => "calls",
                "command" => "set_save_microphone_off_state_enabled",
                "value" => (int)$data->save_microphone_off_state_enabled
            ]);

            // Номер только если включено
            if ($data->auto_call_number_enabled === 1 ) {
                send_to_socket([
                    "page" => "calls",
                    "command" => "set_auto_call_number",
                    "value" => $data->auto_call_number
                ]);
            }

            // Отправляем в сокет
            if (send_to_socket($message)) {
                $response->success = 1;
                $response->message = "Настройки отправлены в сокет";
            } else {
                $response->success = 0;
                $response->message = "Ошибка отправки в сокет";
            }

            print_r(json_encode($response, JSON_UNESCAPED_UNICODE));
        } 
        elseif ($data->command == "upload-ringtone") {
            $content = $data->{'ringtone'}; 
            if (preg_match('/^data:audio\/(\w+);base64,/', $content, $type)) {
                $content = substr($content, strpos($content, ',') + 1);
                $type = strtolower($type[1]);
                $content = str_replace(' ', '+', $content);
                $content = base64_decode($content);
                if ($content === false) {
                    $response->success = 0;
                    $response->message = 'base64_decode failed';
                } else {
                    $filename = "/opt/cumanphone/share/sounds/rings/".$data->filename;
                    $response->filename = $data->filename;
                    if (file_put_contents($filename, $content) === false) {
                        $response->success = 0;
                        $response->message = "Ошибка сохранения файла.";
                    } else {  
                        $response->success = 1;
                        $response->message = "Файл успешно сохранен.";
                    }
                }     
            }
            print_r(json_encode($response, JSON_UNESCAPED_UNICODE));
        }
    }
}

?> 