<?php

require __DIR__ . '/config.php';


function hash_pin_code($pinCode) {
    return sha1($pinCode);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = new stdClass();
    $config = load_config();
    if ( isset($config['ui']['admin_login']) )
        $data->admin_login = $config['ui']['admin_login'];
    else
        $data->admin_login = "";

    if ( isset($config['ui']['pin_code_enabled']) )
        $data->pin_code_enabled = $config['ui']['pin_code_enabled'];
    else
        $data->pin_code_enabled = "False";
    print_r(json_encode($data));
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = new stdClass();   
    $contents = file_get_contents('php://input');
    $data = json_decode($contents);
    if ( isset($data->{'command'} ) ) {
        if ( $data->{'command'} == "save" ) { 
           $config = load_config();
           if ( isset($data->pin_code_enabled) ){
               if ($data->pin_code_enabled == "1"){
                   $config['ui']['pin_code_enabled'] = "True";                
                   $config['ui']['pin_code_hash'] = hash_pin_code($data->pin_code_hash);
               }
               else {
                   $config['ui']['pin_code_enabled'] = "False";
                   $config['ui']['pin_code_hash'] = "";
               }
           }
           if ( isset($data->authorization) ) {
                if ( $data->authorization == "1"  ) {
                    $config['ui']['admin_login'] = $data->admin_login;
                    $config['ui']['admin_password_hash'] = md5($data->admin_password);
                    $config['ui']['admin_password_disabled'] = "0";
                }
                else{
                    $config['ui']['admin_password_disabled'] = "1";
                    unset($config['ui']['admin_login']);
                    unset($config['ui']['admin_password_hash']);
                }
            }
            if ( save_config($config) === false ) {
                $response->success = 0;
                $response->message = "Ошибка сохранения";
            } else {
                $response->success = 1;
                $response->message = "Настройки сохранены";
            }
                print_r(json_encode($response, JSON_UNESCAPED_UNICODE));
        }
    }
}

?> 
