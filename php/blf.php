<?php

require __DIR__ . '/config.php';

function unicode_to_hex($str) {
    $hex_str = '';
    $len = mb_strlen($str, 'UTF-8');
    for ($i = 0; $i < $len; $i++) {
        $char = mb_substr($str, $i, 1, 'UTF-8');
        $hex_str .= '\\x' . strtoupper(bin2hex(iconv('UTF-8', 'UCS-2BE', $char)));
    }
    return $hex_str;
}

function hex_to_unicode($str) {
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

function log_message($message) {
    file_put_contents('app.log', date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = new stdClass();
    $accounts = [];
    $config = load_config();

    foreach ($config as $key => $value) {
        if (strpos($key, 'proxy_') === 0 && isset($value['reg_identity'])) {
            preg_match('/<([^>]+)>/', $value['reg_identity'], $matches);
            if (isset($matches[1])) {
                $accounts[] = $matches[1];
            }
        }
    }
    $data->accounts = $accounts;

    $blf = load_blf();
    foreach ($blf as $key => $entry) {
        if (isset($entry['name'])) {
            $blf[$key]['name'] = hex_to_unicode($entry['name']);
        }
    }
    $data->blf = $blf;

    echo json_encode($data, JSON_UNESCAPED_UNICODE);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = new stdClass();
    $contents = file_get_contents('php://input');
    $data = json_decode($contents, true);

    if (isset($data['command']) && $data['command'] === 'save') {
        if (!isset($data['key'], $data['type'], $data['account'], $data['name'], $data['number'])) {
            $response->success = 0;
            $response->error = 'Missing required fields';
        } else {
            $blf = load_blf();
            
            $address = 'sip:' . $data['number'] . '@' . '10.10.2.4';

            $hex_name = unicode_to_hex($data['name']);

            if ($data['enable'] == 0) {
                unset($blf['BLF' . $data['key']]);
            } else {
                $blf['BLF' . $data['key']] = [
                    'account' => $data['account'],
                    'address' => $address,
                    'name' => $hex_name,
                    'number' => $data['number'],
                    'type' => $data['type']
                ];
            }

            $response->success = save_blf($blf) ? 1 : 0;
        }
        echo json_encode($response);
    }
}

?>
