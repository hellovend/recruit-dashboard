<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    exit();
}

require_once __DIR__ . '/../config/dbconfig.php';

if ($conn->connect_error) { die("연결 실패: " . $conn->connect_error); }

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
set_time_limit(0);

function fetch_lists($conn) {
    $lists = ['pass' => [], 'failed' => [], 'denied' => []];

    $res = $conn->query("SELECT unique_number FROM exam_results WHERE pass_status='passed'");
    while ($row = $res->fetch_assoc()) $lists['pass'][] = $row['unique_number'];

    $res = $conn->query("SELECT unique_number FROM exam_results WHERE pass_status='failed'");
    while ($row = $res->fetch_assoc()) $lists['failed'][] = $row['unique_number'];

    $res = $conn->query("SELECT unique_number FROM notpassed_candidates");
    while ($row = $res->fetch_assoc()) $lists['denied'][] = $row['unique_number'];

    return $lists;
}

$lastHash = null;

// ponytail: 워커를 무한 점유하지 않도록 150회(~5분) 후 스스로 종료 — 브라우저가 자동 재연결
for ($i = 0; $i < 150; $i++) {
    if (connection_aborted()) break;

    $json = json_encode(fetch_lists($conn));
    $hash = md5($json);

    if ($hash !== $lastHash) {
        echo "data: $json\n\n";
        @ob_flush();
        flush();
        $lastHash = $hash;
    }

    sleep(2);
}

$conn->close();
