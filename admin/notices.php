<?php
/**
 * admin/notices.php
 * 공지 관리 페이지 — 목록 조회 / 등록 / 활성-비활성 전환 / 삭제
 */
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../config/dbconfig.php';

$message = '';
$msgType = '';

// ─── POST 처리 ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    // 공지 등록
    if ($action === 'create') {
        $title     = trim($_POST['title'] ?? '');
        $content   = trim($_POST['content'] ?? '') ?: null;
        $type      = $_POST['type'] ?? 'info';
        $startsAt  = $_POST['starts_at'] ?: null;
        $endsAt    = $_POST['ends_at']   ?: null;

        if (!in_array($type, ['info','warning','success','danger'])) {
            $type = 'info';
        }

        if ($title === '') {
            $message = '제목을 입력하세요.';
            $msgType = 'danger';
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO notices (title, content, type, is_active, starts_at, ends_at)
                 VALUES (?, ?, ?, 1, ?, ?)"
            );
            $stmt->bind_param('sssss', $title, $content, $type, $startsAt, $endsAt);
            if ($stmt->execute()) {
                $message = '✅ 공지가 등록되었습니다.';
                $msgType = 'success';
            } else {
                $message = '❌ 등록 실패: ' . $conn->error;
                $msgType = 'danger';
            }
        }
    }

    // 활성/비활성 토글
    if ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $conn->prepare(
            "UPDATE notices SET is_active = 1 - is_active WHERE id = ?"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $message = '공지 상태가 변경되었습니다.';
        $msgType = 'info';
    }

    // 삭제
    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM notices WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $message = '🗑️ 공지가 삭제되었습니다.';
        $msgType = 'warning';
    }
}

