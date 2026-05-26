<?php
// Разрешаем только запросы с заголовком X-Requested-With: XMLHttpRequest
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
    header('Location: /');
    exit;
}

$page = basename($_GET['page'] ?? '');
if ($page === '') {
    http_response_code(400);
    exit('Bad request');
}

$file = __DIR__ . "/../{$page}.html";
if (!file_exists($file)) {
    http_response_code(404);
    exit('Page not found');
}

header('Content-Type: text/html; charset=utf-8');
readfile($file);