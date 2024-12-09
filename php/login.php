<?php

require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = new stdClass();
    $login = 0;
    $config = load_config();
    if ( isset($config['web']['web_username']) ) {
        $login = (int)(!empty($config['web']['web_username']));  
    }  
    $data->login = $login;  
    print_r(json_encode($data));
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = new stdClass();
    $contents = file_get_contents('php://input');
    $data = json_decode($contents);
    $success = 0;
    $config = load_config(); 
    if ( isset($data->{'web_username'}) ) {
        if ( $config['web']['web_username'] == $data->web_username ) {
            if ( $config['web']['web_password'] == $data->web_password ) {
                $success = 1; 
            }  
        }                 
    }
    $response->web_username = $config['web']['web_username'];
    $response->web_password = $config['web']['web_password'];
    $response->success = $success;  
    print_r(json_encode($response));
}

?> 