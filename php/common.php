<?php
// чтобы ошибки не мешали JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);

require __DIR__ . '/config.php';

function get_backlight() {
    $output = shell_exec("cat /sys/class/backlight/lvds_backlight/brightness");
    $backlight = trim($output);
    if (!is_numeric($backlight)) $backlight = 100;
    $value = 100 - $backlight;
    if ($value < 30) $value = 30;
    return $value;
}
function set_backlight($value) {
    if ($value < 30) $value = 30;
    $backlight = 100 - $value;
    shell_exec("echo {$backlight} > /sys/class/backlight/lvds_backlight/brightness");
}
// ======== socket ========
function send_text_to_socket($message) {
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

    $payload = $message . "\n";

    $bytesWritten = fwrite($socket, $payload);
    fflush($socket);
    fclose($socket);

    if ($bytesWritten === false || $bytesWritten !== strlen($payload)) {
        error_log("Failed to write full message to socket");
        return false;
    }

    return true;
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


// ======== HTTP handling ========
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = new stdClass();
    $data->photos = load_images();
    $data->backlight = get_backlight();
    $data->datetime = trim(shell_exec('date "+%FT%H:%M:%S"'));
    $tz = trim(shell_exec('date +%Z'));
    $data->timezone = (str_starts_with($tz, '-') || str_starts_with($tz, '+')) ? "UTC".$tz : $tz;

    $ntpdate = load_ntpdate();
    if ( isset($ntpdate['NTPSERVERS']) )
            $data->ntp_server = $ntpdate['NTPSERVERS'];
        else   
            $data->ntp_server = null;

        if (isset($ntpdate['UPDATE_HWCLOCK'])) {
            $val = trim($ntpdate['UPDATE_HWCLOCK'], '"');
            $data->ntp_hwclock = ($val === "yes") ? 1 : 0;
        } else {
            $data->ntp_hwclock = 0;
        }

    $config = load_config();

    // Текущие значения громкостей
    $data->handsfree_playback_volume = $config['sound']['handsfree_playback_volume'] ?? 0;
    $data->handsfree_capture_volume  = $config['sound']['handsfree_capture_volume'] ?? 0;
    $data->headset_playback_volume   = $config['sound']['headset_playback_volume'] ?? 0;
    $data->headset_capture_volume    = $config['sound']['headset_capture_volume'] ?? 0;
    $data->phone_playback_volume     = $config['sound']['phone_playback_volume'] ?? 0;
    $data->phone_capture_volume      = $config['sound']['phone_capture_volume'] ?? 0;
    $data->ringtone_volume           = $config['sound']['ringtone_volume'] ?? 0;

    // Максимальные значения громкостей
    $data->handsfree_max_volume = $config['sound']['handsfree_max_volume'] ?? 100;
    $data->headset_max_volume   = $config['sound']['headset_max_volume'] ?? 100;
    $data->phone_max_volume     = $config['sound']['phone_max_volume'] ?? 100;

    if (isset($config['ui']['time_widget']))
        $data->time_widget = $config['ui']['time_widget'];
    if (isset($config['ui']['calendar_widget']))
        $data->calendar_widget = $config['ui']['calendar_widget'];
    if (isset($config['ui']['blf_widget']))
        $data->blf_widget = $config['ui']['blf_widget'];

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
        if (preg_match('/^url\("data:image\/(\w+);base64,/', $content, $type)) {
            $content = substr($content, strpos($content, ',') + 1);
            $type = strtolower($type[1]);
            if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png'])) {
                throw new \Exception('Invalid image type');
            }
            $content = str_replace(' ', '+', $content);
            $content = base64_decode($content);
            $response->success = 0;

            if ($content !== false) {
                $filename = "/opt/cumanphone/share/images/wallpaper.{$type}";
                if (file_put_contents($filename, $content) !== false) {
                    $config = load_config();

                    if (save_config($config) !== false) {
                        $response->success = 1;
                        $message = [
                            'command' => 'set_wallpaper',
                            'path' => $filename
                        ];
                        send_to_socket($message);
                    }
                }
            }
            echo json_encode($response);
        } else {
            $response->success = 0;
            echo json_encode($response);
        }
    }
