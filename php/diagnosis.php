<?php
require __DIR__ . '/config.php';

function mask2cidr($mask) {
    $long = ip2long($mask);
    $base = ip2long('255.255.255.255');
    return 32 - log(($long ^ $base) + 1, 2);
}

function getSipServers(array $config): array {
    $servers = [];
    foreach ($config as $key => $section) {
        if (strpos($key, 'proxy_') === 0) {
            if (!empty($section['server_active_uri'])) {
                if (preg_match('/sip:([^;]+)/', $section['server_active_uri'], $m)) {
                    $servers[] = $m[1];
                }
            }
            if (!empty($section['reg_proxy'])) {
                if (preg_match('/sip:([^;>]+)/', $section['reg_proxy'], $m)) {
                    $servers[] = $m[1];
                }
            }
        }
    }
    return array_values(array_unique($servers));
}

// === ФУНКЦИИ ДЛЯ РАБОТЫ С ЮНИКОДОМ И СОКЕТОМ ===
function unicode_to_hex(string $str): string {
    $hex_str = '';
    $len = mb_strlen($str, 'UTF-8');
    for ($i = 0; $i < $len; $i++) {
        $char = mb_substr($str, $i, 1, 'UTF-8');
        $hex_str .= '\\x' . strtoupper(bin2hex(iconv('UTF-8', 'UCS-2BE', $char)));
    }
    return $hex_str;
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

function log_message(string $message): void {
    file_put_contents('app.log', date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
}

function getSipDomain(string $account, array $config): ?string {
    if (preg_match('/@([0-9a-zA-Z\.\-]+)/', $account, $matches)) {
        return $matches[1];
    }
    return $config['auth_info_0']['domain'] ?? null;
}

// === GET: ЧТЕНИЕ ЛОГ-ФАЙЛОВ ===
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['logfile'])) {
    $logfile = $_GET['logfile'];
    $allowed = [
        '/var/log/syslog',
        '/var/log/messages',
        '/var/log/kern.log',
        '/opt/cumanphone/var/log/cumanphone1.log',
        '/opt/cumanphone/var/log/cumanphone2.log'
    ];
    if (!in_array($logfile, $allowed, true)) {
        http_response_code(403);
        echo "Ошибка: доступ запрещен";
        exit;
    }
    $output = shell_exec("tail -n 500 " . escapeshellarg($logfile));
    echo $output ?: "Файл пуст или не читается";
    exit;
}

// === GET: ДИАГНОСТИКА (ping, traceroute, speedtest, checksip) ===
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $action   = $_GET['action'];
    $language = $_GET['language'] ?? 'ru';

    switch ($action) {
        case 'ping':
            echo shell_exec("ping -c 4 " . escapeshellarg($_GET['address']));
            break;
        case 'traceroute':
            echo shell_exec("traceroute " . escapeshellarg($_GET['address']));
            break;
        case 'speedtest':
            $address = escapeshellarg($_GET['address']);
            $ping = shell_exec("ping -c 4 $address 2>&1");
            if (strpos($ping, '0% packet loss') !== false) {
                echo shell_exec("iperf3 -c $address");
            } else {
                echo "Сервер недоступен";
            }
            break;
        case 'checksip':
            $config = load_config();
            $sipServers = getSipServers($config);
            if (empty($sipServers)) {
                echo "SIP-серверы не найдены в конфигурации";
                break;
            }
            $result = [];
            foreach ($sipServers as $server) {
                $ping = shell_exec("ping -c 2 " . escapeshellarg($server) . " 2>&1");
                $ok = strpos($ping, '0% packet loss') !== false;
                $result[] = ['server' => $server, 'status' => $ok ? 'ok' : 'fail'];
                if ($ok) {
                    echo "Соединение с SIP-сервером установлено ($server)";
                    break;
                }
            }
            if (!isset($ok) || !$ok) {
                echo "Не удалось подключиться ни к одному SIP-серверу";
            }
            break;
    }
    exit;
}

// === GET: СЕТЕВАЯ КОНФИГУРАЦИЯ ===
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(load_networkd());
    exit;
}

// === POST: ОБРАБОТКА КОМАНД ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'));
    $response = new stdClass();

    // --- Сохранение сети ---
    if (!isset($data->command)) {
        $ini  = "[Match]\nName=lan0\n\n[Network]\n";
        if (!empty($data->dhcp) && $data->dhcp === "true") {
            $ini .= "DHCP=yes\n";
        } else {
            if (!empty($data->address)) {
                $ini .= "Address={$data->address}/" . mask2cidr($data->netmask) . "\n";
            }
            if (!empty($data->gateway)) $ini .= "Gateway={$data->gateway}\n";
            if (!empty($data->dns1))    $ini .= "DNS={$data->dns1}\n";
            if (!empty($data->dns2))    $ini .= "DNS={$data->dns2}\n";
        }
        file_put_contents('/etc/systemd/network/80-lan0.network', $ini);
        exec("/bin/systemctl restart systemd-networkd", $o, $code);
        echo json_encode(['success' => $code === 0 ? 1 : 0]);
        exit;
    }

    // --- Команда: save (BLF) ---
    if ($data->command == "save") {
        $subType = $data->subType ?? 0;
        $message = [
            "page" => "blf",
            "command" => "save",
            "key" => $data->key,
            "type" => (int)$data->type,
            "subType" => (int)$subType,
            "name" => $data->name ?? ''
        ];
        if ($data->type == 0 || ($data->type == 1 && $subType == 0)) {
            $message["account"] = $data->account;
            $message["number"] = $data->number ?? '';
        } elseif ($data->type == 1 && $subType == 1) {
            if (!empty($data->number) && is_string($data->number)) {
                $message["ip"] = $data->number;
            } elseif (!empty($data->ip)) {
                $message["ip"] = $data->ip;
            } else {
                $response->success = 0;
                $response->message = "IP-адрес не указан";
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
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

    // --- Команда: reset (BLF) ---
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
        exit;
    }

    // === НОВАЯ КОМАНДА: clear (очистка логов) ===
    elseif ($data->command == "clear") {
        $fileMap = [
            'cumanphone' => '/opt/cumanphone/var/log/cumanphone1.log',
            'syslog'     => '/var/log/syslog',
            'kern'       => '/var/log/kern.log'
            // 🟡 web-logs НЕ включён — он обрабатывается только на клиенте
        ];
        
        $targetFile = $data->file ?? '';
        
        // 🟢 Если web-logs — возвращаем успех, но НЕ отправляем в сокет
        if ($targetFile === 'web-logs') {
            $response->success = 1;
            $response->message = "Логи веб-интерфейса очищены локально";
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $filePath = $fileMap[$targetFile] ?? null;
        
        // Если файл не найден в мапе — ошибка
        if (!$filePath) {
            $response->success = 0;
            $response->message = "Неизвестный тип лога: $targetFile";
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $message = [
            "page" => "diagnostics",
            "command" => "clear",
            "file" => $targetFile,
            "path" => $filePath,
            "timestamp" => date('c')
        ];

        if (send_to_socket($message)) {
            $response->success = 1;
            $response->message = "Запрос на очистку лога отправлен в сокет";
        } else {
            $response->success = 0;
            $response->message = "Ошибка отправки запроса в сокет";
        }
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- Неизвестная команда ---
    $response->success = 0;
    $response->message = "Неизвестная команда: " . ($data->command ?? 'none');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}
?>