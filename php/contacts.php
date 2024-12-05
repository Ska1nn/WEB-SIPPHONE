<?php

require __DIR__ . '/config.php';


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = new stdClass();
    if (isset($config['ui']['web_import_from_server_type'])) {
        if (!isset($data->download)) {
            $data->download = new stdClass();
        }


        if(isset($config['ui']['web_import_from_server_type'])){
            $data->download->protocol = $config['ui']['web_import_from_server_type'];
        }
        if ($data->download->protocol==="HTTPS"){
            if(isset($config['ui']['web_import_from_server_url_address'])){
                $data->download->url = $config['ui']['web_import_from_server_url_address'];
            }
        }
        if ($data->download->protocol==="UDP"){
            if(isset($config['ui']['web_import_from_server_ip_address_and_port'])){
                $data->download->address_port = $config['ui']['web_import_from_server_ip_address_and_port'];
            }
            if(isset($config['ui']['web_import_from_server_file_name'])){
                $data->download->filename = $config['ui']['web_import_from_server_file_name'];
            }
        }
    
        if(isset($config['ui']['web_import_contacts_mode'])){
            $data->download->type = $config['ui']['web_import_contacts_mode'];
        }

    }
    

    print_r(json_encode($data));
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = new stdClass();
    $contents = file_get_contents('php://input');
    $data = json_decode($contents);
    if (isset($data->{'command'})) {
        if ($data->{'command'} == "delete-contacts") {
            $config = load_config();
            if ($data->{'status'}) {
                $config['ui']['web_remove_all_contacts_enabled'] = "True";
                if (save_config($config) === false)
                    $response->success = 0;
                else
                    $response->success = 1;
                print_r(json_encode($response));
            }
        } elseif ($data->{'command'} == "save") {
            $config = load_config();
            if (isset($data->download)) {
                if ($data->download->status == true ) {
                    if ($data->download->download_type == "udp") {
                        $config['ui']['web_import_from_server_type'] = "UDP";
                        $config['ui']['web_import_from_server_ip_address_and_port'] = $data->download->address_port;
                        $config['ui']['web_import_from_server_file_name'] = $data->download->filename;
                        $config['ui']['web_import_from_server_url_address'] = "";
                    }else if($data->download->download_type == "https") {
                        $config['ui']['web_import_from_server_type'] = "HTTPS";
                        $config['ui']['web_import_from_server_url_address'] = $data->download->url;
                        $config['ui']['web_import_from_server_ip_address_and_port'] = "";
                        $config['ui']['web_import_from_server_file_name'] = "";
                    }
                    $config['ui']['web_import_contacts_mode'] = $data->download->type;
                } else {
                    if(isset($config['ui']['web_import_from_server_url_address'])){
                        $config['ui']['web_import_from_server_url_address'] = "";
                    }
                    if(isset($config['ui']['web_import_from_server_ip_address_and_port'])){
                        $config['ui']['web_import_from_server_ip_address_and_port'] = "";
                    }
                    if(isset($config['ui']['web_import_from_server_file_name'])){
                        $config['ui']['web_import_from_server_file_name'] = "";
                    }
                    if(isset($config['ui']['web_import_contacts_mode'])){
                        $config['ui']['web_import_contacts_mode'] = "";
                    }
                }
            }
        

            if (save_config($config) === false)
                $response->success = 0;
            else
                $response->success = 1;
            print_r(json_encode($response));

        }
    }
}
?>