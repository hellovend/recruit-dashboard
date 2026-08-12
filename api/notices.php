<?php
/**
 * api/notices.php
 * 활성 공지 목록을 JSON으로 반환합니다.
 * check.php / 조회 화면에서 fetch()로 호출합니다.
 *
 * GET /api/notices.php        → 현재 활성 공지 전체
 * GET /api/notices.php?first=1 → 최우선 공지 1건만
 */
require_once __DIR__ . '/../config/dbconfig.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$onlyFirst = isset($_GET['first']) && $_GET['first'] === '1';

$sql = "SELECT id, title, content, type
        FROM notices
        WHERE is_active = 1
          AND (starts_at IS NULL OR starts_at <= NOW())
          AND (ends_at   IS NULL OR ends_at   >= NOW())
        ORDER BY id DESC";

if ($onlyFirst) {
    $sql .= " LIMIT 1";
}

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'DB 오류']);
    exit;
}

$notices = [];
while ($row = $result->fetch_assoc()) {
    $notices[] = [
        'id'      => (int) $row['id'],
        'title'   => $row['title'],
        'content' => $row['content'],
        'type'    => $row['type'],
    ];
}

echo json_encode([
    'success' => true,
    'notices' => $notices,
]);
$conn->close();

