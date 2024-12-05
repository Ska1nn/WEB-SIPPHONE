<?php

require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $response = new stdClass();
    $config = load_config();
    if ( isset($config['ui']['language']) )
       $response->language = $config['ui']['language'];    
    else         
        $response->language = "ru";
    print_r(json_encode($response));
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = new stdClass();
    $contents = file_get_contents('php://input');
    $data = json_decode($contents);     
    if ( isset($data->{'language'}) ) { 
        $config = load_config();
        $config['ui']['language'] = $data->{'language'};
        if ( save_config($config) === false )
            $response->success = 0;
        else 
            $response->success = 1;
        print_r(json_encode($response));
    }
}

?> 