// ─── 공지 목록 조회 ───────────────────────────────────────────
$noticesResult = $conn->query(
    "SELECT id, title, content, type, is_active, starts_at, ends_at, created_at
     FROM notices
     ORDER BY id DESC"
);
$notices = [];
while ($row = $noticesResult->fetch_assoc()) {
    $notices[] = $row;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="ko" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/darkmode.css">
    <title>공지 관리</title>
    <style>
        @font-face {
            font-family: "SpoqaHanSansNeo-Regular";
            src: url("https://cdn.jsdelivr.net/gh/projectnoonnu/noonfonts_2108@1.1/SpoqaHanSansNeo-Regular.woff") format("woff");
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: var(--bg-primary);
            font-family: "SpoqaHanSansNeo-Regular", sans-serif;
            color: var(--text-primary);
            min-height: 100vh;
            padding: 40px 20px 80px;
        }

        .page-wrap {
            max-width: 820px;
            margin: 0 auto;
        }

        /* ── 헤더 ── */
        .page-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 32px;
        }
        .page-header img {
            height: 48px;
            object-fit: contain;
            filter: drop-shadow(0 0 3px rgba(0,0,0,0.3));
        }
        .page-header h1 {
            font-size: 22px;
            font-weight: bold;
            color: var(--text-primary);
        }
        .page-header a {
            margin-left: auto;
            font-size: 12px;
            color: var(--text-secondary);
            text-decoration: none;
            padding: 6px 14px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            transition: background 0.2s;
        }
        .page-header a:hover { background: var(--bg-secondary); }

        /* ── 알림 메시지 ── */
        .alert {
            padding: 12px 16px;
            margin-bottom: 20px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
        }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .alert-danger  { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .alert-info    { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
        .alert-warning { background: #fef9c3; color: #713f12; border: 1px solid #fde047; }
        [data-theme="dark"] .alert-success { background: #064e3b; color: #6ee7b7; border-color: #065f46; }
        [data-theme="dark"] .alert-danger  { background: #450a0a; color: #fca5a5; border-color: #991b1b; }
        [data-theme="dark"] .alert-info    { background: #1e3a5f; color: #93c5fd; border-color: #1e40af; }
        [data-theme="dark"] .alert-warning { background: #3b2500; color: #fde047; border-color: #713f12; }

        /* ── 카드 ── */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 28px;
            box-shadow: 0 2px 8px var(--shadow);
        }
        .card-title {
            font-size: 15px;
            font-weight: bold;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        /* ── 폼 ── */
        .form-row { margin-bottom: 14px; }
        .form-row label {
            display: block;
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }
        .form-row input[type="text"],
        .form-row input[type="datetime-local"],
        .form-row textarea,
        .form-row select {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font: 13px "SpoqaHanSansNeo-Regular";
            outline: none;
            transition: border-color 0.2s;
        }
        .form-row input:focus,
        .form-row textarea:focus,
        .form-row select:focus { border-color: var(--accent); }
        .form-row textarea { resize: vertical; min-height: 72px; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        @media (max-width: 560px) { .form-grid { grid-template-columns: 1fr; } }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 9px 20px;
            border: none;
            border-radius: 4px;
            font: bold 13px "SpoqaHanSansNeo-Regular";
            cursor: pointer;
            transition: background 0.2s, opacity 0.2s;
        }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-primary:hover { background: var(--accent-hover); }
        .btn-danger  { background: var(--danger); color: #fff; }
        .btn-danger:hover { background: var(--danger-hover); }
        .btn-sm { padding: 5px 12px; font-size: 11px; }

        /* ── 뱃지 ── */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }
        .badge-info    { background: #dbeafe; color: #1e40af; }
        .badge-warning { background: #fef9c3; color: #713f12; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-danger  { background: #fee2e2; color: #991b1b; }
        [data-theme="dark"] .badge-info    { background: #1e3a5f; color: #93c5fd; }
        [data-theme="dark"] .badge-warning { background: #3b2500; color: #fde047; }
        [data-theme="dark"] .badge-success { background: #064e3b; color: #6ee7b7; }
        [data-theme="dark"] .badge-danger  { background: #450a0a; color: #fca5a5; }

        .badge-active   { background: #d1fae5; color: #065f46; }
        .badge-inactive { background: var(--bg-secondary); color: var(--text-secondary); }
        [data-theme="dark"] .badge-active   { background: #064e3b; color: #6ee7b7; }

        /* ── 테이블 ── */
        .notice-table { width: 100%; border-collapse: collapse; }
        .notice-table th,
        .notice-table td {
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            font-size: 12px;
            color: var(--text-primary);
            vertical-align: middle;
        }
        .notice-table th {
            background: var(--bg-table-header);
            color: var(--text-table-header);
            font-weight: bold;
            text-align: center;
        }
        .notice-table td { background: var(--bg-card); }
        .notice-table tr:hover td { background: var(--bg-table-hover); }
        .notice-table .td-actions { text-align: center; white-space: nowrap; }
        .notice-table .td-actions form { display: inline; }
        .notice-table .td-actions .btn + .btn { margin-left: 4px; }

        .notice-title-cell { max-width: 260px; }
        .notice-title-text {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .empty-row td {
            text-align: center;
            color: var(--text-secondary);
            padding: 30px;
        }
    </style>
</head>
<body>
<div class="page-wrap">

    <!-- 헤더 -->
    <div class="page-header">
        <img src="../assets/logo/logo.png" alt="logo">
        <h1>공지 관리</h1>
        <a href="index.php">← 돌아가기</a>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= htmlspecialchars($msgType) ?>">
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <!-- 공지 등록 폼 -->
    <div class="card">
        <div class="card-title">📣 새 공지 등록</div>
        <form method="post">
            <input type="hidden" name="action" value="create">

            <div class="form-row">
                <label>제목 <span style="color:var(--danger)">*</span></label>
                <input type="text" name="title" placeholder="공지 제목을 입력하세요" required>
            </div>

            <div class="form-row">
                <label>상세 내용 (선택)</label>
                <textarea name="content" placeholder="추가 설명이 있으면 입력하세요 (없으면 비워두세요)"></textarea>
            </div>

            <div class="form-grid">
                <div class="form-row">
                    <label>배너 종류</label>
                    <select name="type">
                        <option value="info">📘 안내 (파란색)</option>
                        <option value="success">✅ 성공 (초록색)</option>
                        <option value="warning">⚠️ 경고 (노란색)</option>
                        <option value="danger">🚨 위험 (빨간색)</option>
                    </select>
                </div>
                <div class="form-row" style="display:flex;gap:10px;">
                    <div style="flex:1;">
                        <label>시작 시각 (비우면 즉시)</label>
                        <input type="datetime-local" name="starts_at">
                    </div>
                    <div style="flex:1;">
                        <label>종료 시각 (비우면 무기한)</label>
                        <input type="datetime-local" name="ends_at">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">공지 등록</button>
        </form>
    </div>

    <!-- 공지 목록 -->
    <div class="card">
        <div class="card-title">📋 공지 목록</div>
        <div style="overflow-x:auto;">
        <table class="notice-table">
            <thead>
                <tr>
                    <th style="width:40px">#</th>
                    <th>제목</th>
                    <th style="width:80px">종류</th>
                    <th style="width:70px">상태</th>
                    <th style="width:130px">등록일</th>
                    <th style="width:130px">종료일</th>
                    <th style="width:140px">관리</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($notices)): ?>
                <tr class="empty-row"><td colspan="7">등록된 공지가 없습니다.</td></tr>
            <?php else: ?>
            <?php foreach ($notices as $n): ?>
                <tr>
                    <td style="text-align:center"><?= $n['id'] ?></td>
                    <td class="notice-title-cell">
                        <div class="notice-title-text" title="<?= htmlspecialchars($n['title']) ?>">
                            <?= htmlspecialchars($n['title']) ?>
                        </div>
                        <?php if ($n['content']): ?>
                        <div style="font-size:11px;color:var(--text-secondary);margin-top:3px;">
                            <?= htmlspecialchars(mb_substr($n['content'], 0, 40)) ?>…
                        </div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center">
                        <?php
                        $badgeMap = [
                            'info'    => ['badge-info',    '📘 안내'],
                            'warning' => ['badge-warning', '⚠️ 경고'],
                            'success' => ['badge-success', '✅ 성공'],
                            'danger'  => ['badge-danger',  '🚨 위험'],
                        ];
                        [$bClass, $bLabel] = $badgeMap[$n['type']] ?? ['badge-info','안내'];
                        ?>
                        <span class="badge <?= $bClass ?>"><?= $bLabel ?></span>
                    </td>
                    <td style="text-align:center">
                        <span class="badge <?= $n['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                            <?= $n['is_active'] ? '활성' : '비활성' ?>
                        </span>
                    </td>
                    <td style="text-align:center;font-size:11px;">
                        <?= htmlspecialchars(substr($n['created_at'], 0, 16)) ?>
                    </td>
                    <td style="text-align:center;font-size:11px;color:var(--text-secondary);">
                        <?= $n['ends_at'] ? htmlspecialchars(substr($n['ends_at'], 0, 16)) : '무기한' ?>
                    </td>
                    <td class="td-actions">
                        <form method="post">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $n['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <?= $n['is_active'] ? '비활성화' : '활성화' ?>
                            </button>
                        </form>
                        <form method="post" onsubmit="return confirm('정말 삭제하시겠습니까?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $n['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">삭제</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

</div>
<script src="../assets/script/darkmode.js"></script>
</body>
</html>

