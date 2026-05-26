<?php
// Пропускаем только запросы с X-Requested-With: XMLHttpRequest
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
    header('Location: /');
    exit;
}