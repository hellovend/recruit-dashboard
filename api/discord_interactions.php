<?php
// Discord가 버튼 클릭 시 호출하는 공개 엔드포인트.
// 인증은 세션이 아니라 Ed25519 서명 검증 + 관리자 Discord ID 화이트리스트로 처리한다.
require_once __DIR__ . '/../config/discord_bot_config.php';
require_once __DIR__ . '/../config/dbconfig.php';
require_once __DIR__ . '/../includes/email_sender.php';

$rawBody = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_SIGNATURE_ED25519'] ?? '';
$timestamp = $_SERVER['HTTP_X_SIGNATURE_TIMESTAMP'] ?? '';

if (!$signature || !$timestamp || !$discordPublicKey) {
    http_response_code(401);
    exit();
}

$verified = sodium_crypto_sign_verify_detached(
    sodium_hex2bin($signature),
    $timestamp . $rawBody,
    sodium_hex2bin($discordPublicKey)
);

if (!$verified) {
    http_response_code(401);
    exit();
}

$interaction = json_decode($rawBody, true);
header('Content-Type: application/json');

// PING
if (($interaction['type'] ?? null) === 1) {
    echo json_encode(['type' => 1]);
    exit();
}

// MESSAGE_COMPONENT (버튼 클릭)
if (($interaction['type'] ?? null) === 3) {
    $userId = $interaction['member']['user']['id'] ?? ($interaction['user']['id'] ?? null);
    $username = $interaction['member']['user']['username'] ?? ($interaction['user']['username'] ?? '알수없음');

    if (!$userId || !in_array($userId, $discordAdminIds, true)) {
        echo json_encode([
            'type' => 4, // CHANNEL_MESSAGE_WITH_SOURCE
            'data' => ['content' => '⛔ 이 작업은 관리자만 가능합니다.', 'flags' => 64], // ephemeral
        ]);
        exit();
    }

    $parts = array_pad(explode(':', $interaction['data']['custom_id'] ?? ''), 3, null);
    [$prefix, $action, $uniqueNumber] = $parts;

    $statusMap = ['approve' => 'passed', 'reject' => 'failed', 'pending' => 'pending'];

    if ($prefix !== 'pass_result' || !$uniqueNumber || !isset($statusMap[$action])) {
        http_response_code(400);
        exit();
    }

    $passStatus = $statusMap[$action];

    $stmt = $conn->prepare("UPDATE exam_results SET pass_status = ? WHERE unique_number = ?");
    $stmt->bind_param("ss", $passStatus, $uniqueNumber);
    $stmt->execute();

    // 합격/불합격이면 저장된 이메일로 결과 메일 발송
    if ($email_enabled && in_array($passStatus, ['passed', 'failed'], true)) {
        $emailStmt = $conn->prepare("SELECT email, nickname FROM exam_results WHERE unique_number = ?");
        $emailStmt->bind_param("s", $uniqueNumber);
        $emailStmt->execute();
        $emailRow = $emailStmt->get_result()->fetch_assoc();

        if ($emailRow && $emailRow['email']) {
            $toName = $emailRow['nickname'] ?: $uniqueNumber;
            if (sendResultEmail($emailRow['email'], $toName, $uniqueNumber, $passStatus)) {
                $markSent = $conn->prepare("UPDATE exam_results SET email_sent_at = NOW() WHERE unique_number = ?");
                $markSent->bind_param("s", $uniqueNumber);
                $markSent->execute();
            }
        }
    }

    $statusText = ['passed' => '합격', 'failed' => '불합격', 'pending' => '보류'][$passStatus];

    // 원본 메시지를 갱신 — 버튼을 처리 결과 텍스트로 교체
    echo json_encode([
        'type' => 7, // UPDATE_MESSAGE
        'data' => [
            'flags' => 32768, // IS_COMPONENTS_V2
            'components' => [[
                'type' => 17,
                'components' => [[
                    'type' => 10,
                    'content' => "**지원 결과 처리 완료**\n고유번호: `{$uniqueNumber}`\n상태: {$statusText}\n처리자: {$username}",
                ]],
            ]],
        ],
    ]);
    exit();
}

http_response_code(400);
