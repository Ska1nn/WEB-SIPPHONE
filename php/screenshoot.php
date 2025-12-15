<?php
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = new stdClass();
    $version = shell_exec("/opt/cumanphone/bin/CumanPhone --version");
    if (!empty($version) && str_contains($version, 'CumanPhone version: ')) {
        $data->version = substr(trim($version), 20);
    }
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $contents = file_get_contents('php://input');
    $data = json_decode($contents);

    if (!is_object($data) || ($data->command ?? '') !== 'screenshooter') {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid request'
        ]);
        exit;
    }

    // 🔐 LOCK только для скриншота
    $lockFile = '/tmp/screenshooter.lock';
    $lock = fopen($lockFile, 'w');

    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Screenshooter already running'
        ]);
        exit;
    }

    putenv('WAYLAND_DISPLAY=wayland-0');
    putenv('XDG_RUNTIME_DIR=/run/user/0');

    exec("/usr/bin/weston-screenshooter", $output, $retval);

    if ($retval !== 0) {
        flock($lock, LOCK_UN);
        fclose($lock);

        echo json_encode([
            'status' => 'error',
            'message' => implode("\n", $output)
        ]);
        exit;
    }

    // берём ТОЛЬКО последний файл
    exec("ls -t /srv/www/php/wayland-screenshot-*.png | head -n 1", $files);

    if (empty($files[0])) {
        flock($lock, LOCK_UN);
        fclose($lock);

        echo json_encode([
            'status' => 'error',
            'message' => 'Screenshot file not found'
        ]);
        exit;
    }

    exec(
        "mv " . escapeshellarg($files[0]) . " /opt/cumanphone/share/images/",
        $move_output,
        $move_retval
    );

    flock($lock, LOCK_UN);
    fclose($lock);

    if ($move_retval !== 0) {
        echo json_encode([
            'status' => 'error',
            'message' => implode("\n", $move_output)
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Screenshot captured successfully'
    ]);
    exit;
}
