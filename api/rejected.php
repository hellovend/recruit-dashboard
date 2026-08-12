<?php
session_start();

require_once __DIR__ . '/../config/dbconfig.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../includes/csrf.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_require();
    if (isset($_POST['rejected_number'])) {
        $rejectedNumber = $_POST['rejected_number'];

        $stmt = $conn->prepare("INSERT INTO rejected_applicants (unique_number) VALUES (?)");
        $stmt->bind_param("s", $rejectedNumber);

        if ($stmt->execute()) {
            echo "<aside id='popup'><p>지원불가자가 추가되었습니다.</p></aside>";
        } else {
            echo "<aside id='popup'><p>오류 발생: " . $conn->error . "</p></aside>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/registration.css">
    <title>SamSam 지원불가자 추가</title>
</head>

<body>
    <div id="wrap">
        <!-- <h1><a href="#"><img src="../assets/logo/logo.png" alt="엔젤 서버 로고"></a></h1> -->
        <h2>SamSam 지원불가자 추가</h2>
        <p>지원불가자의 고유번호를 입력 후 추가하세요!</p>
        <form action="rejected.php" method="post">
            <?php echo csrf_field(); ?>
            <input type="number" id="rejected_number" name="rejected_number" placeholder="지원불가자 고유번호" required><br>
            <input type="submit" value="지원불가자 추가">
        </form>
    </div>
</body>

</html>
