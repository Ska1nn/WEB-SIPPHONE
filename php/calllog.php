<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

$callLogDbPath = '/.local/share/CumanPhone/linphone.db';
$contactsDbPath = '/.local/share/CumanPhone/friends.db';

function extractShortNumber($sip) {
    if (!$sip) return null;
    if (strpos($sip, 'sip:') === 0) $sip = substr($sip, 4);
    $parts = explode('@', $sip);
    return $parts[0];
}

function findContactName($sipUri, $contactsPdo) {
    if (!$sipUri || !is_string($sipUri)) return null;
    if (strpos($sipUri, 'sip:') === 0) $sipUri = substr($sipUri, 4);
    $number = explode('@', $sipUri)[0];

    $stmt = $contactsPdo->prepare("SELECT vCard FROM friends WHERE vCard LIKE ?");
    $stmt->execute(["%$number%"]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!empty($result['vCard'])) {
        preg_match('/FN:(.+)/', $result['vCard'], $matches);
        return $matches[1] ?? trim($result['vCard'], "\"");
    }

    return null;
}

try {
    if (!file_exists($callLogDbPath)) {
        throw new Exception("Файл базы звонков не найден.");
    }

    $pdo = new PDO("sqlite:$callLogDbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (isset($_GET['action']) && $_GET['action'] === 'clear') {
        $tablesToClear = ['conference_call', 'history'];
        foreach ($tablesToClear as $table) {
            $pdo->exec("DELETE FROM $table");
        }
        $pdo->exec("VACUUM");
        echo json_encode(['success' => true, 'message' => 'История звонков успешно очищена.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!file_exists($contactsDbPath)) {
        throw new Exception("Файл базы контактов не найден.");
    }

    $contactsPdo = new PDO("sqlite:$contactsDbPath");
    $contactsPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $allowedFilters = ['incoming', 'outgoing', 'missed', 'all'];
    $filter = isset($_GET['filter']) && in_array($_GET['filter'], $allowedFilters) ? $_GET['filter'] : 'all';

    $sql = "
        SELECT cc.id, cc.from_sip_address_id, cc.to_sip_address_id, cc.direction, cc.duration, 
               cc.start_time, cc.connected_time, cc.status, cc.video_enabled, cc.quality, 
               cc.call_id, cc.refkey, cc.conference_info_id,
               from_sip.value AS from_sip_value,
               to_sip.value AS to_sip_value,
               from_sip.display_name AS from_display_name,
               to_sip.display_name AS to_display_name
        FROM conference_call cc
        LEFT JOIN sip_address from_sip ON cc.from_sip_address_id = from_sip.id
        LEFT JOIN sip_address to_sip ON cc.to_sip_address_id = to_sip.id
    ";

    switch ($filter) {
        case 'incoming':
            $sql .= " WHERE cc.direction = 1";
            break;
        case 'outgoing':
            $sql .= " WHERE cc.direction = 0";
            break;
        case 'missed':
            $sql .= " WHERE cc.direction = 1 AND cc.duration = 0 AND cc.connected_time IS NULL";
            break;
    }

    $sql .= " ORDER BY cc.start_time DESC";

    $stmt = $pdo->query($sql);
    $calls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($calls as &$call) {
        $fromName = findContactName($call['from_sip_value'], $contactsPdo);
        $fromShort = extractShortNumber($call['from_sip_value']);
        $call['from_display_name'] = $fromName ?: $fromShort;

        $toName = findContactName($call['to_sip_value'], $contactsPdo);
        $toShort = extractShortNumber($call['to_sip_value']);
        $call['to_display_name'] = $toName ?: $toShort;

        $call['short_number'] = $call['direction'] == 0 ? $toShort : $fromShort;

        $call['call_status'] = ($call['duration'] == 0 && is_null($call['connected_time'])) ? 'missed' : 'answered';
    }

    date_default_timezone_set('UTC');
    foreach ($calls as &$call) {
        if (!empty($call['start_time'])) {
            $timestamp = strtotime($call['start_time']) + (5 * 3600);
            $call['start_date_local'] = date('d.m.Y', $timestamp);
            $call['start_time_local'] = date('H:i', $timestamp);
        }
        if (!empty($call['connected_time'])) {
            $call['connected_time_local'] = date('d-m-Y H:i:s', strtotime($call['connected_time']) + (5 * 3600));
        }
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($calls, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Ошибка: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>
