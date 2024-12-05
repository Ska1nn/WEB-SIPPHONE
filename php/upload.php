<?php
ini_set('memory_limit', '-1');
ini_set('upload_max_filesize', '512M');
ini_set('post_max_size', '512M');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contents = file_get_contents('php://input');
    $filename = "/tmp/1.test";
    file_put_contents($filename, $content);
}
?>
