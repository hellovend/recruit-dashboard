<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../includes/discord_bot.php';
require_once __DIR__ . '/../includes/csrf.php';

$step = 'token';
$error = '';
$botToken = '';
$publicKey = '';
$adminIdsRaw = '';
$botUser = null;
$channels = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_require();

    $botToken = trim($_POST['bot_token'] ?? '');
    $publicKey = trim($_POST['public_key'] ?? '');
    $adminIdsRaw = trim($_POST['admin_ids'] ?? '');

    if ($_POST['action'] === 'fetch_channels') {
        // 1단계: 토큰으로 "로그인" 확인 후 채널 목록 조회
        $me = discord_bot_request('GET', '/users/@me', $botToken);

        if ($me['status'] !== 200) {
            $error = '봇 토큰이 유효하지 않습니다. Discord 개발자 포털에서 다시 확인해주세요.';
        } else {
            $botUser = $me['body'];
            $guilds = discord_bot_request('GET', '/users/@me/guilds', $botToken);

            foreach (($guilds['body'] ?? []) as $guild) {
                $guildChannels = discord_bot_request('GET', "/guilds/{$guild['id']}/channels", $botToken);
                foreach (($guildChannels['body'] ?? []) as $ch) {
                    if (($ch['type'] ?? null) === 0) { // 텍스트 채널만
                        $channels[] = [
                            'id' => $ch['id'],
                            'label' => "{$guild['name']} / #{$ch['name']}",
                        ];
                    }
                }
            }

            if (empty($channels)) {
                $error = '봇이 참여한 서버에서 텍스트 채널을 찾을 수 없습니다. 서버에 봇을 초대했는지 확인해주세요.';
            } else {
                $step = 'channel';
            }
        }
    } elseif ($_POST['action'] === 'save') {
        $channelId = $_POST['channel_id'] ?? '';
        $adminIds = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $adminIdsRaw))));

        if (!$channelId) {
            $error = '채널을 선택해주세요.';
            $step = 'token';
        } else {
            $config = "<?php\n";
            $config .= "// admin/discord_bot_settings.php에서 자동 생성됨 — git에 커밋되지 않음\n";
            $config .= '$discordBotToken = ' . var_export($botToken, true) . ";\n";
            $config .= '$discordPublicKey = ' . var_export($publicKey, true) . ";\n";
            $config .= '$discordChannelId = ' . var_export($channelId, true) . ";\n";
            $config .= '$discordAdminIds = ' . var_export($adminIds, true) . ";\n";

            file_put_contents(__DIR__ . '/../config/discord_bot_config.local.php', $config);

            $step = 'done';
        }
    }
}

// 저장된 현재 설정 (화면 표시용)
require_once __DIR__ . '/../config/discord_bot_config.php';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discord 봇 설정</title>
    <style>
        body { font-family: sans-serif; max-width: 640px; margin: 40px auto; padding: 0 20px; }
        label { display: block; margin-top: 16px; font-weight: bold; }
        input[type=text], input[type=password], textarea, select {
            width: 100%; padding: 8px; margin-top: 4px; box-sizing: border-box;
        }
        button { margin-top: 20px; padding: 10px 20px; }
        .error { color: #c0392b; margin-top: 12px; }
        .hint { color: #666; font-size: 13px; }
        .current { background: #f5f5f5; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
    </style>
</head>
<body>
    <h2>Discord 봇 설정</h2>

    <div class="current">
        현재 설정: 채널 ID <code><?php echo htmlspecialchars($discordChannelId ?: '(없음)'); ?></code>,
        관리자 <?php echo count($discordAdminIds); ?>명 등록됨
    </div>

    <?php if ($error): ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <?php if ($step === 'done'): ?>
        <p>✅ 저장되었습니다. Discord 개발자 포털의 <strong>Interactions Endpoint URL</strong>에
           이 사이트의 <code>/api/discord_interactions.php</code> 전체 URL을 등록해야 버튼이 동작합니다.</p>
        <p><a href="discord_bot_settings.php">다시 설정하기</a></p>

    <?php elseif ($step === 'channel'): ?>
        <p>✅ 봇 로그인 확인: <strong><?php echo htmlspecialchars($botUser['username'] ?? ''); ?></strong></p>
        <form method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="bot_token" value="<?php echo htmlspecialchars($botToken); ?>">
            <input type="hidden" name="public_key" value="<?php echo htmlspecialchars($publicKey); ?>">
            <input type="hidden" name="admin_ids" value="<?php echo htmlspecialchars($adminIdsRaw); ?>">

            <label for="channel_id">알림을 보낼 채널</label>
            <select name="channel_id" id="channel_id" required>
                <?php foreach ($channels as $ch): ?>
                    <option value="<?php echo htmlspecialchars($ch['id']); ?>"><?php echo htmlspecialchars($ch['label']); ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit">저장</button>
        </form>

    <?php else: ?>
        <form method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="fetch_channels">

            <label for="bot_token">봇 토큰</label>
            <input type="password" name="bot_token" id="bot_token" required
                   value="<?php echo htmlspecialchars($botToken); ?>">
            <p class="hint">Discord 개발자 포털 &gt; Bot &gt; Token</p>

            <label for="public_key">Public Key</label>
            <input type="text" name="public_key" id="public_key" required
                   value="<?php echo htmlspecialchars($publicKey); ?>">
            <p class="hint">Discord 개발자 포털 &gt; General Information &gt; Public Key
                (버튼 클릭 요청이 실제 Discord에서 온 것인지 검증하는 데 사용됩니다)</p>

            <label for="admin_ids">버튼 클릭을 허용할 관리자 Discord 사용자 ID (한 줄에 하나씩)</label>
            <textarea name="admin_ids" id="admin_ids" rows="4"><?php echo htmlspecialchars($adminIdsRaw); ?></textarea>
            <p class="hint">디스코드에서 사용자 프로필 &gt; ... &gt; ID 복사 (개발자 모드 필요)</p>

            <button type="submit">봇 로그인 확인 &amp; 채널 불러오기</button>
        </form>
    <?php endif; ?>
</body>
</html>
