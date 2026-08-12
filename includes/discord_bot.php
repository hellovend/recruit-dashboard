<?php
require_once __DIR__ . '/../config/discord_bot_config.php';

const DISCORD_API = 'https://discord.com/api/v10';
const DISCORD_FLAG_IS_COMPONENTS_V2 = 32768;

function discord_bot_request($method, $endpoint, $token, $body = null) {
    $ch = curl_init(DISCORD_API . $endpoint);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bot {$token}",
        "Content-Type: application/json",
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => json_decode($response, true)];
}

// 새 지원자 접수 알림 — 관리자만 누를 수 있는 합격/불합격/보류 버튼 포함 (Components V2)
function send_applicant_bot_message($channelId, $token, $uniqueNumber, $nickname) {
    $payload = [
        'flags' => DISCORD_FLAG_IS_COMPONENTS_V2,
        'components' => [[
            'type' => 17, // Container
            'components' => [
                [
                    'type' => 10, // Text Display
                    'content' => "**새 지원자 접수**\n닉네임: {$nickname}\n고유번호: `{$uniqueNumber}`\n상태: 대기중",
                ],
                [
                    'type' => 1, // Action Row
                    'components' => [
                        ['type' => 2, 'style' => 3, 'label' => '합격',   'custom_id' => "pass_result:approve:{$uniqueNumber}"],
                        ['type' => 2, 'style' => 4, 'label' => '불합격', 'custom_id' => "pass_result:reject:{$uniqueNumber}"],
                        ['type' => 2, 'style' => 2, 'label' => '보류',   'custom_id' => "pass_result:pending:{$uniqueNumber}"],
                    ],
                ],
            ],
        ]],
    ];

    return discord_bot_request('POST', "/channels/{$channelId}/messages", $token, $payload);
}
