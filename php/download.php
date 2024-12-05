<?php
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
} else {
    echo "Не указан файл для скачивания.";
}
?>
