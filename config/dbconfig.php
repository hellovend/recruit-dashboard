<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rlagusdn143";

// 운영 자격증명은 git에 커밋되지 않는 dbconfig.local.php에서 덮어씀
$localConfig = __DIR__ . '/dbconfig.local.php';
if (file_exists($localConfig)) {
    require $localConfig;
}

// 데이터베이스 연결
$conn = new mysqli($servername, $username, $password, $dbname);

// 연결 오류 체크
if ($conn->connect_error) {
    die("MySQL 연결 실패: " . $conn->connect_error);
}

// 편의를 위해 mysqli 객체를 $mysqli로도 사용할 수 있습니다.
$mysqli = $conn;
?>

