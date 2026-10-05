<?php

require __DIR__ . '/config.php';

function send_to_socket($message) {
    $socketPath = '/tmp/qt_wayland_ipc.socket';

    if (!file_exists($socketPath)) {
        error_log("Socket file not found: $socketPath");
        return false;
    }

    $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
    if ($socket === false) {
        error_log("Socket creation failed: " . socket_strerror(socket_last_error()));
        return false;
    }

    if (!socket_connect($socket, $socketPath)) {
        error_log("Socket connection failed: " . socket_strerror(socket_last_error($socket)));
        socket_close($socket);
        return false;
    }

    $json = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        error_log("Failed to encode message to JSON: " . json_last_error_msg());
        socket_close($socket);
        return false;
    }

    $payload = $json . "\n";
    $bytes = socket_write($socket, $payload, strlen($payload));

    socket_close($socket);

    if ($bytes === false || $bytes !== strlen($payload)) {
        error_log("Failed to write full message to socket");
        return false;
    }

    return true;
}

function find_proxy_section($config, $accountId) {
    $auth = 'auth_info_' . $accountId;
    $username = $config[$auth]['username'] ?? null;
    $domain   = $config[$auth]['domain'] ?? null;

    if ($username === null) {
        return 'proxy_' . $accountId;
    }

    foreach ($config as $key => $value) {
        if (strpos($key, 'proxy_') !== 0) continue;

        $regIdentity = $value['reg_identity'] ?? '';

        if (preg_match('/<sip:([^@]+)@([^;>]+)/', $regIdentity, $m)) {
            $regUser = $m[1];
            $regHost = $m[2];

            if ($regUser === (string)$username) {
                if ($domain !== null && $regHost === (string)$domain) {
                    return $key;
                }
            }
        }
    }

    foreach ($config as $key => $value) {
        if (strpos($key, 'proxy_') !== 0) continue;

        $regIdentity = $value['reg_identity'] ?? '';

        if (preg_match('/<sip:([^@]+)@/', $regIdentity, $m)) {
            if ($m[1] === (string)$username) {
                return $key;
            }
        }
    }

    return 'proxy_' . $accountId;
}
function load($data) {
    $response = new stdClass();
    $config = load_config();

    if (isset($config['version']['model'])) {
        $response->model = $config['version']['model'];
    }

    if (!isset($data->{'account'})) {
        $config = load_config();
        $accounts = [];

        foreach ($config as $key => $value) {
            if (strpos($key, 'auth_info_') === 0) {
                $accountId = substr($key, strlen('auth_info_'));
                $accounts[] = [
                    'account' => $accountId,
                    'name' => "Аккаунт " . $accountId
                ];
            }
        }

        $response->accounts = $accounts;
        return $response;
    }

    if (isset($data->{'account'})) {
        $config = load_config();
        $auth = 'auth_info_' . $data->{'account'};

        if (isset($config[$auth])) {
            $response->auth = $config[$auth];
        }

        $proxy = find_proxy_section($config, $data->{'account'});

        if (!isset($config[$proxy])) {
            return $response;
        }

        if (isset($config[$proxy]['reg_proxy'])) {
            $response->reg_proxy = $config[$proxy]['reg_proxy'];

            $regProxy = trim($config[$proxy]['reg_proxy'], '<>');

            if (preg_match('/^sip:(?:[^@]+@)?([^;>]+)/', $regProxy, $matches)) {
                $hostWithPort = $matches[1];

                if (!isset($response->auth) || !is_array($response->auth)) {
                    $response->auth = [];
                }

                $response->auth['domain'] = $hostWithPort;
            }
        }

        if (isset($config[$proxy]['server_backup'])) {
            $server = $config[$proxy]['server_backup'];
            if (preg_match('/sip:([^;>]+)/', $server, $matches)) {
                $response->backup_server = $matches[1];
            } else {
                $response->backup_server = $server;
            }
        }

        if (isset($config[$proxy]['reg_identity'])) {
            $response->reg_identity = $config[$proxy]['reg_identity'];

            if (preg_match('/"([^"]+)"/', $response->reg_identity, $matches)) {
                $name = $matches[1];

                if (isset($response->auth) && is_array($response->auth)) {
                    $response->auth['name'] = $name;
                } else {
                    $response->auth = ['name' => $name];
                }
            }
        }

        if (isset($config[$proxy]['reg_sendregister'])) {
            $response->reg_sendregister = ((string)$config[$proxy]['reg_sendregister'] === '1') ? '1' : '0';
        }

        if (isset($config[$proxy]['x-custom-property:rtp_ports']))
            $response->rtp_ports = $config[$proxy]['x-custom-property:rtp_ports'];

        if (isset($config[$proxy]['x-custom-property:backup_server']))
            $response->backup_server = $config[$proxy]['x-custom-property:backup_server'];

        if (isset($config[$proxy]['x-custom-property:dtmf']))
            $response->dtmf = $config[$proxy]['x-custom-property:dtmf'];

        if (isset($config[$proxy]['x-custom-property:codecs']))
            $response->audiocodecs = $config[$proxy]['x-custom-property:codecs'];

        if (isset($config[$proxy]['x-custom-property:video_codecs']))
            $response->videocodecs = $config[$proxy]['x-custom-property:video_codecs'];

        if (isset($config[$proxy]['x-custom-property:encryptionType']))
            $response->encryptionType = $config[$proxy]['x-custom-property:encryptionType'];

        if (isset($config[$proxy]['x-custom-property:srtp']))
            $response->srtp_type = $config[$proxy]['x-custom-property:srtp'];

        $response->proxy_section = $proxy;
    }

    return $response;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = new stdClass();
    $contents = file_get_contents('php://input');
    $data = json_decode($contents);

    if (isset($data->command)) {

        if ($data->command === 'load') {
            print_r(json_encode(load($data)));
        }

        elseif ($data->command === 'save') {
            $response->success = 1;
            $response->message = 'Изменения отправлены через сокет (без сохранения).';

            $config = load_config();
            $proxySection = find_proxy_section($config, $data->account);

            $message = [
                'type' => 'sip',
                'event' => 'sip_config_updated',
                'account' => $data->account,
                'proxy_section' => $proxySection,
                'config' => [
                    'username'          => $data->username ?? "",
                    'domain'            => $data->domain ?? "",
                    'transport'         => $data->transport ?? "",
                    'backup_server'     => $data->backup_server ?? "",
                    'codecs'            => $data->audiocodecs ?? "",
                    'video_codecs'      => $data->videocodecs ?? "",
                    'srtp_type'         => $data->srtp_type ?? "",
                    'dtmf'              => $data->dtmf ?? "",
                    'rtp_ports'         => $data->rtp_ports ?? "",
                    'password'          => $data->passwd ?? "",
                    'displayName'       => $data->displayname ?? "",
                    'encryptionType'    => $data->encryptionType ?? "",
                    'account_activation'=> $data->account_activation ?? 1,
                ]
            ];

            send_to_socket($message);
            $response->action = $message;

            print_r(json_encode($response, JSON_UNESCAPED_UNICODE));
        }

        elseif ($data->command === 'remove') {
            $response->success = 1;
            $response->message = 'Удаление аккаунта отправлено через сокет (без сохранения).';

            $config = load_config();
            $proxySection = find_proxy_section($config, $data->account);

            $message = [
                'type' => 'sip',
                'event' => 'sip_config_removed',
                'account' => $data->account,
                'proxy_section' => $proxySection
            ];

            send_to_socket($message);
            $response->action = $message;

            print_r(json_encode($response));
        }
    }
}

?>