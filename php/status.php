<?php

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = new stdClass();
    $output =  shell_exec("ifconfig lan0");

    $flags_pos = strpos($output , 'flags');
    $mtu_pos = strpos($output , 'mtu');
    $inet_pos = strpos($output , 'inet');
    $netmask_pos = strpos($output , 'netmask');
    $broadcast_pos = strpos($output , 'broadcast');
    $ether_pos = strpos($output , 'ether');

    if ($flags_pos !== false) {
        $flags_pos = $flags_pos + 6;
        $flags = substr($output, $flags_pos, ($mtu_pos - $flags_pos - 2));
        $data->running = str_contains($flags, 'RUNNING');
    }

    if ($inet_pos !== false) {
        $inet_pos = $inet_pos + 5;
        $data->ip = substr($output, $inet_pos, ($netmask_pos - $inet_pos - 2));
    }

    if ($netmask_pos !== false) {
        $netmask_pos = $netmask_pos + 8;
        $data->netmask = substr($output, $netmask_pos, ($broadcast_pos - $netmask_pos - 2));
    }

    if ($ether_pos !== false) {
        $data->mac = substr($output, ($ether_pos + 6), 17);
    }

    $output = shell_exec("/opt/cumanphone/bin/CumanPhone --version");
    if ( !empty($output) && str_contains($output, 'CumanPhone version: ') ) 
        $data->version = substr($output, 20);

    print_r(json_encode($data));
}
