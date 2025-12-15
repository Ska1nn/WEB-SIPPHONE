<?php
require __DIR__ . '/config.php';

function mask2cidr($mask) {
    $long = ip2long($mask);
    $base = ip2long('255.255.255.255');
    return 32 - log(($long ^ $base) + 1, 2);
}

/* =======================
   GET: ЧТЕНИЕ ЛОГОВ
======================= */
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

/* =======================
   GET: ДИАГНОСТИКА
======================= */
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
            $ping = shell_exec("ping -c 4 10.10.2.4 2>&1");
            echo (strpos($ping, '0% packet loss') !== false)
                ? "Соединение с SIP-сервером установлено"
                : "Не удалось подключиться к SIP-серверу";
            break;
    }
    exit;
}

/* =======================
   GET: NETWORK CONFIG
======================= */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(load_networkd());
    exit;
}

/* =======================
   POST: SAVE NETWORK
======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $data = json_decode(file_get_contents('php://input'));
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
