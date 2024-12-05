<?php

require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = new stdClass();
    $login = 0;
    $config = load_config();
    if ( isset($config['web']['username']) ) {
        $login = (int)(!empty($config['web']['username']));  
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
    if ( isset($data->{'username'}) ) {
        if ( $config['web']['username'] == $data->username ) {
            if ( $config['web']['password'] == $data->password ) {
                $success = 1; 
            }  
        }                 
    }
    $response->username = $config['web']['username'];
    $response->password = $config['web']['password'];
    $response->success = $success;  
    print_r(json_encode($response));
}

?> 