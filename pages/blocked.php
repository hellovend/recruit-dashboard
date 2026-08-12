<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/darkmode.css">
    <title>접근 제한</title>
    <style>
        @font-face {
            font-family: "SpoqaHanSansNeo-Regular";
            src: url("https://cdn.jsdelivr.net/gh/projectnoonnu/noonfonts_2108@1.1/SpoqaHanSansNeo-Regular.woff") format("woff");
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: var(--bg-primary);
            font-family: "SpoqaHanSansNeo-Regular", sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }

        .block-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-top: 4px solid var(--danger);
            box-shadow: 0 0 24px var(--shadow);
            padding: 40px 32px;
            max-width: 420px;
            width: 100%;
            text-align: center;
        }

        .block-icon {
            font-size: 52px;
            line-height: 1;
            margin-bottom: 20px;
            display: block;
        }

        .block-card h1 {
            font-size: 18px;
            font-weight: bold;
            color: var(--danger);
            margin-bottom: 12px;
        }

        .block-card p {
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.7;
            margin-bottom: 8px;
        }

        .block-number {
            display: inline-block;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            padding: 6px 18px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: bold;
            color: var(--text-primary);
            margin: 16px 0 20px;
            letter-spacing: 1px;
        }

        .divider {
            border: none;
            border-top: 1px solid var(--border-color);
            margin: 20px 0;
        }

        .block-footer {
            font-size: 11px;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .back-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 9px 24px;
            background: var(--accent);
            color: #fff;
            font: bold 13px "SpoqaHanSansNeo-Regular";
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s;
        }
        .back-btn:hover { background: var(--accent-hover); }
    </style>
</head>
<body>

<div class="block-card">
    <span class="block-icon">🚫</span>

    <h1>접근이 제한된 번호입니다</h1>

    <?php if (!empty($uniqueNumber)): ?>
    <div class="block-number"><?= htmlspecialchars($uniqueNumber) ?></div>
    <?php endif; ?>

    <p>해당 고유번호는 <strong>지원불가자</strong>로 등록되어<br>서비스 이용이 제한됩니다.</p>

    <hr class="divider">

    <p class="block-footer">
        이의 신청이 필요하다면 운영진에게 직접 문의해 주세요.<br>
        본 화면은 시스템에 의해 자동으로 표시됩니다.
    </p>

    <a href="javascript:history.back()" class="back-btn">← 돌아가기</a>
</div>

<script src="../assets/script/darkmode.js"></script>
</body>
</html>

