<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
        $valid_data = [];
        foreach ($data as $subscriber) {
            if (isset($subscriber['number']) && isset($subscriber['status'])) {
                $number = filter_var($subscriber['number'], FILTER_VALIDATE_INT);
                $status = filter_var($subscriber['status'], FILTER_VALIDATE_BOOLEAN);

                if ($number !== false && $status !== null) {
                    $valid_data[] = [
                        'number' => $number,
                        'status' => $status
                    ];
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid number or status value']);
                    exit;
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required parameters']);
                exit;
            }
        }

        $file = '../api_config/account_statuses.json';
        
        // Clear the file before writing new data
        file_put_contents($file, '');

        // Write new data to the file
        file_put_contents($file, json_encode($valid_data, JSON_PRETTY_PRINT));
        http_response_code(200);
        echo json_encode(['message' => 'Account statuses updated successfully']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $file = '../api_config/account_statuses.json';
    if (file_exists($file)) {
        $data = file_get_contents($file);
        header('Content-Type: application/json');
        echo $data;
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Resource not found']);
    }
}
?>