elseif ($data->command === "reset-wallpaper") {
    $response = new stdClass();
    $response->success = 1;

    $message = [
        'command' => 'reset_wallpaper'
    ];
    send_to_socket($message);

    if (isset($data->old_wallpaper) && file_exists($data->old_wallpaper)) {
        unlink($data->old_wallpaper);
    }

    echo json_encode($response);
}
elseif ($data->command === "set-wallpaper-sleep") {
    $wallpaperPath = $data->sleep_wallpaper_path;
    $response = new stdClass();
    $response->success = 0;

    if (file_exists($wallpaperPath)) {
        $message = [
            'command' => 'set_sleep_wallpaper',
            'path' => $wallpaperPath
        ];
        
        send_to_socket($message);

        $response->success = 1;
    }

    echo json_encode($response);
}

        elseif ($data->command === "save") {
            $response->success = 0;

            $config = load_config();

            // ====== Audio volumes ======
            $volumes = [
                'phone_playback_volume' => 'SET_PHONE_PLAYBACK_VOLUME',
                'phone_capture_volume' => 'SET_PHONE_CAPTURE_VOLUME',
                'handsfree_playback_volume' => 'SET_HANDSFREE_PLAYBACK_VOLUME',
                'handsfree_capture_volume' => 'SET_HANDSFREE_CAPTURE_VOLUME',
                'headset_playback_volume' => 'SET_HEADSET_PLAYBACK_VOLUME',
                'headset_capture_volume' => 'SET_HEADSET_CAPTURE_VOLUME',
            ];

            foreach ($volumes as $key => $cmd) {
                if (isset($data->{$key})) {
                    send_text_to_socket("{$cmd}={$data->{$key}}");
                }
            }

            if (isset($data->ringtone_volume) && is_numeric($data->ringtone_volume)) {
                $ringtone_volume = intval($data->ringtone_volume);
                if ($ringtone_volume >= 0 && $ringtone_volume <= 100) {
                    send_text_to_socket("SET_RINGTONE_PLAYBACK_VOLUME={$ringtone_volume}");
                }
            }

            if (isset($data->backlight) && is_numeric($data->backlight)) {
                set_backlight($data->backlight);
                send_text_to_socket("SET_BACKLIGHT=" . intval($data->backlight));
            }

            // ====== Timezone ======
            if (str_starts_with($data->timezone, "UTC")) {
                $path = "/usr/share/zoneinfo/Etc/";
                if (strlen($data->timezone) > 3) {
                    $sign = substr($data->timezone, 3, 1);
                    $offset = substr($data->timezone, 4);
                    if (str_starts_with($offset, "0")) $offset = substr($offset, -1);
                    $path .= ($sign == "-" ? "GMT+" : "GMT-") . $offset;
                } else {
                    $path .= "UTC";
                }
                shell_exec("ln -f -s $path /etc/localtime");
            }

            $ntpdate = load_ntpdate();

            $ntpdate['UPDATE_HWCLOCK'] = ($data->ntp_hwclock == 1) ? '"yes"' : '"no"';
            send_text_to_socket("SET_HW_CLOCK_UPDATE=" . intval($data->ntp_hwclock));

            if (isset($data->ntp_hwclock) && $data->ntp_hwclock == 0) {
                if (!empty($data->datetime)) {
                    $datetimeIso = date('Y-n-j\TH:i', strtotime($data->datetime));
                    shell_exec("date -s " . escapeshellarg(str_replace('T', ' ', $datetimeIso)));
                    send_text_to_socket($datetimeIso);
                }

                $ntpdate['NTPSERVERS'] = '""';
                send_text_to_socket("SET_NTP_ENABLED=0");

            } elseif (isset($data->ntp_hwclock) && $data->ntp_hwclock == 1) {
                if (isset($data->ntp_server) && $data->ntp_server !== "") {
                    $ntpdate['NTPSERVERS'] = '"' . $data->ntp_server . '"';
                    send_text_to_socket("SET_NTP_SERVER=" . $data->ntp_server);
                } else {
                    $ntpdate['NTPSERVERS'] = '""';
                }

                send_text_to_socket("SET_NTP_ENABLED=1");
            }

            save_ntpdate($ntpdate);

            $message = [
                'type' => 'widget',
                'event' => 'widget_settings_updated',
                'time_widget' => $data->time_widget,
                'calendar_widget' => $data->calendar_widget,
                'blf_widget' => $data->blf_widget
            ];

            send_to_socket($message);
            
            $screensaver_timeout = intval($data->screensaver_timeout ?? 0);

            if ($screensaver_timeout === 0) {
                $sleep_wallpaper_enabled = 0;
                $sleep_date_time = 0;
                $screen_saver_backlight = 50;
            } else {
                $sleep_wallpaper_enabled = intval($data->sleep_wallpaper_enabled ?? 0);
                $sleep_date_time = intval($data->sleep_date_time ?? 0);
                $screen_saver_backlight = 50;
            }

            send_to_socket("SET_SCREENSAVER_TIMEOUT={$screensaver_timeout}");
            send_to_socket("SET_SLEEP_WALLPAPER_ENABLED={$sleep_wallpaper_enabled}");
            send_to_socket("SET_SLEEP_DATE_TIME={$sleep_date_time}");
            send_to_socket("SET_SCREEN_SAVER_BACKLIGHT={$screen_saver_backlight}");

            $response->success = save_config($config) !== false ? 1 : 0;
            $response->message = "Конфигурация сохранена";

            echo json_encode($response, JSON_UNESCAPED_UNICODE);
        }
    }
}
?>
