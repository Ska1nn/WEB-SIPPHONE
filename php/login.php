<?php

require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = new stdClass();
    $login = 0;
    $config = load_config();
    if ( isset($config['ui']['admin_login'])) {
        $login = (int)(!empty($config['ui']['admin_login']));
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
        if ( $config['ui']['admin_login'] == $data->username ) {
            $inputPasswordHash = md5($data->password);
            if ($config['ui']['admin_password_hash'] == $inputPasswordHash) {
                $success = 1;
            }
        }
    }
    $response->admin_login = $config['ui']['admin_login'];
    $response->success = $success;
    $response->message = $success ? "Вход выполнен" : "Неверный логин или пароль";
    print_r(json_encode($response, JSON_UNESCAPED_UNICODE));
}

?> 