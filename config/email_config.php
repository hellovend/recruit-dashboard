<?php
// 이메일(SMTP) 설정 로더 — 기본값만 포함, git 추적됨
$email_driver = "smtp";

$email_from_address = "noreply@hellovend.xyz";
$email_from_name    = "SamSam 인사팀";

$email_smtp_host       = "smtp.resend.com";
$email_smtp_port       = 465;
$email_smtp_encryption = "ssl";
$email_smtp_username   = "resend";
$email_smtp_password   = "";

$email_enabled = false;

// 실제 SMTP 자격증명은 git에 커밋되지 않는 email_config.local.php에서 덮어씀
$localConfig = __DIR__ . "/email_config.local.php";
if (file_exists($localConfig)) {
    require $localConfig;
}
