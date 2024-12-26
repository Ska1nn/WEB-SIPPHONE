<?php
ini_set('memory_limit', '-1');
ini_set('upload_max_filesize', '512M');
ini_set('post_max_size', '512M');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '/opt/cumanphone/share/blf/';
        $uploadFile = $uploadDir . 'blf.conf';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        if (move_uploaded_file($_FILES['import_file']['tmp_name'], $uploadFile)) {
            echo json_encode([
                'success' => true,
                'message' => "Файл успешно импортирован как blf.conf"
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => "Ошибка при сохранении файла."
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => "Ошибка загрузки файла."
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => "Неверный метод запроса."
    ]);
}
?>