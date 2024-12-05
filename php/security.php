<?php

require __DIR__ . '/config.php';


function hash_pin_code($pinCode) {
    return sha1($pinCode);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = new stdClass();
    $config = load_config();
    if ( isset($config['web']['web_username']) )
        $data->web_username = $config['web']['web_username'];
    else
        $data->web_username = "";

    if ( isset($config['web']['web_password']) )
        $data->web_password = $config['web']['web_password'];
    else
        $data->web_password = "";

    if ( isset($config['ui']['pin_code_enabled']) )
        $data->pin_code_enabled = $config['ui']['pin_code_enabled'];
    else
        $data->pin_code_enabled = "False";
    if ( isset($config['ui']['pin_code_hash']) )
        $data->pin_code_hash = $config['ui']['pin_code_hash'];
    else
        $data->pin_code_hash = "";



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
                    $config['web']['web_username'] = $data->web_username;
                    $config['web']['web_password'] = $data->web_password;
                }
                else{
                    $config['web']['web_username'] = "";
                    $config['web']['web_password'] = "";
                }
            }
           if ( save_config($config) === false )
               $response->success = 0;
           else 
               $response->success = 1;
           print_r(json_encode($response));
        }
    }
}

?> 
