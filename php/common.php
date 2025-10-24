<?php
// чтобы ошибки не мешали JSON
error_reporting(E_ALL);
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
    return intval($config['ui']['handsfree_volume'] ?? 150);
}
function set_handsfree_volume($volume) {
    $config = load_config();
    if ($volume >= 0 && $volume <= 150) {
        $config['ui']['handsfree_volume'] = $volume;
        save_config($config);
    }
}
function get_handsfree_mic_volume() {
    $config = load_config();
    return intval($config['ui']['handsfree_mic_volume'] ?? 150);
}
function set_handsfree_mic_volume($volume) {
    $config = load_config();
    if ($volume >= 0 && $volume <= 150) {
        $config['ui']['handsfree_mic_volume'] = $volume;
        save_config($config);
    }
}
function get_phoneset_volume() {
    $config = load_config();
    return intval($config['ui']['phoneset_volume'] ?? 150);
}
function set_phoneset_volume($volume) {
    $config = load_config();
    if ($volume >= 0 && $volume <= 150) {
        $config['ui']['phoneset_volume'] = $volume;
        save_config($config);
    }
}
function get_phoneset_mic_volume() {
    $config = load_config();
    return intval($config['ui']['phoneset_mic_volume'] ?? 150);
}
function set_phoneset_mic_volume($volume) {
    $config = load_config();
    if ($volume >= 0 && $volume <= 150) {
        $config['ui']['phoneset_mic_volume'] = $volume;
        save_config($config);
    }
}
function get_headset_volume() {
    $config = load_config();
    return intval($config['ui']['headset_volume'] ?? 150);
}
function set_headset_volume($volume) {
    $config = load_config();
    if ($volume >= 0 && $volume <= 150) {
        $config['ui']['headset_volume'] = $volume;
        save_config($config);
    }
}
function get_headset_mic_volume() {
    $config = load_config();
    return intval($config['ui']['headset_mic_volume'] ?? 150);
}
function set_headset_mic_volume($volume) {
    $config = load_config();
    if ($volume >= 0 && $volume <= 150) {
        $config['ui']['headset_mic_volume'] = $volume;
        save_config($config);
    }
}
function get_ringtone_volume() {
    $config = load_config();
    return intval($config['ui']['ringtone_volume'] ?? 150);
}
function set_ringtone_volume($volume) {
    $config = load_config();
    if ($volume >= 0 && $volume <= 150) {
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

        if (isset($ntpdate['UPDATE_HWCLOCK'])) {
            $val = trim($ntpdate['UPDATE_HWCLOCK'], '"');
            $data->ntp_hwclock = ($val === "yes") ? 1 : 0;
        } else {
            $data->ntp_hwclock = 0;
        }

    $config = load_config();
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
                    if (!isset($config['ui'])) {
                        $config['ui'] = [];
                    }
                    $config['ui']['wallpaper'] = $filename;

                    if (save_config($config) !== false) {
                        $response->success = 1;

                        $message = json_encode([
                            'command' => 'set_wallpaper',
                            'path' => $filename
                        ]) . "\n";
                        send_to_socket($message);
                        touch("/opt/cumanphone/etc/config.conf");
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
        $config = load_config();
        $oldWallpaper = null;

        if (isset($config['ui']['wallpaper'])) {
            $oldWallpaper = $config['ui']['wallpaper'];
            unset($config['ui']['wallpaper']);
        }

        if (!isset($config['ui'])) {
            $config['ui'] = [];
        }

        if (save_config($config) !== false) {
            $response->success = 1;

            if ($oldWallpaper && file_exists($oldWallpaper)) {
                unlink($oldWallpaper);
            }

            $message = json_encode([
                'command' => 'reset_wallpaper'
            ]) . "\n";
            send_to_socket($message);

            touch("/opt/cumanphone/etc/config.conf");
        } else {
            $response->success = 0;
        }

        echo json_encode($response);
    }
    elseif ($data->command === "set-wallpaper-sleep") {
        $wallpaperPath = $data->sleep_wallpaper_path;
        $response->success = 0;

        if (file_exists($wallpaperPath)) {
            $config = load_config();
            if (!isset($config['ui'])) {
                $config['ui'] = [];
            }
            $config['ui']['sleep_wallpaper_path'] = $wallpaperPath;

            if (save_config($config) !== false) {
                $response->success = 1;

                send_to_socket("SET_SLEEP_WALLPAPER=" . $wallpaperPath . "\n");

                touch("/opt/cumanphone/etc/config.conf");
            }
        }
        echo json_encode($response);
    }
        elseif ($data->command === "save") {
            $response->success = 0;

            $config = load_config();

            // ====== Audio volumes ======
            $volumes = [
                'phone_sink' => 'SET_PHONE_PLAYBACK_VOLUME',
                'phone_source' => 'SET_PHONE_CAPTURE_VOLUME',
                'handsfree_sink' => 'SET_HANDSFREE_PLAYBACK_VOLUME',
                'handsfree_source' => 'SET_HANDSFREE_CAPTURE_VOLUME',
                'headset_sink' => 'SET_HEADSET_PLAYBACK_VOLUME',
                'headset_source' => 'SET_HEADSET_CAPTURE_VOLUME',
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
                    set_ringtone_volume($ringtone_volume);
                }
            }

            if (isset($data->backlight) && is_numeric($data->backlight)) {
                set_backlight($data->backlight);
                $config['ui']['backlight'] = $data->backlight;
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

            $config['ui']['time_widget'] = $data->time_widget;
            $config['ui']['calendar_widget'] = $data->calendar_widget;
            $config['ui']['blf_widget'] = $data->blf_widget;

            $socketData = [
                'type' => 'widget',
                'event' => 'widget_settings_updated',
                'time_widget' => $config['ui']['time_widget'],
                'calendar_widget' => $config['ui']['calendar_widget'],
                'blf_widget' => $config['ui']['blf_widget']
            ];

            send_to_socket($socketData);
            
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

            $config['ui']['screensaver_timeout'] = $screensaver_timeout;
            $config['ui']['sleep_wallpaper_enabled'] = $sleep_wallpaper_enabled;
            $config['ui']['sleep_date_time'] = $sleep_date_time;
            $config['ui']['screen_saver_backlight'] = $screen_saver_backlight;

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
