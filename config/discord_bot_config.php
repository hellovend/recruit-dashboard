<?php
// Discord 봇 설정 — 실제 값은 discord_bot_config.local.php(git 미추적)에서 덮어씀
$discordBotToken = "";
$discordPublicKey = "";
$discordChannelId = "";
$discordAdminIds = []; // 버튼 클릭을 허용할 Discord 사용자 ID 목록

$localConfig = __DIR__ . '/discord_bot_config.local.php';
if (file_exists($localConfig)) {
    require $localConfig;
}
