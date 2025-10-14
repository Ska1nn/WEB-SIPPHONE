<?php

require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $response = new stdClass();
    $config = load_config();
    if ( isset($config['forwarding']['unconditional']) )
        $response->unconditional = $config['forwarding']['unconditional'];    
    else         
        $response->unconditional = "";  

    if ( isset($config['forwarding']['busy']) )
        $response->busy = $config['forwarding']['busy'];    
    else         
        $response->busy = "";  

    if ( isset($config['forwarding']['no_answer']) )
        $response->no_answer = $config['forwarding']['no_answer'];    
    else         
        $response->no_answer = "";  

    if ( isset($config['forwarding']['no_answer_timeout']) )
        $response->no_answer_timeout = $config['forwarding']['no_answer_timeout'];    
    else         
        $response->no_answer_timeout = 30;  


    print_r(json_encode($response));
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = new stdClass();
    $contents = file_get_contents('php://input');
    $data = json_decode($contents);     
    $config = load_config();
    $config['forwarding']['unconditional'] = $data->{'unconditional'};   
    $config['forwarding']['busy'] = $data->{'busy'};
    $config['forwarding']['no_answer'] = $data->{'no_answer'};  
    $config['forwarding']['no_answer_timeout'] = $data->{'no_answer_timeout'};  

    if ( save_config($config) === false ) {
        $response->success = 0;
        $response->message = "Ошибка сохранения";
    } else {
        $response->success = 1;
        $response->message = "Настройки сохранены.";
    }
    print_r(json_encode($response, JSON_UNESCAPED_UNICODE));
}

?> 