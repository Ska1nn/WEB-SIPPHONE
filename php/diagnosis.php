<?php

require __DIR__ . '/config.php';

function mask2cidr($mask) {
    $long = ip2long($mask);
    $base = ip2long('255.255.255.255');
    return 32-log(($long ^ $base)+1,2);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['action'])) {
        $action = $_GET['action'];
        $language = isset($_GET['language']) ? $_GET['language'] : 'ru'; 
        switch ($action) {
            case 'ping':
                $address = $_GET['address'];
                $output = shell_exec("ping -c 4 " . $address);
                echo $output;
                break;
            case 'traceroute':
                $address = $_GET['address'];
                $output = shell_exec("traceroute " . $address);
                echo $output;
                break;
            case 'speedtest':
                $address = $_GET['address'];
                $pingOutput = shell_exec("ping -c 4 " . escapeshellarg($address) . " 2>&1");
                if (strpos($pingOutput, '0% packet loss') !== false) {
                    $output = shell_exec("iperf3 -c " . escapeshellarg($address));
                    echo $output;
                } else {
                    echo "Сервер недоступен. Проверьте адрес: " . htmlspecialchars($address);
                }
                break;
                case 'readlog':
                    $logfile = $_GET['logfile'];
                    $isErrorLog = isset($_GET['isErrorLog']) && $_GET['isErrorLog'] === 'true';
                
                    $output = shell_exec("tail -n 50 " . escapeshellarg($logfile));
                
                    if ($isErrorLog) {
                        $lines = explode("\n", $output);
                        $filteredLines = [];
                
                        foreach ($lines as $line) {
                            if (strpos($line, '[ERROR]') !== false) {
                                $filteredLines[] = $line; 
                            }
                        }
                
                        if (empty($filteredLines)) {
                            $output = "Критические ошибки не обнаружены";
                        } else {
                            $output = implode("\n", $filteredLines); 
                        }
                    }
                
                    echo $output;
                    break;
                
                    case 'checksip':
                        $address = '10.10.2.4';
                        $output = shell_exec("ping -c 4 " . escapeshellarg($address) . " 2>&1");
                
                        if (strpos($output, '0% packet loss') !== false || strpos($output, '4 packets transmitted, 4 received') !== false) {
                            if ($language == 'kz') {
                                echo "Сип-серверге қосылу орнатылды.";
                            } elseif ($language == 'en') {
                                echo "Connection to SIP server established.";
                            } elseif ($language == 'ru') {
                                echo "Соединение с SIP-сервером установлено.";
                            } elseif ($language == 'kzlat') {
                                echo "SIP serverge qosylu ornatyldy.";
                            }
                        } else {
                            if ($language == 'kz') {
                                echo "SIP-серверге қосыла алмадық.";
                            } elseif ($language == 'en') {
                                echo "Failed to connect to SIP server.";
                            } elseif ($language == 'ru') {
                                echo "Не удалось подключиться к SIP-серверу.";
                            } elseif ($language == 'kzlat') {
                                echo "SIP serverge qosyla almadik.";
                            }
                        }
                        break;           
        }
    }
    else {
        $data = load_networkd();
        print_r(json_encode($data));
    }
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = new stdClass();
    $contents = file_get_contents('php://input');
    $data = json_decode($contents);
    $ini  = "[Match]\n";
    $ini .= "Name=lan0\n";
    $ini .= "\n";
    $ini .= "[Network]\n";
    if ( isset($data->{'dhcp'}) && $data->{'dhcp'} == "true" ) {
        $ini .= "DHCP=yes\n";
    }
    else  {
        if ( isset($data->{'address'}) ) { 
            $netmask = mask2cidr($data->{'netmask'});
            $ini .= "Address=".$data->{'address'}."/".$netmask."\n";
        }
        if ( isset($data->{'gateway'}) ) {
            $ini .= "Gateway=".$data->{'gateway'}."\n";
        }
        if ( isset($data->{'dns1'}) ) {
            $ini .= "DNS=".$data->{'dns1'}."\n";
        }
        if ( isset($data->{'dns2'}) ) {
            $ini .= "DNS=".$data->{'dns2'}."\n";
        }
    }
    file_put_contents('/etc/systemd/network/80-lan0.network', $ini);
    exec("/bin/systemctl restart systemd-networkd", $output, $retval);
    if ( $retval == 0 )
        $response->success = 1;
    else   
        $response->success = 0;
    print_r(json_encode($response));
}

?>