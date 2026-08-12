<?php
/**
 * URL 단축 API
 * 원본 URL을 받아 짧은 코드로 변환하고 저장
 * 
 * 사용법:
 * POST /api/shorten.php
 * {
 *   "url": "https://domain.com/api/check.php?number=12345&name=test",
 *   "expires_in_days": 30  // 선택사항, 기본값 null (무제한)
 * }
 * 
 * 응답:
 * {
 *   "success": true,
 *   "short_code": "abc123",
 *   "short_url": "https://domain.com/r/abc123",
 *   "original_url": "https://domain.com/api/check.php?number=12345"
 * }
 */

// 자동 로드 및 설정
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Content-Type 설정
header('Content-Type: application/json; charset=utf-8');

// 데이터베이스 연결
require_once '../config/dbconfig.php';

$response = array('success' => false, 'message' => '');

try {
    // 요청 방식 확인
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('POST 요청만 허용됩니다.');
    }

    // JSON 데이터 읽기
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['url'])) {
        http_response_code(400);
        throw new Exception('url 파라미터가 필수입니다.');
    }

    $original_url = trim($input['url']);
    $expires_in_days = isset($input['expires_in_days']) ? intval($input['expires_in_days']) : null;

    // URL 유효성 검사
    if (!filter_var($original_url, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        throw new Exception('유효하지 않은 URL입니다.');
    }

    // 데이터베이스 연결
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception('데이터베이스 연결 실패: ' . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");

    // 중복된 URL이 있는지 확인
    $stmt = $conn->prepare("SELECT short_code FROM url_shorts WHERE original_url = ? AND (expires_at IS NULL OR expires_at > NOW())");
    if (!$stmt) {
        throw new Exception('쿼리 준비 실패: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $original_url);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // 이미 단축된 URL이 있으면 그것을 반환
        $row = $result->fetch_assoc();
        $response['success'] = true;
        $response['short_code'] = $row['short_code'];
        $response['short_url'] = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/r/' . $row['short_code'];
        $response['original_url'] = $original_url;
        $response['message'] = '기존 단축 URL을 반환합니다.';
        $stmt->close();
        $conn->close();
        http_response_code(200);
        echo json_encode($response);
        exit;
    }
    $stmt->close();

    // 새로운 단축 코드 생성 (충돌 피하기 위해 반복)
    $short_code = '';
    $max_attempts = 10;
    $attempt = 0;
    
    while ($attempt < $max_attempts) {
        $short_code = generateShortCode();
        
        // 중복 확인
        $stmt = $conn->prepare("SELECT id FROM url_shorts WHERE short_code = ?");
        if (!$stmt) {
            throw new Exception('쿼리 준비 실패: ' . $conn->error);
        }
        $stmt->bind_param("s", $short_code);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        if ($result->num_rows === 0) {
            break; // 중복이 없으면 사용 가능
        }
        $attempt++;
    }

    if ($attempt >= $max_attempts) {
        http_response_code(500);
        throw new Exception('단축 코드 생성 실패: 재시도 한계 도달');
    }

    // 만료 날짜 계산
    $expires_at = null;
    if ($expires_in_days !== null && $expires_in_days > 0) {
        $expires_at = date('Y-m-d H:i:s', strtotime("+$expires_in_days days"));
    }

    // 데이터베이스에 저장
    if ($expires_at) {
        $stmt = $conn->prepare("INSERT INTO url_shorts (short_code, original_url, expires_at) VALUES (?, ?, ?)");
        if (!$stmt) {
            throw new Exception('쿼리 준비 실패: ' . $conn->error);
        }
        $stmt->bind_param("sss", $short_code, $original_url, $expires_at);
    } else {
        $stmt = $conn->prepare("INSERT INTO url_shorts (short_code, original_url) VALUES (?, ?)");
        if (!$stmt) {
            throw new Exception('쿼리 준비 실패: ' . $conn->error);
        }
        $stmt->bind_param("ss", $short_code, $original_url);
    }

    if (!$stmt->execute()) {
        http_response_code(500);
        throw new Exception('URL 단축 저장 실패: ' . $stmt->error);
    }
    $stmt->close();
    $conn->close();

    // 성공 응답
    $response['success'] = true;
    $response['short_code'] = $short_code;
    $response['short_url'] = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/r/' . $short_code;
    $response['original_url'] = $original_url;
    $response['message'] = '단축 URL이 생성되었습니다.';
    http_response_code(201);

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    if (empty($response['message'])) {
        $response['message'] = '알 수 없는 오류가 발생했습니다.';
    }
}

echo json_encode($response);

/**
 * 무작위 단축 코드 생성 함수
 * 길이: 6자 (대소문자 + 숫자)
 */
function generateShortCode($length = 6) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $code;
}
?>
