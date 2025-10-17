<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (isset($_GET['logfile'])) {
    $logFile = $_GET['logfile'];
    if (file_exists($logFile)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($logFile) . '"');
        header('Content-Length: ' . filesize($logFile));
        readfile($logFile);
        exit;
    } else {
        die("Файл не найден!");
    }
}

elseif (isset($_GET['filepath'])) {
    $filePath = $_GET['filepath'];
    if (file_exists($filePath)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    } else {
        die("Файл не найден!");
    }
}
elseif (isset($_GET['export']) && $_GET['export'] === "autoprovision") {
    $mac_address = trim(shell_exec("cat /sys/class/net/eth0/address"));
    if (!$mac_address) {
        die("Не удалось получить MAC-адрес");
    }
    $mac_address = strtoupper(str_replace(":", "-", $mac_address));
    $zip_file = sys_get_temp_dir() . "/mac" . $mac_address . ".zip";

    $paths_to_export = [
        "/.local/share/CumanPhone",
        "/etc/default/ntpdate",
        "/etc/localtime.tmp",
        "/etc/timezone",
        "/opt/cumanphone/etc/config.conf",
        "/opt/cumanphone/share/blf/blf.conf",
        "/opt/cumanphone/share/images",
        "/opt/cumanphone/share/sounds/rings"
    ];

    $existing_files = [];
    foreach ($paths_to_export as $path) {
        if (file_exists($path)) {
            $existing_files[] = $path;
        }
    }

    if (empty($existing_files)) {
        die("Нет файлов для архивации");
    }

    $files = implode(" ", array_map("escapeshellarg", $existing_files));
    
    $cmd = "7z a -spf -pWHATABOUT -mhe=on " . escapeshellarg($zip_file) . " $files 2>&1";
    $output = shell_exec($cmd);

    if (!file_exists($zip_file)) {
        $cmd = "7z a -spf -pWHATABOUT " . escapeshellarg($zip_file) . " $files 2>&1";
        $output = shell_exec($cmd);
    }

    if (!file_exists($zip_file)) {
        die("Архив не создан. Ошибка: " . $output);
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="mac' . $mac_address . '.zip"');
    header('Content-Length: ' . filesize($zip_file));
    readfile($zip_file);
    unlink($zip_file);
    exit;
}