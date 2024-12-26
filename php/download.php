<?php
error_reporting(0);

if (isset($_GET['logfile'])) {
    $logFile = $_GET['logfile'];
    
    if (file_exists($logFile)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($logFile) . '"');
        header('Content-Length: ' . filesize($logFile));
        
        readfile($logFile);
        exit;
    } else {
        echo "Файл не найден!";
    }
}
elseif (isset($_GET['filepath'])) {
    $filePath = $_GET['filepath'];

    if (file_exists($filePath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        readfile($filePath);
        exit;
    } else {
        die('Файл не найден!');
    }
} else {
    die('Не указан файл для скачивания.');
}
?>