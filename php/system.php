<?php

ini_set('memory_limit', '-1');
ini_set('upload_max_filesize', '512M');
ini_set('post_max_size', '512M');

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
    if ( isset($data->{'command'}) ) {
       if ( $data->{'command'} == "reboot" ) {
           exec("reboot", $output, $retval);
           if ( $retval == 0 )
               $response->success = 1;
           else   
               $response->success = 0;
       }   
       elseif ( $data->{'command'} == "reset" ) {
           exec('/opt/cumanphone/share/default/reset.sh', $output, $retval);
           if ( $retval == 0 )
               $response->success = 1;
           else   
               $response->success = 0;
       }
       elseif ( $data->{'command'} == "restart" ) {
           $output = exec('systemctl restart cumanphone', $output, $retval);
           if ( $retval == 0 )
               $response->success = 1;
           else   
               $response->success = 0;
       }
       elseif ( $data->{'command'} == "download-syslog" ) {
           header("Cache-Control: public");
           header("Content-Description: File Transfer");
           header("Content-Disposition: attachment; filename=syslog");
           header("Content-Transfer-Encoding: binary");
           header("Content-Type: binary/octet-stream");
           readfile("/var/log/syslog");
       }
       elseif ( $data->{'command'} == "download-log" ) {
           header("Cache-Control: public");
           header("Content-Description: File Transfer");
           header("Content-Disposition: attachment; filename=cumanphone1.log");
           header("Content-Transfer-Encoding: binary");
           header("Content-Type: binary/octet-stream");
           readfile("/opt/cumanphone/var/log/cumanphone1.log");    
       }
       elseif ( $data->{'command'} == "update" ) {
           $content = $data->{'content'};
           $mime = substr($content, 0, 30);         
           echo $mime; 
           if ( (str_starts_with($content, "data:text/x-sh;base64")) ||
                (str_starts_with($content, "data:application/x-shellscript;base64")) ||
                (str_starts_with($content, "data:application/octet-stream;base64")) )  {
               $content = substr($content, strpos($content, ',') + 1);
               $content = str_replace( ' ', '+', $content);
               $content = base64_decode($content);
               if ( $content === false ) {
                   throw new \Exception('base64_decode failed');
               }
               else {
                   $filename = "/tmp/".$data->{'filename'};
                   file_put_contents($filename, $content);
               }
               shell_exec("sh $filename");     
           }
           elseif ( str_starts_with($content, "data:application/x-compressed;base64") ) {
               $content = substr($content, strpos($content, ',') + 1);
               $content = str_replace( ' ', '+', $content);
               $content = base64_decode($content);
               if ( $content === false ) {
                   throw new \Exception('base64_decode failed');
               }
               else {
                   $filename = "/tmp/".$data->{'filename'};
                   file_put_contents($filename, $content);
               }     

               shell_exec("tar xjpf $filename -C / ");
           }
       }
    }
    print_r(json_encode($response));
}

?> 
