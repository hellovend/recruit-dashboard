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

if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST['logout'])) {
    require_once __DIR__ . '/../config/dbconfig.php';

    $uniqueNumberToDelete = $_POST['unique_number_to_delete'];

    if ($conn->connect_error) {
        die("MySQL 연결 실패: " . $conn->connect_error);
    }

    $stmt = $conn->prepare("DELETE FROM exam_results WHERE unique_number = ?");
    $stmt->bind_param("s", $uniqueNumberToDelete);

    if ($stmt->execute()) {
        echo "<aside id='popup'><p>해당 고유번호($uniqueNumberToDelete)를 데이터베이스에서 성공적으로 삭제처리가 되었습니다.</p></aside>";
    } else {
        echo "<aside id='popup'><p>해당 고유번호($uniqueNumberToDelete)는 데이터베이스에서 삭제하는 중에 문제가 발생하여 삭제요청이 취소되었습니다. 다시 시도해주세요.</p></aside>";
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        (function(){
            var t = localStorage.getItem("dm-theme") ||
                    (window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light");
            document.documentElement.setAttribute("data-theme", t);
        })();
    </script>
    <link rel="stylesheet" href="../assets/css/darkmode.css">
    <link rel="stylesheet" href="../assets/css/delete.css">
    <title>SamSam 서버  고유번호 삭제</title>

    <script>
    // 페이지 로드가 완료되면 실행
    window.onload = function() {
        var deleteButton = document.getElementById("deleteButton");
        var uniqueNumberInput = document.getElementById("unique_number_to_delete");

        // 1초마다 실행되는 함수
        setInterval(function() {
            // 삭제 버튼의 텍스트를 변경
            deleteButton.value = "고유번호(" + (uniqueNumberInput.value || "0") + ") 삭제하기";
        }, 1);
    };
    </script>
</head>

<body>
    <div id="wrap1">
        <h1><a href="#"><img src="../assets/logo/logo.png" alt="SamSam 서버 로고"></a></h1>
        <h2>SamSam  고유번호 삭제</h2>
        <p>고유번호를 입력 후 삭제하세요!</p>
        <form action="delete.php" method="post">
            <?php echo csrf_field(); ?>
            <input type="number" id="unique_number_to_delete" name="unique_number_to_delete" placeholder="삭제할 고유번호" required><br>
            <input type="submit" value="고유번호 삭제하기" id="deleteButton">
        </form>

        <form action="" method="post">
            <?php echo csrf_field(); ?>
            <input type="submit" name="logout" value="로그아웃하기">
        </form>
    </div>
    <script src="../assets/script/darkmode.js"></script>
</body>

</html>
