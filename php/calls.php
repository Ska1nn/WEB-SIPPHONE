<?php

require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = new stdClass();
    $ringtons = array();
    $files = array_diff(scandir('/opt/cumanphone/share/sounds/rings/'), array('.', '..'));
    foreach ($files as &$file) { 
       array_push($ringtons, $file);
    }  
    $data->ringtons = $ringtons;

    $config = load_config();
    if ( isset($config['sound']['local_ring']) ) {
        $ringtone = $config['sound']['local_ring'];  
        $data->ringtone = basename($ringtone);   

    }  
    else         
        $data->ringtone = "notes_of_the_optimistic.mkv";  

    if ( isset($config['ui']['auto_answer']) ) {
        $data->auto_answer = $config['ui']['auto_answer'];   
    }  
    else 
        $data->auto_answer = 0;

    if ( isset($config['ui']['conference_mxone']) ) {
        $data->mxone = $config['ui']['conference_mxone'];   
    }  
    else 
        $data->mxone = 0;

    print_r(json_encode($data));
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = new stdClass();   
    $contents = file_get_contents('php://input');
    $data = json_decode($contents);
    if ( isset($data->{'command'}) ) {
        if ( $data->command == "save" ) {
            $config = load_config();
            $ringtone = "/opt/cumanphone/share/sounds/rings/".$data->ringtone; 
            $config['sound']['local_ring'] = $ringtone;
            $config['ui']['auto_answer'] = $data->auto_answer;
            $config['ui']['conference_mxone'] = $data->mxone;
            if ( save_config($config) === false )
                $response->success = 0;
            else 
                $response->success = 1;
            print_r(json_encode($response)); 
        }
        elseif ( $data->command == "upload-ringtone" ) {
            $content = $data->{'ringtone'}; 
            if ( preg_match('/^data:audio\/(\w+);base64,/', $content, $type) ) {
                $content = substr($content, strpos($content, ',') + 1);
                $type = strtolower($type[1]);
                $content = str_replace( ' ', '+', $content);
                $content = base64_decode($content);
                if ( $content === false ) {
                    throw new \Exception('base64_decode failed');
                }  
               else {
                    $filename = "/opt/cumanphone/share/sounds/rings/".$data->filename;
                    $response->filename = $data->filename;
                    if ( file_put_contents($filename, $content) === false ) 
                        $response->success = 0;
                    else   
                        $response->success = 1;
               }     
            }
            print_r(json_encode($response));
        }     
    }
}

?> 