<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (isset($data['downloading_status']) && isset($data['progress'])) {
        $downloading_status = filter_var($data['downloading_status'], FILTER_VALIDATE_BOOLEAN);

        $allowed_progress_values = [-1, 0, 1, 2, 3, 4, 5, 6, 7];
        $progress = (int)$data['progress'];

        if (in_array($progress, $allowed_progress_values)) {
            $data = [
                'downloading_status' => $downloading_status,
                'progress' => $progress
            ];
            $file = '../api_config/download_status.json';
            file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
            http_response_code(200);
            echo json_encode(['message' => 'Download status updated successfully']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid progress value']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required parameters']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $file = '../api_config/download_status.json';
    if (file_exists($file)) {
        $data = file_get_contents($file);
        if ($data === false || trim($data) === '') {
            // File is empty or cannot be read
            http_response_code(200);
            echo json_encode(['downloading_status' => false, 'progress' => null]);
        } else {
            $json_data = json_decode($data, true);
            if ($json_data === null) {
                // Invalid JSON in file
                http_response_code(200);
                echo json_encode(['downloading_status' => false, 'progress' => null]);
            } else {
                header('Content-Type: application/json');
                echo json_encode($json_data);
            }
        }
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Resource not found']);
    }
}
?>
