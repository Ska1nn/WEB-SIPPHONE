<?php

require __DIR__ . '/config.php';

function get_sink_volume( $name ) {
    $output = shell_exec("pactl -- get-sink-volume {$name} | grep -Po '\\d+(?=%)' | head -n 1");
    $volume = trim((string) $output);     
    //if ( !is_numeric($volume) )
    //    $volume = 0;
    return $volume;
}

function set_sink_volume( $name, $volume ) {    
    $output = shell_exec("pactl -- set-sink-volume {$name} {$volume}%");
}

function get_source_volume( $name ) {
    $output = shell_exec("pactl -- get-source-volume {$name} | grep -Po '\\d+(?=%)' | head -n 1");
    $volume = trim((string) $output);
    //if ( !is_numeric($volume) )
    //    $volume = 0;
    return $volume;
}

function set_source_volume( $name, $volume ) {
    $output = shell_exec("pactl -- set-source-volume {$name} {$volume}%");
}

function get_handsfree_volume() {
    $config = load_config();
    if (!isset($config['ui']['handsfree_volume'])) {
        return intval(100);
    }
    return intval($config['ui']['handsfree_volume']);
}

function set_handsfree_volume($volume) {
    $config = load_config();
    if ($volume >= 0 && $volume <= 100) {
        $config['ui']['handsfree_volume'] = $volume;
        save_config($config);
    } else {
        throw new Exception("Volume must be between 0 and 100.");
    }
}

function get_handsfree_mic_volume() {
    $config = load_config();
    if (!isset($config['ui']['handsfree_mic_volume'])) {
        return intval(100);
    }
    return intval($config['ui']['handsfree_mic_volume']);
}

function set_handsfree_mic_volume($volume) {
    $config = load_config();
    if ($volume >= 0 && $volume <= 100) {
        $config['ui']['handsfree_mic_volume'] = $volume;
        save_config($config);
    } else {
        throw new Exception("Volume must be between 0 and 100.");
    }
}

function get_phoneset_volume() {
    $config = load_config();
    if (!isset($config['ui']['phoneset_volume'])) {
        return intval(100);
    }
    return intval($config['ui']['phoneset_volume']);
}

function set_phoneset_volume($volume) {
    $config = load_config();
    if ($volume >= 0 && $volume <= 100) {
        $config['ui']['phoneset_volume'] = $volume;
        save_config($config);
    } else {
        throw new Exception("Volume must be between 0 and 100.");
    }
}

function get_phoneset_mic_volume() {
    $config = load_config();
    if (!isset($config['ui']['phoneset_mic_volume'])) {
        return intval(100);
    }
    return intval($config['ui']['phoneset_mic_volume']);
}

function set_phoneset_mic_volume($volume) {
    $config = load_config();
    if ($volume >= 0 && $volume <= 100) {
        $config['ui']['phoneset_mic_volume'] = $volume;
        save_config($config);
    } else {
        throw new Exception("Volume must be between 0 and 100.");
    }
}

function get_headset_volume() {
    $config = load_config();
    if (!isset($config['ui']['headset_volume'])) {
        return intval(100);
    }
    return intval($config['ui']['headset_volume']);
}

function set_headset_volume($volume) {
    $config = load_config();
    if ($volume >= 0 && $volume <= 100) {
        $config['ui']['headset_volume'] = $volume;
        save_config($config);
    } else {
        throw new Exception("Volume must be between 0 and 100.");
    }
}

function get_headset_mic_volume() {
    $config = load_config();
    if (!isset($config['ui']['headset_mic_volume'])) {
        return intval(100);
    }
    return intval($config['ui']['headset_mic_volume']);
}

function set_headset_mic_volume($volume) {
    $config = load_config();
    if ($volume >= 0 && $volume <= 100) {
        $config['ui']['headset_mic_volume'] = $volume;
        save_config($config);
    } else {
        throw new Exception("Volume must be between 0 and 100.");
    }
}

function get_ringtone_volume() {
    $config = load_config();
    if (!isset($config['ui']['ringtone_volume'])) {
        return intval(100);
    }
    return intval($config['ui']['ringtone_volume']);
}

function set_ringtone_volume($volume) {
    $config = load_config();
    if ($volume >= 0 && $volume <= 100) {
        $config['ui']['ringtone_volume'] = $volume;
        save_config($config);
    } else {
        throw new Exception("Volume must be between 0 and 100.");
    }
}


function get_backlight( ) {
    $output = shell_exec("cat /sys/class/backlight/lvds_backlight/brightness");
    $backlight = trim($output);
    if ( !is_numeric($backlight) )
        $backlight = 100;
    return (100 - $backlight);
}

function set_backlight( $value ) {
    $backlight = 100 - $value; 
    $output = shell_exec("echo {$backlight} > /sys/class/backlight/lvds_backlight/brightness");
}

