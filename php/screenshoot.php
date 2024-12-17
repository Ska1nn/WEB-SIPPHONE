<?php

require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = new stdClass();
    $version = shell_exec("/opt/cumanphone/bin/CumanPhone --version");
    if ( !empty($version ) && str_contains($version, 'CumanPhone version: ') ) 
        $data->version = substr(trim($version), 20);

    print_r(json_encode($data));
}  
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = new stdClass();   
    $contents = file_get_contents('php://input');
    $data = json_decode($contents);
    if ($data->{'command'} == "screenshooter") {
        putenv('WAYLAND_DISPLAY=wayland-0');
        putenv('XDG_RUNTIME_DIR=/run/user/0');

        exec("/usr/bin/weston-screenshooter", $output, $retval);

        if ($retval == 0) {
            exec("mv /srv/www/php/wayland-screenshot-*.png /opt/cumanphone/share/images/", $move_output, $move_retval);
            
            if ($move_retval == 0) {
                $response = array(
                    'status' => 'success',
                    'message' => 'Screenshot captured and moved successfully'
                );
            } else {
                $response = array(
                    'status' => 'error',
                    'message' => "Failed to move screenshot: " . implode("\n", $move_output)
                );
            }
        } else {
            $response = array(
                'status' => 'error',
                'message' => implode("\n", $output)
            );
        }
        
        echo json_encode($response);
        exit();
    }
}
?>
