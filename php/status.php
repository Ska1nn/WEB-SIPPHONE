<?php
require __DIR__ . '/config.php';

while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$data = new stdClass();

/**
 * Получение информации по сетевому интерфейсу
 */
function getInterfaceInfo(string $iface): ?array
{
    $output = shell_exec("ifconfig " . escapeshellarg($iface) . " 2>/dev/null");
    if (!$output) {
        return null;
    }

    $info = [
        'iface'   => $iface,
        'running' => (bool)preg_match('/RUNNING/', $output),
        'ip'      => null,
        'netmask' => null,
        'mac'     => null,
    ];

    if (preg_match('/inet ([0-9.]+)/', $output, $m)) {
        $info['ip'] = $m[1];
    }

    if (preg_match('/netmask ([0-9.]+)/', $output, $m)) {
        $info['netmask'] = $m[1];
    }

    if (preg_match('/ether ([0-9A-Fa-f:]+)/', $output, $m)) {
        $info['mac'] = $m[1];
    }

    return $info;
}

// --- СЕТЕВЫЕ ДАННЫЕ ---
$config = load_config();

$baseIface = 'lan0';
$interfacesToCheck = [];

// VLAN включён?
if (
    !empty($config['net']['vlan_internet_port_enabled']) && // 0 → false, 1 → true
    isset($config['net']['vlan_internet_port_vid_number'])
) {
    $vid = (int)$config['net']['vlan_internet_port_vid_number'];
    if ($vid > 0) {
        $interfacesToCheck[] = $baseIface . '.' . $vid;
    }
}

// fallback — обычный интерфейс
$interfacesToCheck[] = $baseIface;

$ifaceInfo = null;

foreach ($interfacesToCheck as $iface) {
    $info = getInterfaceInfo($iface);
    if ($info && !empty($info['ip'])) {
        $ifaceInfo = $info;
        break;
    }
}

if ($ifaceInfo) {
    $data->interface = $ifaceInfo['iface'];
    $data->running   = $ifaceInfo['running'];
    $data->ip        = $ifaceInfo['ip'];
    $data->netmask   = $ifaceInfo['netmask'];
    $data->mac       = $ifaceInfo['mac'];
} else {
    // Явно указываем отсутствие сети
    $data->interface = null;
    $data->running   = false;
}

// --- МОДЕЛЬ ---
if (isset($config['version']['model'])) {
    $data->model = $config['version']['model'];
}

// --- ВЕРСИЯ CumanPhone ---
$ver = shell_exec("/opt/cumanphone/bin/CumanPhone --version 2>/dev/null");
if ($ver && strpos($ver, 'CumanPhone version: ') !== false) {
    $data->version = trim(substr($ver, 20));
}

// --- SIP АККАУНТЫ ---
$sip_accounts = [];

$conf = @file_get_contents('/opt/cumanphone/etc/config.conf');
if ($conf && preg_match_all(
    '/reg_identity="([^"]*)"[^<]*<sip:([a-zA-Z0-9+._-]+)@/i',
    $conf,
    $matches,
    PREG_SET_ORDER
)) {
    foreach ($matches as $m) {
        $sip_accounts[$m[2]] = [
            'id'     => $m[2],
            'name'   => $m[1],
            'status' => 'unknown',
        ];
    }
}

$sip_json = @file_get_contents('/opt/cumanphone/var/log/sipdata.json');
if ($sip_json) {
    $sip_status_data = json_decode($sip_json, true);

    if (!empty($sip_status_data['sip_accounts_status_info'])) {
        foreach ($sip_status_data['sip_accounts_status_info'] as $value) {
            if (preg_match('/(\d+):(OK|NOK)/i', $value, $m)) {
                $id     = $m[1];
                $status = strtoupper($m[2]) === 'OK' ? 'online' : 'offline';

                if (isset($sip_accounts[$id])) {
                    $sip_accounts[$id]['status'] = $status;
                } else {
                    $sip_accounts[$id] = [
                        'id'     => $id,
                        'name'   => $id,
                        'status' => $status
                    ];
                }
            }
        }
    }
}

$data->sip_accounts = array_values($sip_accounts);

// --- OUTPUT ---
echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
