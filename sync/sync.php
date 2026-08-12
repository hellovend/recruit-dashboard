<?php

// 🔥 에러 표시 (개발용)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 🔥 DB / Discord / 이메일 설정
include '../config/dbconfig.php';
require_once '../includes/discord_bot.php';
require_once '../includes/email_sender.php';

// 🔥 요청 데이터 받기
$input = file_get_contents("php://input");
file_put_contents("log.txt", $input . "\n", FILE_APPEND);

// JSON 파싱
$data = json_decode($input, true);

if (!$data) {
    die("JSON 파싱 실패");
}

// 🔥 데이터 추출
$uniqueNumber = $data['unique_number'] ?? null;
$nickname = $data['nickname'] ?? null;
$age = $data['age'] ?? null;
$email = $data['email'] ?? null;
if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $email = null; // 형식이 잘못됐으면 조용히 무시
}

// 🔥 서버 기준 시간 (조작 방지)
$registeredAt = date("Y-m-d H:i:s");

// 값 검증
if (!$uniqueNumber || !$nickname) {
    die("값 부족");
}

// =========================
// 🔥 중복 확인 (exam_results)
// =========================
$stmt = $conn->prepare("SELECT id FROM exam_results WHERE unique_number = ?");
$stmt->bind_param("s", $uniqueNumber);
$stmt->execute();
$result = $stmt->get_result();

// =========================
// 🔥 INSERT (없을 때만)
// =========================
if ($result->num_rows === 0) {

    $passStatus = "pending"; // ENUM 값

    $insert = $conn->prepare("
        INSERT INTO exam_results 
        (unique_number, pass_status, nickname, email, registered_at)
        VALUES (?, ?, ?, ?, ?)
    ");

    $insert->bind_param("sssss", $uniqueNumber, $passStatus, $nickname, $email, $registeredAt);

    if ($insert->execute()) {

        // =========================
        // 🔥 Discord 봇 알림 (합격/불합격/보류 버튼 포함, 관리자만 클릭 가능)
        // =========================
        send_applicant_bot_message($discordChannelId, $discordBotToken, $uniqueNumber, $nickname);

        // =========================
        // 🔥 접수 완료 이메일 발송
        // =========================
        if ($email_enabled && $email) {
            if (sendRegistrationEmail($email, $nickname, $uniqueNumber)) {
                $markSent = $conn->prepare("UPDATE exam_results SET email_sent_at = NOW() WHERE unique_number = ?");
                $markSent->bind_param("s", $uniqueNumber);
                $markSent->execute();
            }
        }

        echo "OK";

    } else {
        echo "DB 저장 실패";
    }

} else {
    echo "이미 존재";
}

$conn->close();

?>
