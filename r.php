<?php
/**
 * 단축 URL 리다이렉트
 * 짧은 코드를 받아 원본 URL로 리다이렉트
 * 
 * 사용법:
 * GET /r/abc123
 * 또는 GET /r.php?code=abc123
 * 
 * 동작:
 * 1. 단축 코드를 조회
 * 2. 유효하고 만료되지 않은 경우 원본 URL로 리다이렉트
 * 3. 클릭 횟수 증가
 * 4. 만료되거나 없으면 404 에러
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

// 데이터베이스 연결
require_once 'config/dbconfig.php';

// 단축 코드 추출
$short_code = '';

// URL 경로에서 코드 추출 (/r/abc123 형태)
if (!empty($_GET['code'])) {
    $short_code = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['code']);
} else {
    // PATH_INFO 사용 (/r.php/abc123 또는 RewriteRule로 /r/abc123)
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $parts = explode('/', rtrim($path, '/'));
    if (!empty($parts)) {
        $short_code = preg_replace('/[^a-zA-Z0-9]/', '', end($parts));
    }
}

if (empty($short_code)) {
    header('HTTP/1.1 400 Bad Request');
    die('단축 코드가 없습니다.');
}

try {
    // 데이터베이스 연결
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception('데이터베이스 연결 실패');
    }
    $conn->set_charset("utf8mb4");

    // 단축 코드로 원본 URL 조회
    $stmt = $conn->prepare("SELECT id, original_url, expires_at FROM url_shorts WHERE short_code = ? AND (expires_at IS NULL OR expires_at > NOW())");
    if (!$stmt) {
        throw new Exception('쿼리 준비 실패');
    }
    
    $stmt->bind_param("s", $short_code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // 단축 코드가 없거나 만료됨
        header('HTTP/1.1 404 Not Found');
        $conn->close();
        die('해당 단축 URL을 찾을 수 없거나 만료되었습니다.');
    }

    $row = $result->fetch_assoc();
    $original_url = $row['original_url'];
    $url_id = $row['id'];

    // 클릭 횟수 증가 (비동기로 처리해도 됨)
    $update_stmt = $conn->prepare("UPDATE url_shorts SET click_count = click_count + 1 WHERE id = ?");
    if ($update_stmt) {
        $update_stmt->bind_param("i", $url_id);
        $update_stmt->execute();
        $update_stmt->close();
    }

    $stmt->close();
    $conn->close();

    // 원본 URL로 리다이렉트
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $original_url);
    exit;

} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    die('오류: ' . $e->getMessage());
}
?>
