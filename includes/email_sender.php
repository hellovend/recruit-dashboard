<?php
/**
 * includes/email_sender.php
 * 이메일 발송 공통 모듈
 *
 * ─────────────────────────────────────────────────
 *  의존성 (PHPMailer 사용 시)
 * ─────────────────────────────────────────────────
 *  composer require phpmailer/phpmailer
 *  또는 수동으로 src/ 폴더를 프로젝트에 복사하세요.
 *  https://github.com/PHPMailer/PHPMailer
 *
 *  PHPMailer 가 없으면 자동으로 PHP mail() 로 폴백합니다.
 * ─────────────────────────────────────────────────
 *
 *  사용법:
 *    require_once __DIR__ . '/../includes/email_sender.php';
 *    $ok = sendResultEmail($toAddress, $toName, $uniqueNumber, $passStatus);
 *    $ok = sendRegistrationEmail($toAddress, $toName, $uniqueNumber);
 */

require_once __DIR__ . '/../config/email_config.php';

// PHPMailer autoload (Composer 사용 시) — 없으면 sendMail()이 mail()로 폴백함
$phpmailerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($phpmailerAutoload)) {
    require_once $phpmailerAutoload;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/* ════════════════════════════════════════════════════════════
   공통 발송 함수
   ════════════════════════════════════════════════════════════ */
/**
 * @param string $toEmail    수신자 이메일
 * @param string $toName     수신자 이름 (닉네임 등)
 * @param string $subject    제목
 * @param string $bodyHtml   HTML 본문
 * @param string $bodyText   Plain-text 폴백 본문
 * @return bool              발송 성공 여부
 */
function sendMail(string $toEmail, string $toName, string $subject, string $bodyHtml, string $bodyText = ''): bool
{
    global $email_enabled,
           $email_driver,
           $email_from_address,
           $email_from_name,
           $email_smtp_host,
           $email_smtp_port,
           $email_smtp_encryption,
           $email_smtp_username,
           $email_smtp_password;

    if (!$email_enabled) {
        return false;
    }

    // ── PHPMailer 경로 ──────────────────────────────────────
    $usePHPMailer = $email_driver === 'smtp'
                 && class_exists('PHPMailer\\PHPMailer\\PHPMailer');

    if ($usePHPMailer) {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $email_smtp_host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $email_smtp_username;
            $mail->Password   = $email_smtp_password;
            $mail->SMTPSecure = ($email_smtp_encryption === 'ssl')
                                  ? PHPMailer::ENCRYPTION_SMTPS
                                  : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $email_smtp_port;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom($email_from_address, $email_from_name);
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $bodyHtml;
            $mail->AltBody = $bodyText ?: strip_tags($bodyHtml);

            $mail->send();
            return true;

        } catch (Exception $e) {
            error_log('[email_sender] PHPMailer 오류: ' . $e->getMessage());
            return false;
        }
    }

    // ── PHP mail() 폴백 ─────────────────────────────────────
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: =?UTF-8?B?" . base64_encode($email_from_name) . "?= <{$email_from_address}>\r\n";

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    return mail($toEmail, $encodedSubject, $bodyHtml, $headers);
}


/* ════════════════════════════════════════════════════════════
   이메일 템플릿 함수들
   ════════════════════════════════════════════════════════════ */

/**
 * 1. 심사 결과 이메일 (합격 / 불합격 / 검토중)
 */
function sendResultEmail(string $toEmail, string $toName, string $uniqueNumber, string $passStatus): bool
{
    global $email_from_name;

    $statusMap = [
        'passed'  => ['✅ 합격',      '#22c55e', '축하드립니다! 합격하셨습니다.',       '면접 일정 관련 안내를 DM으로 전달드릴 예정입니다. 확인 부탁드립니다.'],
        'failed'  => ['❌ 불합격',    '#f53838', '아쉽게도 불합격 처리되었습니다.',     '3일 후 재지원이 가능합니다. 다음 기회에 다시 도전해 주세요.'],
        'pending' => ['🕐 검토 중',   '#f59e0b', '현재 서류를 검토 중입니다.',         '인사팀이 검토를 완료하는 대로 결과를 안내해드리겠습니다.'],
    ];

    [$statusLabel, $statusColor, $headline, $detail] =
        $statusMap[$passStatus] ?? ['⚠️ 오류', '#6b7280', '결과를 확인할 수 없습니다.', '관리자에게 문의해주세요.'];

    $subject = "[{$email_from_name}] 지원 결과 안내 — 고유번호 {$uniqueNumber}";

    $bodyHtml = buildEmailTemplate(
        title:    $statusLabel,
        headline: $headline,
        detail:   $detail,
        accentColor: $statusColor,
        uniqueNumber: $uniqueNumber,
        toName:   $toName
    );

    return sendMail($toEmail, $toName, $subject, $bodyHtml);
}

