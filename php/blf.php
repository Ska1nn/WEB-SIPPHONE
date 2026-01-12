<?php

require __DIR__ . '/config.php';

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

function log_message(string $message): void {
    file_put_contents('app.log', date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
}

function getSipDomain(string $account, array $config): ?string {
    if (preg_match('/@([0-9a-zA-Z\.\-]+)/', $account, $matches)) {
        return $matches[1];
    }

    return $config['auth_info_0']['domain'] ?? null;
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
    $config = load_config();

    if (isset($data['command']) && $data['command'] === 'save') {

        if (!isset($data['key'], $data['type'], $data['account'], $data['name'], $data['number'])) {
            $response->success = 0;
            $response->error = 'Missing required fields';
        } else {

            $blf = load_blf();

            $sipDomain = getSipDomain($data['account'], $config);

            if (!$sipDomain) {
                $response->success = 0;
                $response->error = 'Cannot determine SIP server domain';
                echo json_encode($response);
                exit;
            }

            $address = 'sip:' . $data['number'] . '@' . $sipDomain;
            $hex_name = unicode_to_hex($data['name']);

            $blf['BLF' . $data['key']] = [
                'account' => $data['account'],
                'address' => $address,
                'name'    => $hex_name,
                'number'  => $data['number'],
                'type'    => $data['type']
            ];

            $success = save_blf($blf);
            $response->success = $success ? 1 : 0;

            if ($success) {
                $updatedBLF = load_blf();
                foreach ($updatedBLF as $key => $entry) {
                    if (isset($entry['name'])) {
                        $updatedBLF[$key]['name'] = hex_to_unicode($entry['name']);
                    }
                }
                $response->blf = $updatedBLF;
            }
        }

    } elseif ($data['command'] === 'reset') {

        if (!isset($data['key'])) {
            $response->success = 0;
            $response->error = 'No key provided';
        } else {
            $blf = load_blf();
            $key = 'BLF' . $data['key'];

            if (isset($blf[$key])) {
                unset($blf[$key]);
                save_blf($blf);
                $response->success = 1;
            } else {
                $response->success = 0;
                $response->error = 'Key not found';
            }
        }
    }

    echo json_encode($response);
}

?>
