<?php

// Для запуска локально

// $networkd_filename = "../config/80-lan0.network";
// $config_filename = "../config/config.conf";
// $ntpdate_filename = "../config/ntpdate";
// $blf_filename = "../config/blf.conf";

// Для запуска на телефоне

$networkd_filename = "/etc/systemd/network/80-lan0.network";
$config_filename = "/opt/cumanphone/etc/config.conf";
$ntpdate_filename = "/etc/default/ntpdate";
$blf_filename = "/opt/cumanphone/share/blf/blf.conf";

function load_file($filename) {
    $ini = file($filename);
    if ( count( $ini ) == 0 ) { return array(); }
    $sections = array();
    $values = array();
    $globals = array();
    $i = 0;
    foreach( $ini as $line ){
        $line = trim($line);
        // Comments
        if ( is_null($line) || $line == '' || $line[0] == ';' || $line[0] == '#' ) { continue; }
        // Sections
        if ( $line[0] == '[' ) {
            $sections[] = substr( $line, 1, -1 );
            $i++;
            continue;
        }
        // Key-value pair
        list( $key, $value ) = explode( '=', $line, 2 );

        if ( is_null($key) ) { continue; }
  
        if( $value == "\"\"" )
           $value = null;
        
        if ( $i == 0 ) {
            $globals[ $key ] = $value;
        } else {
            $values[ $i - 1 ][ $key ] = $value;
        }
    }
    for ( $j=0; $j<$i; $j++ ) {
         $result[ $sections[ $j ] ] = $values[ $j ];
    }
    if ( isset($result) )
       return $result + $globals;
    else
       return $globals;
}

function save_file($filename, $data) {
    $res = array();
    foreach ($data as $key => $val) {
        if (is_array($val)) {
            $res[] = "[$key]\n";
            foreach ($val as $skey => $sval) 
                $res[] = "$skey=$sval\n";
            $res[] = "\n"; 
        }
        else 
            $res[] = "$key=$val\n";
    }

    return file_put_contents($filename, $res);
}

function load_networkd() {
    global $networkd_filename;
    return load_file($networkd_filename);
}

function save_networkd( $data ) {
    global $networkd_filename;
    save_file($networkd_filename, $data);      
}

function load_config() {
    global $config_filename;
    return load_file($config_filename);
}

function save_config( $data ) {
    global $config_filename;
    return save_file($config_filename, $data);      
}

function load_ntpdate() {
    global $ntpdate_filename;
    return load_file($ntpdate_filename);
}

function save_ntpdate( $data ) {
    global $ntpdate_filename;
    return save_file($ntpdate_filename, $data);      
}

function load_blf() {
    global $blf_filename;
    return load_file($blf_filename);
}

function save_blf( $data ) {
    global $blf_filename;
    return save_file($blf_filename, $data);      
}

?>
