<?php
// чтобы ошибки не мешали JSON
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

require __DIR__ . '/config.php';

function get_sink_volume($name) {
    $output = shell_exec("pactl -- get-sink-volume {$name} | grep -Po '\\d+(?=%)' | head -n 1");
    return trim((string) $output);
}

function set_sink_volume($name, $volume) {
    shell_exec("pactl -- set-sink-volume {$name} {$volume}%");
}

function get_source_volume($name) {
    $output = shell_exec("pactl -- get-source-volume {$name} | grep -Po '\\d+(?=%)' | head -n 1");
    return trim((string) $output);
}

function set_source_volume($name, $volume) {
    shell_exec("pactl -- set-source-volume {$name} {$volume}%");
}

// ======== helpers with config ========
function get_handsfree_volume() {
    $config = load_config();
    return intval($config['ui']['handsfree_volume'] ?? 100);
}
function set_handsfree_volume($volume) {
    $config = load_config();
    if ($volume >= 0 && $volume <= 100) {
        $config['ui']['handsfree_volume'] = $volume;
        save_config($config);
    }
}
function get_handsfree_mic_volume() {
    $config = load_config();
    return intval($config['ui']['handsfree_mic_volume'] ?? 100);
}
function set_handsfree_mic_volume($volume) {
    $config = load_config();
    if ($volume >= 0 && $volume <= 100) {
        $config['ui']['handsfree_mic_volume'] = $volume;
        save_config($config);
    }
}
function get_phoneset_volume() {
    $config = load_config();
    return intval($config['ui']['phoneset_volume'] ?? 100);
}
function set_phoneset_volume($volume) {
    $config = load_config();
    if ($volume >= 0 && $volume <= 100) {
        $config['ui']['phoneset_volume'] = $volume;
        save_config($config);
    }
}
function get_phoneset_mic_volume() {
    $config = load_config();
    return intval($config['ui']['phoneset_mic_volume'] ?? 100);
}
function set_phoneset_mic_volume($volume) {
    $config = load_config();
    if ($volume >= 0 && $volume <= 100) {
        $config['ui']['phoneset_mic_volume'] = $volume;
        save_config($config);
    }
}
function get_headset_volume() {
    $config = load_config();
    return intval($config['ui']['headset_volume'] ?? 100);
}
function set_headset_volume($volume) {
    $config = load_config();
    if ($volume >= 0 && $volume <= 100) {
        $config['ui']['headset_volume'] = $volume;
        save_config($config);
    }
}
function get_headset_mic_volume() {
    $config = load_config();
    return intval($config['ui']['headset_mic_volume'] ?? 100);
}
function set_headset_mic_volume($volume) {
    $config = load_config();
    if ($volume >= 0 && $volume <= 100) {
        $config['ui']['headset_mic_volume'] = $volume;
        save_config($config);
    }
}
function get_ringtone_volume() {
    $config = load_config();
    return intval($config['ui']['ringtone_volume'] ?? 100);
}
function set_ringtone_volume($volume) {
    $config = load_config();
    if ($volume >= 0 && $volume <= 100) {
        $config['ui']['ringtone_volume'] = $volume;
        save_config($config);
    }
}

function get_backlight() {
    $output = shell_exec("cat /sys/class/backlight/lvds_backlight/brightness");
    $backlight = trim($output);
    if (!is_numeric($backlight)) $backlight = 100;
    return (100 - $backlight);
}
function set_backlight($value) {
    $backlight = 100 - $value;
    shell_exec("echo {$backlight} > /sys/class/backlight/lvds_backlight/brightness");
}

