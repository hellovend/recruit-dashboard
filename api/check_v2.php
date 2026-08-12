<?php
/**
 * api/check.php  (v2 — 추가 기능 포함)
 *
 * 변경 사항:
 *  1. 지원불가자 차단 — notpassed_candidates 조회 후 pages/blocked.php 로 리다이렉트
 *  2. 공지 배너 — notice-banner.css / notice-banner.js 포함
 *  3. (선택) 이메일 안내 연동 준비 완료
 */
require_once __DIR__ . '/../config/dbconfig.php';

$uniqueNumber = "";
$passStatus   = "미지원 & 등록의 오류";
$passClass    = '';
$passText     = '';

$service_status = 0;   // 0 = 정상, 1 = 점검, 2 = 종료

if ($service_status === 1) {
    header("Location: ../pages/maintenance.html");
    exit();
} elseif ($service_status === 2) {
    header("Location: ../pages/service_ended.html");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $uniqueNumber = $_POST['unique_number'];

    // =========================================================
    // ① 지원불가자 차단 체크  ★ 추가된 기능 ★
    // =========================================================
    $blockStmt = $conn->prepare(
        "SELECT unique_number FROM notpassed_candidates WHERE unique_number = ? LIMIT 1"
    );
    $blockStmt->bind_param("s", $uniqueNumber);
    $blockStmt->execute();
    $blockResult = $blockStmt->get_result();

    if ($blockResult && $blockResult->num_rows > 0) {
        $blockStmt->close();
        $conn->close();

        // pages/blocked.php 로 인클루드하여 $uniqueNumber 변수 전달
        include __DIR__ . '/../pages/blocked.php';
        exit();
    }
    $blockStmt->close();

    // =========================================================
    // ② 합격 여부 조회 (기존 로직 — Prepared Statement)
    // =========================================================
    $stmt = $conn->prepare(
        "SELECT pass_status FROM exam_results WHERE unique_number = ?"
    );
    $stmt->bind_param("s", $uniqueNumber);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {

        $row        = $result->fetch_assoc();
        $passStatus = strtolower(trim($row['pass_status']));

        if ($passStatus === 'passed') {
            $passClass = 'passed';
            $passText  = '합격';

        } elseif ($passStatus === 'failed') {
            $passClass = 'failed';
            $passText  = '불합격';

        } elseif ($passStatus === 'pending') {
            $passClass = 'pending';
            $passText  = '지원서 검토 중';

        } else {
            $passClass = 'error';
            $passText  = '미지원 & 등록의 오류';
        }
    }

    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/check.css">
    <link rel="stylesheet" href="../assets/css/darkmode.css">
    <!-- ★ 공지 배너 CSS (추가 기능) -->
    <link rel="stylesheet" href="../assets/css/notice-banner.css">
    <title>합격자 조회</title>

    <style>
        #wrap2 { margin-top: 20px; }
        #wrap2 .inner {
            position: relative;
            padding: 20px;
            border: 1px solid var(--border-color-soft);
            background: var(--bg-inner);
            max-width: 600px;
        }
        #close-btn {
            position: absolute;
            top: 10px; right: 10px;
            font-size: 24px;
            border: none;
            background: transparent;
            cursor: pointer;
            color: var(--text-primary);
        }
        #close-btn:hover { color: var(--danger); }
        .passed  { color: #22c55e; font-weight: bold; }
        .failed  { color: var(--danger); font-weight: bold; }
        .pending { color: orange; font-weight: bold; }
        .error   { color: gray; font-weight: bold; }
    </style>
</head>

<body>

<!-- 공지 배너 영역 (JS가 자동으로 삽입) -->

<div id="wrap1">
    <h1>
        <a href="#">
            <img src="../assets/logo/logo.png" alt="logo">
        </a>
    </h1>

    <h2>합격자 조회</h2>

    <form method="post">
        <input type="text" name="unique_number" placeholder="고유번호" required
               value="<?php echo htmlspecialchars($uniqueNumber); ?>">
        <input type="submit" value="조회">
    </form>
</div>

<?php if ($_SERVER["REQUEST_METHOD"] === "POST"): ?>
<div id="wrap2">
    <h1>결과</h1>
    <button id="close-btn">&times;</button>

    <div class="inner">
        <table>
            <tr>
                <th>고유번호</th>
                <td><?php echo htmlspecialchars($uniqueNumber); ?></td>
            </tr>
            <tr>
                <th>결과</th>
                <td class="<?php echo $passClass; ?>">
                    <?php echo $passText; ?>
                </td>
            </tr>
        </table>

        <p>
            <?php
            if ($passStatus === 'passed') {
                echo '<span>축하합니다!</span> 면접 일정 DM 전달 바랍니다.';
            } elseif ($passStatus === 'failed') {
                echo '<span>불합격입니다</span> 3일 후 재지원 가능합니다.';
            } elseif ($passStatus === 'pending') {
                echo '<span>검토중</span> 현재 담당 인사팀이 검토 중입니다.';
            } else {
                echo '합격 여부를 확인할 수 없습니다.';
            }
            ?>
        </p>

        <!-- 단축 URL 섹션 -->
        <div id="short-url-section" style="margin-top:20px;padding-top:20px;border-top:1px solid var(--border-color-soft);">
            <h3 style="margin-bottom:10px;">결과 공유</h3>
            <button type="button" id="shorten-btn" class="share-btn" onclick="generateShortURL()">
                단축 URL 생성
            </button>
            <div id="short-url-result" style="margin-top:10px;display:none;">
                <p>단축 URL:</p>
                <div style="display:flex;gap:10px;">
                    <input type="text" id="short-url-input" readonly
                           style="flex:1;padding:8px;border:1px solid var(--border-color-soft);border-radius:4px;">
                    <button type="button" onclick="copyToClipboard()" class="share-btn" style="min-width:80px;">복사</button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- darkmode 먼저, 그 다음 공지 배너 -->
<script src="../assets/script/darkmode.js"></script>
<!-- ★ 공지 배너 JS (추가 기능) — API 경로 명시 -->
<script>
    window.NOTICE_API_PATH = '../api/notices.php';
</script>
<script src="../assets/script/notice-banner.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('close-btn');
    const box = document.getElementById('wrap2');
    if (btn && box) {
        btn.addEventListener('click', () => { box.style.display = 'none'; });
    }
});

async function generateShortURL() {
    const btn = document.getElementById('shorten-btn');
    const originalText = btn.innerText;
    btn.disabled = true;
    btn.innerText = '생성 중...';
    try {
        const response = await fetch('../api/shorten.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ url: window.location.href, expires_in_days: 30 })
        });
        if (!response.ok) throw new Error('단축 URL 생성 실패: ' + response.status);
        const data = await response.json();
        if (data.success) {
            document.getElementById('short-url-input').value = data.short_url;
            document.getElementById('short-url-result').style.display = 'block';
            btn.innerText = '✓ 생성됨';
        } else throw new Error(data.message || '알 수 없는 오류');
    } catch (error) {
        alert('단축 URL 생성 중 오류가 발생했습니다: ' + error.message);
        btn.innerText = originalText;
        btn.disabled = false;
    }
}

function copyToClipboard() {
    const input = document.getElementById('short-url-input');
    input.select();
    try { document.execCommand('copy'); alert('단축 URL이 복사되었습니다!'); }
    catch (err) { alert('복사에 실패했습니다. 수동으로 복사해주세요.'); }
}
</script>

</body>
</html>

