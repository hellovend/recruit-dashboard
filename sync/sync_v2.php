<?php
/**
 * sync/sync.php  (v2 — 이메일 수신 + 접수 안내 이메일 발송)
 *
 * Google Apps Script 에서 POST JSON 으로 호출됩니다.
 *
 * 기존 필드:
 *   timestamp, nickname, unique_number, age
 *
 * 추가 필드 (선택):
 *   email  — 지원자 이메일 (구글 폼에서 수집)
 *
 * 변경된 Apps Script 예시:
 *   var data = {
 *     timestamp:     new Date().toISOString(),
 *     nickname:      itemResponses[0].getResponse(),
 *     unique_number: itemResponses[1].getResponse(),
 *     age:           itemResponses[2].getResponse(),
 *     email:         itemResponses[3].getResponse()   // ← 추가
 *   };
 */

require_once __DIR__ . '/../config/dbconfig.php';

// ★ 이메일 모듈 (설정 파일이 없으면 조용히 무시)
$emailEnabled    = false;
$emailConfigPath = __DIR__ . '/../config/email_config.php';
$emailSenderPath = __DIR__ . '/../includes/email_sender.php';
if (file_exists($emailConfigPath) && file_exists($emailSenderPath)) {
    require_once $emailSenderPath;
    $emailEnabled = true;
}

header('Content-Type: application/json; charset=utf-8');

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => '잘못된 JSON']);
    exit;
}

$timestamp    = $data['timestamp']     ?? date('Y-m-d H:i:s');
$nickname     = $data['nickname']      ?? '';
$uniqueNumber = $data['unique_number'] ?? '';
$age          = isset($data['age']) ? (int)$data['age'] : null;
$email        = $data['email']         ?? null;   // ★ 신규 필드

if (empty($uniqueNumber) || empty($nickname)) {
    http_response_code(422);
    echo json_encode(['error' => '필수 필드 누락 (unique_number, nickname)']);
    exit;
}

// 이메일 유효성 검사
if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $email = null;   // 형식 오류면 무시
}

// ── 중복 확인 ────────────────────────────────────────────────
$check = $conn->prepare("SELECT id FROM candidates WHERE unique_number = ?");
$check->bind_param("s", $uniqueNumber);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    // 이미 존재하면 이메일만 업데이트 (있을 경우)
    if ($email) {
        $upd = $conn->prepare(
            "UPDATE candidates SET email = ? WHERE unique_number = ?"
        );
        $upd->bind_param("ss", $email, $uniqueNumber);
        $upd->execute();
    }

    echo json_encode(['status' => 'duplicate', 'message' => '이미 등록된 고유번호']);
    $conn->close();
    exit;
}
$check->close();

// ── INSERT ───────────────────────────────────────────────────
$insert = $conn->prepare(
    "INSERT INTO candidates (unique_number, nickname, age, email, created_at, pass_status)
     VALUES (?, ?, ?, ?, ?, 'pending')"
);
$insert->bind_param("ssiss", $uniqueNumber, $nickname, $age, $email, $timestamp);

if (!$insert->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'DB 오류: ' . $conn->error]);
    $conn->close();
    exit;
}

// ── ★ 접수 완료 이메일 발송 ─────────────────────────────────
$emailSent = false;
if ($emailEnabled && $email) {
    $emailSent = sendRegistrationEmail($email, $nickname, $uniqueNumber);

    if ($emailSent) {
        $markSent = $conn->prepare(
            "UPDATE candidates SET email_sent_at = NOW() WHERE unique_number = ?"
        );
        $markSent->bind_param("s", $uniqueNumber);
        $markSent->execute();
    }
}

$conn->close();

echo json_encode([
    'status'     => 'ok',
    'message'    => '등록 완료',
    'email_sent' => $emailSent,
]);

