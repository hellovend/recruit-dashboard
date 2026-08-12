<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../includes/csrf.php';
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_require();
}

if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../config/dbconfig.php';
require_once __DIR__ . '/../includes/email_sender.php';

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("MySQL 연결 실패: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $uniqueNumber = $_POST['unique_number'];
    $passStatus = $_POST['pass_status'];

    if (!in_array($passStatus, ['passed', 'failed', 'notpassed'])) {
        die("유효하지 않은 값입니다.");
    }

    // =========================
    // notpassed 처리
    // =========================
    if ($passStatus === 'notpassed') {

        $check = $conn->prepare("SELECT unique_number FROM notpassed_candidates WHERE unique_number = ?");
        $check->bind_param("s", $uniqueNumber);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            echo "<aside id='popup'><p>이미 지원불가자입니다.</p></aside>";
        } else {

            $insert = $conn->prepare("INSERT INTO notpassed_candidates (unique_number) VALUES (?)");
            $insert->bind_param("s", $uniqueNumber);

            if ($insert->execute()) {

                // 디스코드
                $webhook = "YOUR_WEBHOOK_URL";

                $data = [
                    'content' => "```인사팀에게 알려드립니다!```",
                    'embeds' => [[
                        'title' => "고유번호: $uniqueNumber",
                        'description' => "합격여부: 지원불가자",
                        'color' => 16776960
                    ]]
                ];

                sendDiscord($webhook, $data);

                echo "<aside id='popup'><p>지원불가자 등록 완료</p></aside>";
            }
        }
    }

    // =========================
    // exam_results (핵심 UPsert)
    // =========================

    $check = $conn->prepare("SELECT unique_number FROM exam_results WHERE unique_number = ?");
    $check->bind_param("s", $uniqueNumber);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {

        // 🔥 UPDATE (이미 존재)
        $update = $conn->prepare("UPDATE exam_results SET pass_status = ? WHERE unique_number = ?");
        $update->bind_param("ss", $passStatus, $uniqueNumber);
        $update->execute();

        echo "<aside id='popup'><p>기존 데이터 업데이트 완료</p></aside>";

    } else {

        // 🔥 INSERT (새 데이터)
        $insert = $conn->prepare("INSERT INTO exam_results (unique_number, pass_status) VALUES (?, ?)");
        $insert->bind_param("ss", $uniqueNumber, $passStatus);
        $insert->execute();

        echo "<aside id='popup'><p>새 데이터 등록 완료</p></aside>";
    }

    // =========================
    // 이메일 발송 (합격/불합격만, 접수 시 저장된 이메일이 있을 때)
    // =========================
    if ($email_enabled && in_array($passStatus, ['passed', 'failed'])) {
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
                echo "<aside id='popup-email'><p>✉️ 이메일 발송 완료</p></aside>";
            }
        }
    }

    // =========================
    // 디스코드 알림
    // =========================
    $webhook = "YOUR_WEBHOOK_URL";

    $statusText = match($passStatus) {
        'passed' => '합격',
        'failed' => '불합격',
        default => '지원불가자'
    };

    $color = match($passStatus) {
        'passed' => 32768,
        'failed' => 16711680,
        default => 16776960
    };

    $data = [
        'content' => "```인사팀에게 알려드립니다!```",
        'embeds' => [[
            'title' => "고유번호: $uniqueNumber",
            'description' => "합격여부: $statusText",
            'color' => $color
        ]]
    ];

    sendDiscord($webhook, $data);

    $conn->close();
}

// =========================
// Discord 함수
// =========================
function sendDiscord($url, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}
?>

<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/darkmode.css">
    <link rel="stylesheet" href="../assets/css/registration.css">
    <title>SamSam 합격자 등록</title>
    <style>
        /* 공지 관리 링크 버튼 */
        .notice-link {
            display: block;
            text-align: center;
            margin-top: 10px;
            font: bold 12px "SpoqaHanSansNeo-Regular", sans-serif;
            color: var(--accent);
            text-decoration: none;
            padding: 6px 0;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            transition: background 0.2s;
        }
        .notice-link:hover { background: var(--bg-secondary); }

        /* 이메일 발송 완료 팝업 */
        #popup-email {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            background: #22c55e;
            color: #fff;
            padding: 10px 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.15);
            border-radius: 4px;
            font-weight: bold;
            font-size: 13px;
            animation: fadeOutEmailPopup 0.5s ease 3s forwards;
        }
        @keyframes fadeOutEmailPopup {
            from { opacity: 1; }
            to   { opacity: 0; pointer-events: none; }
        }
    </style>
</head>

<body>
    <div id="wrap">
        <h1><a href="#"><img src="../assets/logo/logo.png" alt="logo"></a></h1>

        <h2>합격자 등록</h2>
        <p>고유번호와 합격여부를 입력 후 등록하세요!</p>

        <form method="post">
            <?php echo csrf_field(); ?>
            <input type="number" name="unique_number" placeholder="고유번호" required><br>

            <select name="pass_status" required>
                <option value="passed">합격</option>
                <option value="failed">불합격</option>
                <option value="notpassed">지원불가자</option>
            </select><br>

            <input type="submit" value="결과 등록">
        </form>

        <a href="notices.php" class="notice-link">📣 공지 관리</a>

        <form method="post">
            <?php echo csrf_field(); ?>
            <input type="submit" name="logout" value="로그아웃">
        </form>
    </div>
    <script src="../assets/script/darkmode.js"></script>
</body>

</html>
