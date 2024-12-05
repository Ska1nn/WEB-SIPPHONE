<?php

require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    $wallpaperFolder = '/opt/cumanphone/share/images/';
    
    $imageFiles = glob($wallpaperFolder . '*.{jpg,jpeg,png,gif,bmp}', GLOB_BRACE);
    
    $wallpapers = [];
    foreach ($imageFiles as $file) {
        $wallpapers[] = basename($file);
    }

    $data = new stdClass();
    $data->wallpapers = $wallpapers;
    print_r(json_encode($data));
}

?>