function send_to_socket($message) {
    $socketPath = '/tmp/qt_wayland_ipc.socket';

    $socket = stream_socket_client("unix://$socketPath", $errno, $errstr);

    if (!$socket) {
        error_log("Socket connection error: $errstr ($errno)");
        header('Content-Type: application/json');
        echo json_encode(["error" => "Unable to connect to socket.", "details" => "$errstr ($errno)"]);
        return false;
    } else {
        $message = trim($message) . "\n";

        $bytesWritten = fwrite($socket, $message);

        if ($bytesWritten === false) {
            error_log("Error: Unable to write to socket.");
            header('Content-Type: application/json');
            echo json_encode(["error" => "Unable to write to socket."]);
            fclose($socket);
            return false;
        }

        fflush($socket);
        fclose($socket);
        
        header('Content-Type: application/json');
        echo json_encode(["success" => true]);
        return true;
    }
}

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $data = new stdClass();

        $data->photos = load_images();
    
        $data->phone_sink = get_phoneset_volume();
        $data->phone_source = get_phoneset_mic_volume();
    
        $data->handsfree_sink = get_handsfree_volume();
        $data->handsfree_source = get_handsfree_mic_volume();
    
        $data->headset_sink = get_headset_volume();
        $data->headset_source = get_headset_mic_volume();
    
        $data->backlight = get_backlight();
    
        $data->ringtone_volume = get_ringtone_volume();
    
        $data->datetime = trim(shell_exec('date "+%FT%H:%M:%S"'));
          
        $tz = trim(shell_exec('date +%Z'));
        if ( str_starts_with($tz, '-') || str_starts_with($tz, '+') )       
            $data->timezone = "UTC".$tz;
        else  
            $data->timezone = $tz;
    
        $ntpdate = load_ntpdate();
        if ( isset($ntpdate['NTPSERVERS']) )
            $data->ntp_server = $ntpdate['NTPSERVERS'];
        else   
            $data->ntp_server = null;
    
        $config = load_config();
    
        if ( isset($config['ui']['time_widget']) )
            $data->time_widget = boolval($config['ui']['time_widget']);
        else 
            $data->time_widget = false;
    
        if ( isset($config['ui']['calendar_widget']) )
            $data->calendar_widget = boolval($config['ui']['calendar_widget']);
        else 
            $data->calendar_widget = false;
    
        if ( isset($config['ui']['blf_widget']) )
            $data->blf_widget = boolval($config['ui']['blf_widget']);
        else 
            $data->blf_widget = false;
    
        if ( isset($config['ui']['wallpaper']) ) {
            $path = $config['ui']['wallpaper'];  
            $type = pathinfo($path, PATHINFO_EXTENSION);
            $content = file_get_contents($path);
            $data->wallpaper = 'data:image/' . $type . ';base64,' . base64_encode($content); 
        }
    
        if ( isset($config['ui']['screensaver_timeout']) ) {
            $data->screensaver_timeout = $config['ui']['screensaver_timeout']; 
        }
        if ( isset($config['ui']['sleep_date_time'])) {
            $data->sleep_date_time = $config['ui']['sleep_date_time'];
        }
        if ( isset($config['ui']['sleep_wallpaper_path'])){
            $data->sleep_wallpaper_path = $config['ui']['sleep_wallpaper_path'];
        }
    
        print_r(json_encode($data));
    }
    
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = new stdClass();
    $contents = file_get_contents('php://input');
    $data = json_decode($contents);     
    if ( isset($data->{'command'}) ) {
       if ( $data->{'command'} == "set-wallpaper" ) {
           $content = $data->{'wallpaper'}; 
           if ( preg_match('/^url\(\"data:image\/(\w+);base64,/', $content, $type) ) {
               $content = substr($content, strpos($content, ',') + 1);
               $type = strtolower($type[1]);
               if ( !in_array($type, [ 'jpg', 'jpeg', 'gif', 'png' ]) ) {
                   throw new \Exception('invalid image type');
               }
               $content = str_replace( ' ', '+', $content);
               $content = base64_decode($content);
               $response->success = 0;
               if ( $content === false ) {
                   throw new \Exception('base64_decode failed');
               }
               else {
                   $filename = "/opt/cumanphone/share/images/wallpaper.{$type}";
                   file_put_contents($filename, $content);
                   $config = load_config();
                   $config['ui']['wallpaper'] = $filename;
                   if ( save_config($config) !== false )
                       $response->success = 1;
               }     
               print_r(json_encode($response));

           }
       }
       elseif ( $data->{'command'} == "reset-wallpaper" ) {
           $config = load_config();
           if ( isset($config['ui']['wallpaper']) ) {
               unset($config['ui']['wallpaper']);
               if ( save_config($config) === false )
                   $response->success = 0;
               else 
                   $response->success = 1;
               print_r(json_encode($response));
           }
       }
       elseif ( $data->{'command'} == 'set-wallpaper-sleep') {

            $wallpaperPath = $data->{'sleep_wallpaper_path'};
        
            $response = new stdClass();
            $response->success = 0;
        
            if (file_exists($wallpaperPath)) {
                
                $config = load_config();
                $config['ui']['sleep_wallpaper_path'] = $wallpaperPath;
                if (save_config($config) !== false) {
                    $response->success = 1;
                }
            }
        
            echo json_encode($response);
        }
        elseif ($data->{'command'} == "set-widget") {
            $config = load_config();
        
            if (isset($data->time_widget)) {
                $config['ui']['time_widget'] = $data->time_widget;
            }
            if (isset($data->calendar_widget)) {
                $config['ui']['calendar_widget'] = $data->calendar_widget;
            }
            if (isset($data->blf_widget)) {
                $config['ui']['blf_widget'] = $data->blf_widget;
            }
        
            if (save_config($config) === false) {
                $response = new stdClass();
                $response->success = 0;
                echo json_encode($response);
            } else {
                $response = new stdClass();
                $response->success = 1;
                echo json_encode($response);
            }
        }
       elseif ( $data->{'command'} == "save" ) {

            $volume = $data->{'phone_sink'}; 
            $message = "SET_PHONE_PLAYBACK_VOLUME={$volume}";
            send_to_socket($message);
            
            $volume = $data->{'phone_source'};
            $message = "SET_PHONE_CAPTURE_VOLUME={$volume}";
            send_to_socket($message);
            
            $volume = $data->{'handsfree_sink'};
            $message = "SET_HANDSFREE_PLAYBACK_VOLUME={$volume}";
            
            send_to_socket($message);
            $volume = $data->{'handsfree_source'};
            
            $message = "SET_HANDSFREE_CAPTURE_VOLUME={$volume}";
            send_to_socket($message);
            $volume = $data->{'headset_sink'};
            $message = "SET_HEADSET_PLAYBACK_VOLUME={$volume}";
            send_to_socket($message);
            
            $volume = $data->{'headset_source'};
            $message = "SET_HEADSET_CAPTURE_VOLUME={$volume}";
            send_to_socket($message);
            
            $config['ui']['sleep_date_time'] = $data->sleep_date_time;

            if (isset($data->{'ringtone_volume'}) && is_numeric($data->{'ringtone_volume'})) {
            $ringtone_volume = intval($data->{'ringtone_volume'});
            if ($ringtone_volume >= 0 && $ringtone_volume <= 100) {
                $message = "SET_RINGTONE_PLAYBACK_VOLUME={$ringtone_volume}";
                send_to_socket($message);
                set_ringtone_volume($ringtone_volume);
            }
        }

           $backlight = $data->{'backlight'};  
           if ( is_numeric($backlight) )
               set_backlight($backlight);

           if ( isset($data->timezone) ) {
                if ( str_starts_with($data->timezone, "UTC") ) {
                    $path = "/usr/share/zoneinfo/Etc/";
                    if ( strlen($data->timezone) > 3 ) {
                        $sign = substr($data->timezone, 3, 1);
                        $offset = substr($data->timezone, 4);
                        if ( str_starts_with($offset, "0") ) 
                            $offset = substr($offset,-1); 
                        if ( $sign == "-" )
                            $path = $path."GMT+".$offset;
                        else
                            $path = $path."GMT-".$offset; 
                    } 
                    else 
                        $path = $path."UTC";
                    shell_exec("ln -f -s $path /etc/localtime");
                }           
           }

           $ntpdate = load_ntpdate();
           if ( isset($data->ntp_enable) ) {
               if ( $data->ntp_enable == 0 ) {
                   if ( isset($data->datetime) ) {
                       shell_exec("date $data->datetime");                        
                   }
                   $ntpdate['NTPSERVERS'] = "";     
               }  
              else { 
                  if ( isset($data->ntp_server) )
                      $ntpdate['NTPSERVERS'] = $data->ntp_server;
              }  
              save_ntpdate($ntpdate); 
           }
   
       
           $config = load_config();
           if ( isset($data->time_widget) )
               $config['ui']['time_widget'] = $data->time_widget;
           if ( isset($data->calendar_widget) )
               $config['ui']['calendar_widget'] = $data->calendar_widget;
           if ( isset($data->blf_widget) )
               $config['ui']['blf_widget'] = $data->blf_widget;
           if ( isset($data->screensaver_timeout) )
               $config['ui']['screensaver_timeout'] = $data->screensaver_timeout;
            if ( isset($data->sleep_date_time) )
               $config['ui']['sleep_date_time'] = $data->sleep_date_time;
           if ( isset($data->backlight) )
              $config['ui']['backlight'] = $data->backlight;

           if ( save_config($config) === false )
               $response->success = 0;
           else 
               $response->success = 1;
           print_r(json_encode($response));

       }
    }
}
?>
