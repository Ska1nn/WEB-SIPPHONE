<?php

require __DIR__ . '/config.php';

function load( $data ) {
    $response = new stdClass();

    if ( !isset($data->{'account'}) ) {
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
    if ( isset($data->{'account'}) ) {
        $config = load_config();
        $auth = 'auth_info_'.$data->{'account'};  
        if ( isset($config[$auth]) )
           $response->auth = $config[$auth];
        $proxy = 'proxy_'.$data->{'account'};

        if ( isset($config[$proxy]) )
           $response->reg_proxy = $config[$proxy]['reg_proxy'];
        if ( isset($config[$proxy]['x-custom-property:rtp_ports']) )
           $response->rtp_ports = $config[$proxy]['x-custom-property:rtp_ports'];
        if ( isset($config[$proxy]['x-custom-property:dtmf']) )
           $response->dtmf = $config[$proxy]['x-custom-property:dtmf'];             
        if ( isset($config[$proxy]['x-custom-property:codecs']) )
           $response->codecs = $config[$proxy]['x-custom-property:codecs'];
        if ( isset($config[$proxy]['x-custom-property:encryptionType']) )
           $response->encryptionType = $config[$proxy]['x-custom-property:encryptionType'];
        if ( isset($config['sip']['media_encryption_mandatory']) )
           $response->media_encryption_mandatory = $config['sip']['media_encryption_mandatory'];
        if ( isset($config['sip']['media_encryption']) )
           $response->media_encryption = $config['sip']['media_encryption'];        

    }
    return $response;

}

function save($data) {
    if (isset($data->account)) {
        $config = load_config();

        $proxy = 'proxy_' . $data->account;
        $reg_proxy = '<sip:' . $data->domain . ';transport=' . $data->transport . '>';
        $config[$proxy]['reg_proxy'] = $reg_proxy;

        $reg_identity = '"' . $data->realm . '" <sip:' . $data->username . '@' . $data->domain . '>';
        $config[$proxy]['reg_identity'] = $reg_identity;

        $config[$proxy]['quality_reporting_enabled'] = isset($data->quality_reporting_enabled) ? $data->quality_reporting_enabled : 0;
        $config[$proxy]['quality_reporting_interval'] = isset($data->quality_reporting_interval) ? $data->quality_reporting_interval : 0;
        $config[$proxy]['reg_expires'] = isset($data->reg_expires) ? $data->reg_expires : 3600;
        $config[$proxy]['reg_sendregister'] = isset($data->reg_sendregister) ? $data->reg_sendregister : 1;
        $config[$proxy]['publish'] = isset($data->publish) ? $data->publish : 0;
        $config[$proxy]['avpf'] = isset($data->avpf) ? $data->avpf : -1;
        $config[$proxy]['avpf_rr_interval'] = isset($data->avpf_rr_interval) ? $data->avpf_rr_interval : 1;
        $config[$proxy]['dial_escape_plus'] = isset($data->dial_escape_plus) ? $data->dial_escape_plus : 0;
        $config[$proxy]['use_dial_prefix_for_calls_and_chats'] = isset($data->use_dial_prefix_for_calls_and_chats) ? $data->use_dial_prefix_for_calls_and_chats : 1;
        $config[$proxy]['privacy'] = isset($data->privacy) ? $data->privacy : 32768;
        $config[$proxy]['push_notification_allowed'] = isset($data->push_notification_allowed) ? $data->push_notification_allowed : 0;
        $config[$proxy]['remote_push_notification_allowed'] = isset($data->remote_push_notification_allowed) ? $data->remote_push_notification_allowed : 0;
        $config[$proxy]['cpim_in_basic_chat_rooms_enabled'] = isset($data->cpim_in_basic_chat_rooms_enabled) ? $data->cpim_in_basic_chat_rooms_enabled : 0;
        $config[$proxy]['idkey'] = isset($data->idkey) ? $data->idkey : 'proxy_config_U0AvVnUHsVk4fxN';
        $config[$proxy]['publish_expires'] = isset($data->publish_expires) ? $data->publish_expires : -1;
        $config[$proxy]['rtp_bundle'] = isset($data->rtp_bundle) ? $data->rtp_bundle : 0;
        $config[$proxy]['rtp_bundle_assumption'] = isset($data->rtp_bundle_assumption) ? $data->rtp_bundle_assumption : 0;

        if (isset($data->srtp_type)) {
            $config[$proxy]['x-custom-property:srtp'] = $data->srtp_type;
        }

        if (isset($data->media_encryption_mandatory)) {
            $config['sip']['media_encryption_mandatory'] = $data->media_encryption_mandatory;
        }
        if (isset($data->media_encryption)) {
            $config['sip']['media_encryption'] = $data->media_encryption;
        }

        $auth = 'auth_info_' . $data->account;
        if (isset($data->realm))
            $config[$auth]['realm'] = $data->realm;

        if (isset($data->username))
            $config[$auth]['username'] = $data->username;

        if (isset($data->passwd))
            $config[$auth]['passwd'] = $data->passwd;

        if (isset($data->domain))
            $config[$auth]['domain'] = $data->domain;

        if (isset($config[$auth]['ha1']))
            unset($config[$auth]['ha1']);

        $config[$auth]['algorithm'] = 'MD5';
        $config[$auth]['available_algorithms'] = 'MD5';

        return save_config($config);
    }
    return false;
}

function remove($data) {
    if (isset($data->account)) {
        $config = load_config();
        $auth = 'auth_info_' . $data->account;
        $proxy = 'proxy_' . $data->account;

        if (isset($config[$auth])) {
            unset($config[$auth]);
        }

        if (isset($config[$proxy])) {
            unset($config[$proxy]);
        }

        return save_config($config);
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = new stdClass();
    $contents = file_get_contents('php://input');
    $data = json_decode($contents);
    if (isset($data->command)) {
        if ($data->command === 'load') {
            print_r(json_encode(load($data)));
        } elseif ($data->command === 'save') {
            if (save($data) === false) {
                $response->success = 0;
            } else {
                $response->success = 1;
            }
            print_r(json_encode($response));
        } elseif ($data->command === 'remove') {
            if (remove($data) === false) {
                $response->success = 0;
            } else {
                $response->success = 1;
            }
            print_r(json_encode($response));
        }
    }
}


?> 