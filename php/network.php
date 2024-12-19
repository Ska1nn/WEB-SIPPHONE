<?php

require __DIR__ . '/config.php';

function mask2cidr($mask) {
    $long = ip2long($mask);
    $base = ip2long('255.255.255.255');
    return 32 - log(($long ^ $base) + 1, 2);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = load_networkd();
    $ethernet_info = trim(shell_exec("grep 'ethernet_enabled' /opt/cumanphone/etc/config.conf | awk -F= '{print $2}'"));
    $ethernet_info = $ethernet_info !== null ? trim($ethernet_info) : '0';
    $data['ethernet_info'] = $ethernet_info !== '' ? $ethernet_info : '0';

    $mtu_status = trim(shell_exec("sysctl -n net.ipv4.ip_no_pmtu_disc"));
    $data['mtu_status'] = $mtu_status;

    $snmp_process = shell_exec("ps aux | grep '[s]nmpd'");
    $data['snmp_status'] = !empty($snmp_process) ? "active" : "inactive";

    $audio_dscp = shell_exec("grep 'audio_dscp' /opt/cumanphone/etc/config.conf | awk -F= '{print $2}'");
    $audio_dscp = $audio_dscp !== null ? trim($audio_dscp) : '0';

    if ($audio_dscp === '0x2e') {
        $data['audio_qos_status'] = "1";
    } elseif ($audio_dscp === '0x0' || $audio_dscp === '') {
        $data['audio_qos_status'] = "0";
    } else {
        $data['audio_qos_status'] = $audio_dscp;
    }

    $video_qos = shell_exec("grep 'video_dscp' /opt/cumanphone/etc/config.conf | awk -F= '{print $2}'");
    $video_qos = $video_qos !== null ? trim($video_qos) : '0';

    if ($video_qos === '0x22') {
        $data['video_qos_status'] = "1";
    } elseif ($video_qos === '0x0' || $video_qos === '') {
        $data['video_qos_status'] = "0";
    } else {
        $data['video_qos_status'] = $video_qos;
    }

    $sip_qos = shell_exec("grep 'dscp' /opt/cumanphone/etc/config.conf | tail -n 1 | awk -F= '{print $2}'");
    $sip_qos = $sip_qos !== null ? trim($sip_qos) : '0';

    if ($sip_qos === '0x1a') {
        $data['sip_qos_status'] = "1";
    } elseif ($sip_qos === '0x0' || $sip_qos === '') {
        $data['sip_qos_status'] = "0";
    } else {
        $data['sip_qos_status'] = $sip_qos;
    }
    
    echo json_encode($data);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = new stdClass();
    $contents = file_get_contents('php://input');
    $data = json_decode($contents);

    if (isset($data->snmp_action)) {
        $action = $data->snmp_action === "start" ? "start" : "stop";
        exec("systemctl $action snmpd", $output, $retval);

        $snmp_process = shell_exec("ps aux | grep '[s]nmpd'");
        $snmp_status = !empty($snmp_process) ? "active" : "inactive";

        $response->snmp_success = ($retval === 0);
        $response->snmp_status = $snmp_status; 
        echo json_encode($response);
        exit; 
    }

    if (isset($data->{'ethernet_info'}) && $data->{'ethernet_info'} == 1) {
        $ini = "[Match]\n";
        $ini .= "Name=lan0\n";
        $ini .= "\n";
        $ini .= "[Network]\n";
        if (isset($data->{'dhcp'}) && $data->{'dhcp'} == "true") {
            $ini .= "DHCP=yes\n";
        } else {
            $netmask = mask2cidr($data->{'netmask'});
            $ini .= "Address=" . $data->{'address'} . "/" . $netmask . "\n";
            if (isset($data->{'gateway'})) $ini .= "Gateway=" . $data->{'gateway'} . "\n";
            if (isset($data->{'dns1'})) $ini .= "DNS=" . $data->{'dns1'} . "\n";
            if (isset($data->{'dns2'})) $ini .= "DNS=" . $data->{'dns2'} . "\n";
        }
        file_put_contents('/etc/systemd/network/80-lan0.network', $ini);

        if (isset($data->{'mtu_status'}) && $data->{'mtu_status'} == "1") {
            shell_exec("sysctl net.ipv4.ip_no_pmtu_disc=1");
        } elseif (isset($data->{'mtu_status'}) && $data->{'mtu_status'} == "0") {
            shell_exec("sysctl net.ipv4.ip_no_pmtu_disc=0");
        }
        
        exec("/bin/systemctl restart systemd-networkd", $output, $retval);
        $response->success = ($retval == 0) ? 1 : 0;
        echo json_encode($response);

    } elseif (isset($data->{'vlan_info'}) && $data->{'vlan_info'} == 1) {
        $config = load_config();
        $config['ui']['vlan'] = $data->{'vlan_info'};
        $config['ui']['ethernet_enabled'] = $data->{'ethernet_info'};
        $config['ui']['vlan_lan_gateway'] = $data->{'vlan_gateway'};
        $config['ui']['vlan_lan_port'] = $data->{'vlan_lanid'};
        $config['ui']['vlan_lan_ip_address'] = $data->{'vlan_address'};

        if (save_config($config) !== false) {
            $response->success = 1;
        } else {
            $response->success = 0;
        }
        echo json_encode($response);

        $vlan_lanid = escapeshellarg($data->{'vlan_lanid'});
        $vlan_address = escapeshellarg($data->{'vlan_address'});
        $vlan_gateway = escapeshellarg($data->{'vlan_gateway'});

        exec("/usr/bin/vlan $vlan_lanid $vlan_address $vlan_gateway", $output, $retval);
        if (isset($data->{'mtu_status'}) && $data->{'mtu_status'} == "1") {
            shell_exec("sysctl net.ipv4.ip_no_pmtu_disc=1");
            exec("/bin/systemctl restart systemd-networkd", $output, $retval);
        } elseif (isset($data->{'mtu_status'}) && $data->{'mtu_status'} == "0") {
            shell_exec("sysctl net.ipv4.ip_no_pmtu_disc=0");
            exec("/bin/systemctl restart systemd-networkd", $output, $retval);
        }
        if ($retval == 0) {
            $response->success = 1;
            exec("systemctl restart cumanphone");
        } else {
            $response->success = 0;
        }
        echo json_encode($response);
    }

    if (isset($data->{'audio_qos_status'}) && $data->{'audio_qos_status'} == "1") {
        shell_exec("sed -i 's/^audio_dscp=[^ ]*/audio_dscp=0x2e/' /opt/cumanphone/etc/config.conf");
        exec("/bin/systemctl restart systemd-networkd", $output, $retval);
    } elseif (isset($data->{'audio_qos_status'}) && $data->{'audio_qos_status'} == "0") {
        shell_exec("sed -i 's/^audio_dscp=[^ ]*/audio_dscp=0x0/' /opt/cumanphone/etc/config.conf");
        exec("/bin/systemctl restart systemd-networkd", $output, $retval);
    }

    if (isset($data->{'video_qos_status'}) && $data->{'video_qos_status'} == "1") {
        shell_exec("sed -i 's/^video_dscp=[^ ]*/video_dscp=0x22/' /opt/cumanphone/etc/config.conf");
        exec("/bin/systemctl restart systemd-networkd", $output, $retval);
    } elseif (isset($data->{'video_qos_status'}) && $data->{'video_qos_status'} == "0") {
        shell_exec("sed -i 's/^video_dscp=[^ ]*/video_dscp=0x0/' /opt/cumanphone/etc/config.conf");
        exec("/bin/systemctl restart systemd-networkd", $output, $retval);
    }

    if (isset($data->{'sip_qos_status'}) && $data->{'sip_qos_status'} == "1") {
        shell_exec("sed -i '/\[sip\]/,/^\[/{s/^dscp=[^ ]*/dscp=0x1a/}' /opt/cumanphone/etc/config.conf");
        exec("/bin/systemctl restart systemd-networkd", $output, $retval);
    } elseif (isset($data->{'sip_qos_status'}) && $data->{'sip_qos_status'} == "0") {
        shell_exec("sed -i '/\[sip\]/,/^\[/{s/^dscp=[^ ]*/dscp=0x0/}' /opt/cumanphone/etc/config.conf");
        exec("/bin/systemctl restart systemd-networkd", $output, $retval);
    }

    if (isset($data->{'autoprovision_ip'}) && isset($data->{'autoprovision_protocol'})) {
        $autoprovision_ip = escapeshellarg($data->{'autoprovision_ip'});
        $autoprovision_protocol = escapeshellarg($data->{'autoprovision_protocol'});
        $autoprovision_password = isset($data->{'autoprovision_password'}) ? escapeshellarg($data->{'autoprovision_password'}) : '';


        $command = "autoprovision $autoprovision_ip";

        exec($command, $output, $retval);

        if ($retval === 0) {
            $response->success = 1;
            $response->message = "Autoprovisioning completed successfully";
        } else {
            $response->success = 0;
            $response->error = "Autoprovisioning failed. Error: $retval";
        }

        echo json_encode($response);
        exit;
    }
}
?>
