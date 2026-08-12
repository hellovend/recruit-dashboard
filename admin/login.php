<?php
session_start();

// (선택) 디버깅용 MySQL 에러 리포트 - 필요 없으면 주석 처리하세요.
// mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 서비스 상태: 0=정상, 1=점검, 2=종료
$service_status = 0; // 1=점검, 2=종료 테스트 가능

// 서비스 상태 체크
if ($service_status === 1) {
    header("Location: pages/maintenance.html");
    exit();
} elseif ($service_status === 2) {
    header("Location: pages/service_ended.html");
    exit();
}

// DB 연결 설정
require_once __DIR__ . '/../config/dbconfig.php';
require_once __DIR__ . '/../includes/csrf.php';

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// IP 차단 여부 확인
$stmt = $conn->prepare("SELECT id FROM blocked_ips WHERE ip_address = ?");
$stmt->bind_param("s", $ip);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    // IP가 차단되어 있으면 ipblock.html로 이동
    header("Location: /pages/ipblock.html");
    exit(); // 반드시 exit()로 스크립트 종료
}

// 이미 로그인 상태면 index.php
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_require();

    // 공백 제거
    $input_username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $input_password = isset($_POST['password']) ? trim($_POST['password']) : '';

    // 간단한 입력 검사
    if ($input_username === '' || $input_password === '') {
        echo "<aside id='popup'><p>아이디와 비밀번호를 입력해주세요.</p></aside>";
    } else {
        // SQL 인젝션 패턴 감지 (간단 체크)
        $pattern = "/('|\"|--|;|\/\*|\*\/)/";
        if (preg_match($pattern, $input_username) || preg_match($pattern, $input_password)) {
            $ins = $conn->prepare("INSERT INTO blocked_ips (ip_address) VALUES (?)");
            $ins->bind_param("s", $ip);
            $ins->execute();

            // IP 저장 후 알림
            echo "<aside id='popup'><p>인젝션 공격이 확인되었습니다. IP: " . htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') . "</p></aside>";
            unset($input_password);
            exit();
        }

        // 사용자 조회 (Prepared Statement)
        $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $input_username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $row = $result->fetch_assoc();

            if (password_verify($input_password, $row['password'])) {
                $_SESSION['logged_in'] = true;
                $_SESSION['user_id'] = $row['id'];
                unset($input_password);

                // Discord 알림 (파일 내부 구현이 있어야 합니다)
                if (file_exists(__DIR__ . "/config/discord_webhook.php")) {
                    include(__DIR__ . "/config/discord_webhook.php");
                }

                header("Location: index.php");
                exit();
            } else {
                echo "<aside id='popup'><p>아이디 또는 비밀번호가 잘못되었습니다.</p></aside>";
                unset($input_password);
            }
        } else {
            echo "<aside id='popup'><p>아이디 또는 비밀번호가 잘못되었습니다.</p></aside>";
            unset($input_password);
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- <script>
        (function(){
            var t = localStorage.getItem("dm-theme") ||
                    (window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light");
            document.documentElement.setAttribute("data-theme", t);
        })();
    </script> -->
    <link rel="stylesheet" href="../assets/css/login.css">
    <link rel="stylesheet" href="../assets/css/darkmode.css">
    <style>
        /* login.php 다크모드 보완 */
        html, body {
            width: 100%;
            height: 100%;
            background-color: var(--bg-primary);
        }
    </style>
    <title>SamSam 인사팀 로그인</title>
</head>
<body>
    <div id="wrap">
        <h1>
            <a href="#"><img src="../assets/logo/logo.png" alt="SamSam 로고"></a>
        </h1>
        <h2>인사팀 로그인</h2>
        <p>로그인을 해서 합격자 시스템을 이용해보세요!</p>

        <?php
        // 서비스 상태 배너
        if ($service_status === 1) {
            echo "<aside id='popup'><p>현재 서비스 점검 중입니다.</p></aside>";
        } elseif ($service_status === 2) {
            echo "<aside id='popup'><p>현재 서비스가 종료되었습니다.</p></aside>";
        }
        ?>

        <form action="login.php" method="post" autocomplete="off">
            <?php echo csrf_field(); ?>
            <input type="text" id="username" name="username" placeholder="아이디" required>
            <input type="password" id="password" name="password" placeholder="비밀번호" required>
            <input type="submit" value="로그인">
        </form>
    </div>
    <script src="../assets/script/darkmode.js"></script>
</body>
</html>