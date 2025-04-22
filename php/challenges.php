<?php
require __DIR__ . '/config.php';

function mask2cidr($mask) {
    $long = ip2long($mask);
    $base = ip2long('255.255.255.255');
    return 32 - log(($long ^ $base) + 1, 2);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = load_networkd();

    $unknown_phone_block = trim(shell_exec("grep 'unknown_phone_block' /opt/cumanphone/etc/config.conf | tail -n 1 | awk -F= '{print $2}'"));
    $data['unknown_phone_block'] = $unknown_phone_block;

    if ($unknown_phone_block === '1') {
        $data['unknown_phone_block'] = "1";
    } elseif ($unknown_phone_block === '0') {
        $data['unknown_phone_block'] = "0";
    }

    $block_anonymous_calls_enabled = trim(shell_exec("grep 'block_anonymous_calls_enabled' /opt/cumanphone/etc/config.conf | tail -n 1 | awk -F= '{print $2}'"));
    $data['block_anonymous_calls_enabled'] = $block_anonymous_calls_enabled;

    if ($block_anonymous_calls_enabled === 'True') {
        $data['block_anonymous_calls_enabled'] = "1";
    } elseif ($block_anonymous_calls_enabled === 'False') {
        $data['block_anonymous_calls_enabled'] = "0";
    }

    $do_not_disturb = trim(shell_exec("grep 'do_not_disturb' /opt/cumanphone/etc/config.conf | tail -n 1 | awk -F= '{print $2}'"));
    $data['do_not_disturb'] = $do_not_disturb;

    if ($do_not_disturb === '1') {
        $data['do_not_disturb'] = "1";
    } elseif ($do_not_disturb === '0') {
        $data['do_not_disturb'] = "0";
    }

    $show_name_from_contacts_enabled = trim(shell_exec("grep 'show_name_from_contacts_enabled' /opt/cumanphone/etc/config.conf | tail -n 1 | awk -F= '{print $2}'"));
    $data['show_name_from_contacts_enabled'] = $show_name_from_contacts_enabled;

    if ($show_name_from_contacts_enabled === 'True') {
        $data['show_name_from_contacts_enabled'] = "1";
    } elseif ($show_name_from_contacts_enabled === 'False') {
        $data['show_name_from_contacts_enabled'] = "0";
    }

    $auto_call_number_enabled = trim(shell_exec("grep 'auto_call_number_enabled' /opt/cumanphone/etc/config.conf | tail -n 1 | awk -F= '{print $2}'"));
    $data['auto_call_number_enabled'] = $auto_call_number_enabled;

    if ($auto_call_number_enabled === 'True') {
        $data['auto_call_number_enabled'] = "1";
    } elseif ($auto_call_number_enabled === 'False') {
        $data['auto_call_number_enabled'] = "0";
    }

    $auto_call_number = trim(shell_exec("grep 'auto_call_number' /opt/cumanphone/etc/config.conf | tail -n 1 | awk -F= '{print $2}'"));
    $data['auto_call_number'] = $auto_call_number;


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

        exec("/bin/systemctl restart systemd-networkd", $output, $retval);
        $response->success = ($retval == 0) ? 1 : 0;
        echo json_encode($response);
        
        if (isset($data->{'unknown_phone_blocks'}) && $data->{'unknown_phone_blocks'} == "1") {
            shell_exec("sysctl net.ipv4.sip_qos=1");
            exec("/bin/systemctl restart systemd-networkd", $output, $retval);
        } elseif (isset($data->{'unknown_phone_blocks'}) && $data->{'unknown_phone_blocks'} == "0") {
            shell_exec("sysctl net.ipv4.sip_qos=0");
            exec("/bin/systemctl restart systemd-networkd", $output, $retval);
        }

        if (isset($data->{'block_anonymous_calls_enabled'}) && $data->{'block_anonymous_calls_enabled'} == "1") {
            shell_exec("sysctl net.ipv4.sip_qos=1");
            exec("/bin/systemctl restart systemd-networkd", $output, $retval);
        } elseif (isset($data->{'block_anonymous_calls_enabled'}) && $data->{'block_anonymous_calls_enabled'} == "0") {
            shell_exec("sysctl net.ipv4.sip_qos=0");
            exec("/bin/systemctl restart systemd-networkd", $output, $retval);
        }

        if (isset($data->{'do_not_disturb'}) && $data->{'do_not_disturb'} == "1") {
            shell_exec("sysctl net.ipv4.sip_qos=1");
            exec("/bin/systemctl restart systemd-networkd", $output, $retval);
        } elseif (isset($data->{'do_not_disturb'}) && $data->{'do_not_disturb'} == "0") {
            shell_exec("sysctl net.ipv4.sip_qos=0");
            exec("/bin/systemctl restart systemd-networkd", $output, $retval);
        }

        if (isset($data->{'show_name_from_contacts_enabled'}) && $data->{'show_name_from_contacts_enabled'} == "1") {
            shell_exec("sysctl net.ipv4.sip_qos=1");
            exec("/bin/systemctl restart systemd-networkd", $output, $retval);
        } elseif (isset($data->{'show_name_from_contacts_enabled'}) && $data->{'show_name_from_contacts_enabled'} == "0") {
            shell_exec("sysctl net.ipv4.sip_qos=0");
            exec("/bin/systemctl restart systemd-networkd", $output, $retval);
        }

        if (isset($data->{'auto_call_number_enabled'}) && $data->{'auto_call_number_enabled'} == "1") {
            shell_exec("sysctl net.ipv4.sip_qos=1");
            exec("/bin/systemctl restart systemd-networkd", $output, $retval);
        } elseif (isset($data->{'auto_call_number_enabled'}) && $data->{'auto_call_number_enabled'} == "0") {
            shell_exec("sysctl net.ipv4.sip_qos=0");
            exec("/bin/systemctl restart systemd-networkd", $output, $retval);
        }

    }
}

?>
