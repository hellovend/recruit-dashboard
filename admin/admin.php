<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../includes/csrf.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_require();
    require_once __DIR__ . '/../config/dbconfig.php';

    $admin_username = trim($_POST['admin_username'] ?? '');
    $admin_password = $_POST['admin_password'] ?? '';
    $admin_ip = trim($_POST['admin_ip'] ?? '');

    if ($conn->connect_error) {
        die("MySQL 연결 실패: " . $conn->connect_error);
    }

    $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO admins (username, password, ip) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $admin_username, $hashed_password, $admin_ip);
    $result = $stmt->execute();

    if ($result) {
        // Include discord_webhook.php to send Discord notification
        include(__DIR__ . '/../config/discord_webhook_admin_added.php');
    } else {
        echo "<aside id='popup'><p>어드민 추가 실패</p></aside>";
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>어드민 추가하기</title>
</head>

<body>
    <form action="admin.php" method="post">
        <?php echo csrf_field(); ?>
        <input type="text" id="admin_username" name="admin_username" placeholder="어드민 아이디" required>
        <input type="password" id="admin_password" name="admin_password" placeholder="어드민 비밀번호" required>
        <input type="text" id="admin_ip" name="admin_ip" placeholder="접속 가능 IP" required>

        <input type="submit" value="어드민 추가하기">
    </form>
</body>

</html>
