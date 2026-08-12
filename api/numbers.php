<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../config/dbconfig.php';

if ($conn->connect_error) { die("연결 실패: " . $conn->connect_error); }

// 합격자
$sql_pass = "SELECT unique_number FROM exam_results WHERE pass_status='passed'";
$res_pass = $conn->query($sql_pass);
$pass = [];
if($res_pass->num_rows>0){
    while($row=$res_pass->fetch_assoc()) $pass[] = $row['unique_number'];
}

// 불합격자
$sql_failed = "SELECT unique_number FROM exam_results WHERE pass_status='failed'";
$res_failed = $conn->query($sql_failed);
$failed = [];
if($res_failed->num_rows>0){
    while($row=$res_failed->fetch_assoc()) $failed[] = $row['unique_number'];
}

// 지원불가자
$sql_denied = "SELECT unique_number FROM notpassed_candidates";
$res_denied = $conn->query($sql_denied);
$denied = [];
if($res_denied->num_rows>0){
    while($row=$res_denied->fetch_assoc()) $denied[] = $row['unique_number'];
}

$conn->close();

function render_list($list) {
    if (empty($list)) return '<li class="empty">없음</li>';
    return implode('', array_map(fn($n) => '<li>' . htmlspecialchars($n) . '</li>', $list));
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>실시간 고유번호 확인</title>
<link rel="stylesheet" href="../assets/css/darkmode.css">
<style>
body { font-family: sans-serif; max-width: 900px; margin: 40px auto; padding: 0 20px; }
h2 { text-align: center; margin-bottom: 32px; }

.board { display: flex; flex-wrap: wrap; gap: 16px; }

.card {
    flex: 1 1 240px;
    background: var(--bg-card);
    border: 1px solid var(--border-color-soft);
    border-radius: 10px;
    overflow: hidden;
}

.card h3 {
    margin: 0;
    padding: 12px 16px;
    background: var(--bg-table-header);
    color: var(--text-table-header);
    font-size: 15px;
    display: flex;
    justify-content: space-between;
}

.card ul {
    list-style: none;
    margin: 0;
    padding: 0;
    max-height: 400px;
    overflow-y: auto;
}

.card li {
    padding: 8px 16px;
    border-top: 1px solid var(--border-color-soft);
}

.card li.empty {
    color: var(--text-secondary);
    text-align: center;
}
</style>
</head>
<body>

<h2>실시간 고유번호 확인</h2>

<div class="board">
    <div class="card" id="pass-card">
        <h3>합격자 <span id="pass-count"><?php echo count($pass); ?></span></h3>
        <ul id="pass-list"><?php echo render_list($pass); ?></ul>
    </div>

    <div class="card" id="failed-card">
        <h3>불합격자 <span id="failed-count"><?php echo count($failed); ?></span></h3>
        <ul id="failed-list"><?php echo render_list($failed); ?></ul>
    </div>

    <div class="card" id="denied-card">
        <h3>지원불가자 <span id="denied-count"><?php echo count($denied); ?></span></h3>
        <ul id="denied-list"><?php echo render_list($denied); ?></ul>
    </div>
</div>

<script src="../assets/script/darkmode.js"></script>
<script>
// 서버가 값이 바뀔 때만 밀어주는 SSE 방식 (폴링/새로고침 없음)
function renderList(key, list) {
    document.getElementById(`${key}-count`).textContent = list.length;
    const ul = document.getElementById(`${key}-list`);
    ul.replaceChildren();
    if (!list.length) {
        const li = document.createElement('li');
        li.className = 'empty';
        li.textContent = '없음';
        ul.appendChild(li);
        return;
    }
    for (const n of list) {
        const li = document.createElement('li');
        li.textContent = n;
        ul.appendChild(li);
    }
}

// 서버 쪽 스트림은 일정 시간 후 스스로 종료됨(ponytail: PHP-FPM 워커 점유 방지)
// EventSource는 연결이 끊기면 브라우저가 알아서 재연결하므로 별도 재시도 로직 불필요
const es = new EventSource('numbers_stream.php');
es.onmessage = (e) => {
    const data = JSON.parse(e.data);
    renderList('pass', data.pass);
    renderList('failed', data.failed);
    renderList('denied', data.denied);
};
</script>

</body>
</html>