// ======== socket ========
function send_to_socket($message) {
    $socketPath = '/tmp/qt_wayland_ipc.socket';
    $message = trim($message) . "\n";

    file_put_contents('/tmp/socket_debug.log', date('[Y-m-d H:i:s] ') . "Sending: $message\n", FILE_APPEND);

    $socket = @stream_socket_client("unix://$socketPath", $errno, $errstr); // @ чтобы не выводило warning
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

// ======== HTTP handling ========
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
    $data->timezone = (str_starts_with($tz, '-') || str_starts_with($tz, '+')) ? "UTC".$tz : $tz;

    $ntpdate = load_ntpdate();
    if ( isset($ntpdate['NTPSERVERS']) )
            $data->ntp_server = $ntpdate['NTPSERVERS'];
        else   
            $data->ntp_server = null;

        if (isset($ntpdate['UPDATE_HWCLOCK']) && $ntpdate['UPDATE_HWCLOCK'] === "yes") {
            $data->ntp_hwclock = 1;
        } else {
            $data->ntp_hwclock = 0;
        }

    $config = load_config();
    $data->time_widget = !empty($config['ui']['time_widget']);
    $data->calendar_widget = !empty($config['ui']['calendar_widget']);
    $data->blf_widget = !empty($config['ui']['blf_widget']);

    if (isset($config['ui']['wallpaper'])) {
        $path = $config['ui']['wallpaper'];  
        $type = pathinfo($path, PATHINFO_EXTENSION);
        $content = file_get_contents($path);
        $data->wallpaper = 'data:image/' . $type . ';base64,' . base64_encode($content); 
    }
    if (isset($config['ui']['sleep_wallpaper_enabled']))
        $data->sleep_wallpaper_enabled = $config['ui']['sleep_wallpaper_enabled'];
    if (isset($config['ui']['screensaver_timeout']))
        $data->screensaver_timeout = $config['ui']['screensaver_timeout'];
    if (isset($config['ui']['sleep_date_time']))
        $data->sleep_date_time = $config['ui']['sleep_date_time'];
    if (isset($config['ui']['sleep_wallpaper_path']))
        $data->sleep_wallpaper_path = $config['ui']['sleep_wallpaper_path'];

    echo json_encode($data);
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = new stdClass();
    $contents = file_get_contents('php://input');
    $data = json_decode($contents);

    if (isset($data->command)) {
        if ($data->command === "set-wallpaper") {
            $content = $data->wallpaper; 
            if (preg_match('/^url\(\"data:image\/(\w+);base64,/', $content, $type)) {
                $content = substr($content, strpos($content, ',') + 1);
                $type = strtolower($type[1]);
                if (!in_array($type, [ 'jpg', 'jpeg', 'gif', 'png' ])) {
                    throw new \Exception('invalid image type');
                }
                $content = str_replace(' ', '+', $content);
                $content = base64_decode($content);
                $response->success = 0;
                if ($content !== false) {
                    $filename = "/opt/cumanphone/share/images/wallpaper.{$type}";
                    file_put_contents($filename, $content);
                    $config = load_config();
                    $config['ui']['wallpaper'] = $filename;
                    if (save_config($config) !== false)
                        $response->success = 1;
                }
                echo json_encode($response);
            }
        }
        elseif ($data->command === "reset-wallpaper") {
            $config = load_config();
            if (isset($config['ui']['wallpaper'])) {
                unset($config['ui']['wallpaper']);
                $response->success = (save_config($config) === false) ? 0 : 1;
                echo json_encode($response);
            }
        }
        elseif ($data->command === "set-wallpaper-sleep") {
            $wallpaperPath = $data->sleep_wallpaper_path;
            $response->success = 0;
            if (file_exists($wallpaperPath)) {
                $config = load_config();
                $config['ui']['sleep_wallpaper_path'] = $wallpaperPath;
                if (save_config($config) !== false) {
                    $response->success = 1;
                    if (isset($data->sleep_wallpaper_enabled)) {
                        $message = "SET_SLEEP_WALLPAPER_ENABLED=" . ($data->sleep_wallpaper_enabled ? 1 : 0);
                        send_to_socket($message);
                    }
                    if (isset($data->sleep_date_time)) {
                        $message = "SET_SLEEP_DATE_TIME=" . intval($data->sleep_date_time);
                        send_to_socket($message);
                    }
                    $message = "SET_SLEEP_WALLPAPER=" . $wallpaperPath;
                    send_to_socket($message);
                }
            }
            echo json_encode($response);
        }
        elseif ($data->command === "set-widget") {
            $config = load_config();
            if (isset($data->time_widget)) $config['ui']['time_widget'] = $data->time_widget;
            if (isset($data->calendar_widget)) $config['ui']['calendar_widget'] = $data->calendar_widget;
            if (isset($data->blf_widget)) $config['ui']['blf_widget'] = $data->blf_widget;
            if (save_config($config) === false) {
                $response->success = 0;
            } else {
                $response->success = 1;
                $socketData = [
                    'type' => 'widget',
                    'event' => 'widget_settings_updated',
                    'time_widget' => (int) $data->time_widget,
                    'calendar_widget' => (int) $data->calendar_widget,
                    'blf_widget' => (int) $data->blf_widget
                ];
                send_to_socket(json_encode($socketData, JSON_UNESCAPED_UNICODE));
            }
            echo json_encode($response);
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

            $config['ui']['sleep_wallpaper_enabled'] = intval($data->sleep_wallpaper_enabled);
            $message = "SET_SLEEP_WALLPAPER_ENABLED=" . intval($data->sleep_wallpaper_enabled);
            send_to_socket($message);

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

            $ntpdate = load_ntpdate();

                $ntpdate['UPDATE_HWCLOCK'] = $data->ntp_hwclock == 1 ? "yes" : "no";
                $message = "SET_HW_CLOCK_UPDATE=" . intval($data->ntp_hwclock);
                send_to_socket($message);

                if ($data->ntp_enable == 0) {
                    shell_exec("date -s " . escapeshellarg($data->datetime));
                    $message = "SET_SYSTEM_DATE=" . $data->datetime;
                    send_to_socket($message);
                    $ntpdate['NTPSERVERS'] = "";
                    send_to_socket("SET_NTP_ENABLED=0");
                } else {
                    $ntpdate['NTPSERVERS'] = $data->ntp_server;
                    send_to_socket("SET_NTP_SERVER=" . $data->ntp_server);
                    send_to_socket("SET_NTP_ENABLED=1");
                }

            save_ntpdate($ntpdate);

            $config = load_config();

            $config['ui']['time_widget'] = $data->time_widget;
            
            $config['ui']['calendar_widget'] = $data->calendar_widget;
            
            $config['ui']['blf_widget'] = $data->blf_widget;

            $config['ui']['backlight'] = $data->backlight;
            $message = "SET_BACKLIGHT=" . intval($data->backlight);
            send_to_socket($message);
        
            $config['ui']['sleep_wallpaper_path'] = $data->sleep_wallpaper_path;

            if (isset($data->screensaver_timeout) && $data->screensaver_timeout == 0) {
                $config['ui']['screensaver_timeout'] = 0;
                $message = "SET_SCREENSAVER_TIMEOUT=0";
                send_to_socket($message);

                $config['ui']['sleep_date_time'] = 0;
                $message = "SET_SLEEP_DATE_TIME=0";
                send_to_socket($message);

                $config['ui']['sleep_wallpaper_enabled'] = 0;
                $message = "SET_SLEEP_WALLPAPER_ENABLED=0";
                send_to_socket($message);

                $config['ui']['screen_saver_backlight'] = "50";
                $message = "SET_SCREEN_SAVER_BACKLIGHT=50";
                send_to_socket($message);

            } else {
                $config['ui']['screensaver_timeout'] = intval($data->screensaver_timeout);
                $message = "SET_SCREENSAVER_TIMEOUT=" . intval($data->screensaver_timeout);
                send_to_socket($message);

                $config['ui']['sleep_date_time'] = intval($data->sleep_date_time);
                $message = "SET_SLEEP_DATE_TIME=" . intval($data->sleep_date_time);
                send_to_socket($message);

                $config['ui']['sleep_wallpaper_enabled'] = intval($data->sleep_wallpaper_enabled);
                $message = "SET_SLEEP_WALLPAPER_ENABLED=" . intval($data->sleep_wallpaper_enabled);
                send_to_socket($message);

                $config['ui']['screen_saver_backlight'] = "50";
                $message = "SET_SCREEN_SAVER_BACKLIGHT=50";
                send_to_socket($message);
            }

           if ( save_config($config) === false )
               $response->success = 0;
           else 
               $response->success = 1;
            echo json_encode($response);

       }
    }
}
?>