/**
 * 2. 지원서 접수 완료 이메일
 */
function sendRegistrationEmail(string $toEmail, string $toName, string $uniqueNumber): bool
{
    global $email_from_name;

    $subject = "[{$email_from_name}] 지원서 접수 완료 — 고유번호 {$uniqueNumber}";

    $bodyHtml = buildEmailTemplate(
        title:    '📩 지원서 접수 완료',
        headline: '지원서가 정상적으로 접수되었습니다.',
        detail:   '인사팀이 서류를 검토한 후 결과를 이메일로 안내해 드립니다. 잠시만 기다려주세요.',
        accentColor: '#6145ff',
        uniqueNumber: $uniqueNumber,
        toName:   $toName
    );

    return sendMail($toEmail, $toName, $subject, $bodyHtml);
}


/* ════════════════════════════════════════════════════════════
   HTML 이메일 템플릿 빌더
   ════════════════════════════════════════════════════════════ */
function buildEmailTemplate(
    string $title,
    string $headline,
    string $detail,
    string $accentColor,
    string $uniqueNumber,
    string $toName
): string {
    global $email_from_name;

    $year = date('Y');
    $tn   = htmlspecialchars($toName);
    $un   = htmlspecialchars($uniqueNumber);
    $ht   = htmlspecialchars($headline);
    $dt   = htmlspecialchars($detail);
    $tt   = htmlspecialchars($title);
    $fn   = htmlspecialchars($email_from_name);

    return <<<HTML
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$tt}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f8;font-family:'Apple SD Gothic Neo',Malgun Gothic,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f8;padding:40px 0;">
  <tr>
    <td align="center">
      <table width="520" cellpadding="0" cellspacing="0"
             style="background:#ffffff;border-radius:10px;overflow:hidden;
                    box-shadow:0 2px 16px rgba(0,0,0,0.10);">

        <!-- 헤더 -->
        <tr>
          <td style="background:{$accentColor};padding:28px 32px;text-align:center;">
            <div style="font-size:28px;margin-bottom:8px;">{$tt}</div>
            <div style="color:rgba(255,255,255,0.9);font-size:13px;">{$fn}</div>
          </td>
        </tr>

        <!-- 본문 -->
        <tr>
          <td style="padding:32px;">
            <p style="font-size:14px;color:#374151;margin-bottom:20px;">
              안녕하세요, <strong>{$tn}</strong>님.
            </p>
            <p style="font-size:16px;font-weight:bold;color:#111827;margin-bottom:12px;">
              {$ht}
            </p>
            <p style="font-size:13px;color:#6b7280;line-height:1.7;margin-bottom:24px;">
              {$dt}
            </p>

            <!-- 고유번호 박스 -->
            <table width="100%" cellpadding="0" cellspacing="0"
                   style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;margin-bottom:24px;">
              <tr>
                <td style="padding:16px;text-align:center;">
                  <div style="font-size:11px;color:#6b7280;margin-bottom:6px;">고유번호</div>
                  <div style="font-size:22px;font-weight:bold;color:{$accentColor};letter-spacing:2px;">{$un}</div>
                </td>
              </tr>
            </table>

            <p style="font-size:12px;color:#9ca3af;line-height:1.6;border-top:1px solid #f3f4f6;padding-top:16px;">
              본 이메일은 자동으로 발송된 메일입니다.<br>
              궁금한 사항은 운영진에게 직접 문의해 주세요.
            </p>
          </td>
        </tr>

        <!-- 푸터 -->
        <tr>
          <td style="background:#f9fafb;padding:16px 32px;text-align:center;
                     border-top:1px solid #f3f4f6;">
            <span style="font-size:11px;color:#9ca3af;">
              © {$year} {$fn}. All rights reserved.
            </span>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>

</body>
</html>
HTML;
}

