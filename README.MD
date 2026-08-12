# 📋 FiveM 서버 지원자 관리 대시보드

FiveM 서버의 지원자 합격/불합격을 관리하고, Discord로 인사팀과 지원자에게 실시간 알림을 보내는 PHP 기반 웹 대시보드입니다.

---

## ✨ 주요 기능

- 🔐 **관리자 로그인** — 세션 기반 인증, 비밀번호는 `password_hash()`로 해시 저장/검증
- ✅ **합격/불합격/지원불가 처리** — 고유번호 기반 UPsert (중복 등록 시 자동 업데이트)
- 🔍 **합격 여부 조회** — 지원자가 고유번호로 본인 결과 확인 (`passed` / `failed` / `pending`)
- 🤖 **Discord 봇 알림 (지원자 접수)** — 새 지원자가 접수되면 봇이 채널에 메시지를 보내고, 합격/불합격/보류 버튼이 함께 붙습니다. 화이트리스트에 등록된 관리자만 버튼을 눌러 그 자리에서 결과를 반영할 수 있습니다
- 📢 **Discord 웹훅 알림 (로그인 등)** — 인사팀 로그인, 관리자 추가, IP 불일치 등은 기존 방식(웹훅)으로 알림
- ✉️ **이메일 알림** — 지원서 접수 시 접수 완료 메일, 합격/불합격 처리 시 결과 메일을 지원자에게 자동 발송 (이메일을 제출한 경우만, SMTP 설정 필요)
- 📡 **실시간 현황 조회** — 합격/불합격/지원불가 번호 목록을 SSE(Server-Sent Events)로 폴링 없이 실시간 갱신
- 📣 **공지 배너** — 관리자가 등록한 공지를 조회 화면에 배너로 표시 (`admin/notices.php`)
- 🚫 **IP 차단** — SQL 인젝션 시도 등 비정상 접근 IP를 자동 차단
- 🛠️ **서비스 상태 제어** — 정상/점검/종료 모드 전환
- 🗑️ **데이터 삭제** — 로그인한 관리자만 가능
- 🌐 **구글 폼 자동화** — Google Apps Script가 지원서 제출 시 `sync/sync.php`로 자동 전달 → DB 등록 → Discord 알림까지 자동
- 🛡️ **CSRF 방어** — 상태를 바꾸는 모든 폼에 토큰 검증 적용

---

## 🗺️ 서비스 흐름

```
[지원자] → Google Form 제출 (이메일 항목 선택)
    └→ Apps Script → sync/sync.php
        └→ exam_results 테이블에 pending 상태로 등록
        └→ Discord 봇이 합격/불합격/보류 버튼과 함께 알림 전송
        └→ 이메일을 제출했다면 접수 완료 메일 발송

[관리자] → admin/login.php 로그인
    └→ admin/index.php: 고유번호 + 결과 선택 → exam_results UPsert → 저장된 이메일 있으면 결과 메일 발송
    └→ 또는 Discord 메시지의 버튼을 직접 클릭 → api/discord_interactions.php가 동일하게 처리 (결과 메일 포함)

[지원자] → api/check.php에서 고유번호 조회
    └→ 합격 / 불합격 / 검토중 / 오류 결과 화면 표시
```

---

## 📁 프로젝트 구조

```
recruit-dashboard/
├── admin/                          # 관리자용 페이지
│   ├── admin.php                  # 관리자 계정 추가 (로그인 필수)
│   ├── delete.php                 # 데이터 삭제 (로그인 필수)
│   ├── discord_bot_settings.php   # Discord 봇 토큰/채널/화이트리스트 설정
│   ├── index.php                  # 메인 관리 화면 (결과 등록 UPsert, 공지 관리 링크)
│   ├── login.php                  # 관리자 로그인
│   ├── logins.php                 # (레거시) login.php로 리다이렉트만 함
│   ├── notices.php                # 공지 등록/활성화/삭제
│   └── rejected.php                # 지원불가자 처리
├── api/                             # 클라이언트/외부 연동 엔드포인트
│   ├── check.php                  # 지원자 결과 조회
│   ├── delete.php                 # 데이터 삭제 (로그인 필수)
│   ├── discord_interactions.php   # Discord 버튼 클릭 처리 (서명 검증 + 화이트리스트 + DB 반영)
│   ├── login.php                  # 인사팀 로그인 + IP 차단 검사
│   ├── logins.php                 # (레거시) login.php로 리다이렉트만 함
│   ├── notices.php                # 활성 공지 목록 JSON (인증 불필요, 공개)
│   ├── numbers.php                # 합격/불합격/지원불가자 목록 (로그인 필수, SSE 실시간 갱신)
│   ├── numbers_stream.php         # numbers.php용 SSE 스트림
│   ├── rejected.php               # 지원불가자 API
│   └── unique_numbers.php         # 고유번호 중복 검사
├── config/
│   ├── dbconfig.php               # DB 로더 (기본값만, git 추적)
│   ├── dbconfig.local.php         # 실제 DB 자격증명 (git 미추적, 직접 생성)
│   ├── discord_bot_config.php     # Discord 봇 설정 로더 (기본값만, git 추적)
│   ├── discord_bot_config.local.php  # 실제 봇 토큰 등 (git 미추적, 설정 화면에서 자동 생성)
│   ├── discord_config.php         # 로그인/관리자추가 등 웹훅 URL (플레이스홀더 상태, 직접 채워야 함)
│   ├── email_config.php           # SMTP 로더 (기본값만, git 추적)
│   ├── email_config.local.php     # 실제 SMTP 자격증명 (git 미추적, 직접 생성)
│   └── *.sample.php               # 각 설정의 샘플 파일
├── includes/
│   ├── csrf.php                   # CSRF 토큰 발급/검증
│   ├── discord_bot.php            # Discord REST API 호출 + 메시지 생성
│   ├── discord_notification.php   # (레거시) 웹훅 알림 함수
│   └── email_sender.php           # 이메일 발송 (PHPMailer, sync.php/admin/index.php/discord_interactions.php에서 호출)
├── sync/
│   └── sync.php                   # 구글 폼 → DB 자동 등록 + Discord 봇 알림 + 접수 완료 메일
├── DB.sql                          # 전체 스키마 + 샘플 데이터
└── index.php / login.php           # 사용자 진입 페이지
```

---

## ⚙️ 설치 및 설정

### 요구사항

- PHP 8.1 이상 (`password_hash`, `sodium`, `match` 표현식 사용)
- MySQL 5.7+ / MariaDB 10.3+
- nginx + PHP-FPM (또는 Apache)
- Composer (PHPMailer 설치용, 선택)

---

### 1. 파일 배포

```bash
git clone git@github.com:hellovend/recruit-dashboard.git
cd recruit-dashboard
composer install   # vendor/는 git에 커밋되지 않으므로 최초 1회 필요
```

웹서버 루트를 이 디렉토리로 지정하세요 (nginx 예시):

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.com;

    root /path/to/recruit-dashboard;
    index index.php;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }
    location ~ /\.ht  { deny all; }
    location ~ /\.git { deny all; }
}
```

---

### 2. 데이터베이스

`DB.sql`을 MySQL에 임포트하면 아래 테이블이 생성됩니다.

| 테이블명 | 설명 |
|---|---|
| `users` | 인사팀 로그인 계정 (비밀번호 해시 저장) |
| `admins` | 관리자 계정 및 접속 허용 IP (비밀번호 해시 저장) |
| `blocked_ips` | 차단된 IP 목록 |
| `candidates` | 구글 폼 원본 수신 데이터 |
| `exam_results` | 합격(`passed`)/불합격(`failed`)/대기(`pending`) 결과, 이메일/발송시각 포함 |
| `notpassed_candidates` | 지원불가자 목록 (`rejected_applicants` 뷰로도 접근) |
| `notices` | 공지 배너 |

---

### 3. 설정 파일

이 프로젝트의 모든 비밀 정보는 **`config/*.php`(git 추적, 기본값만) + `config/*.local.php`(git 미추적, 실제 값)** 패턴을 씁니다. `*.local.php`가 있으면 자동으로 덮어써서 로드됩니다.

**`config/dbconfig.local.php`** (직접 생성)
```php
<?php
$username = "YOUR_DB_USERNAME";
$password = "YOUR_DB_PASSWORD";
$dbname   = "YOUR_DB_NAME";
```

**`config/email_config.local.php`** (직접 생성) — 이걸 만들고 `$email_enabled = true;`로 켜야 접수 완료/결과 메일이 실제로 발송됩니다. 없으면 이메일 발송 코드는 조용히 스킵되고 나머지 기능(Discord 알림 등)은 그대로 동작합니다.
```php
<?php
$email_smtp_password = "YOUR_SMTP_PASSWORD_OR_API_KEY";
$email_enabled = true;
```

**`config/discord_config.php`** — 로그인/관리자추가/IP불일치 알림용 웹훅 URL을 직접 입력하세요 (현재 플레이스홀더 상태라 알림이 안 나갑니다).

**`config/discord_bot_config.local.php`** — 아래 7번 항목대로 `admin/discord_bot_settings.php` 화면에서 설정하면 자동 생성됩니다. 직접 만들 필요 없습니다.

---

### 4. 서버 로고

`login.php`, `api/check.php`, `admin/index.php` 등에서 아래 경로의 로고 이미지를 교체합니다.

```html
<img src="../assets/logo/logo.png" alt="logo">
```

---

### 5. 서비스 상태

`api/check.php`의 `$service_status` 변수로 서비스 상태를 제어합니다.

| 값 | 동작 |
|---|---|
| `0` | 정상 서비스 |
| `1` | `pages/maintenance.html`로 리다이렉트 |
| `2` | `pages/service_ended.html`로 리다이렉트 |

---

### 6. Google Apps Script (구글 폼 자동화)

구글 폼을 스프레드시트와 연결한 후, **Apps Script 편집기**에 아래 코드를 추가하고 `onFormSubmit` 트리거를 등록합니다.

```javascript
function onFormSubmit(e) {
  if (!e || !e.response) return;
  var itemResponses = e.response.getItemResponses();

  var data = {
    timestamp:     new Date().toISOString(),
    nickname:      itemResponses[0].getResponse(),
    unique_number: itemResponses[1].getResponse(),
    age:           itemResponses[2].getResponse(),
    email:         itemResponses[3] ? itemResponses[3].getResponse() : null  // 선택 — 있으면 접수/결과 메일 발송
  };

  UrlFetchApp.fetch("https://YOUR_DOMAIN/sync/sync.php", {
    method:      "post",
    contentType: "application/json",
    payload:     JSON.stringify(data)
  });
}
```

> 트리거 등록: Apps Script → 시계 아이콘(트리거) → `onFormSubmit`, 이벤트 유형: **양식 제출 시**

---

### 7. Discord 봇 설정 (지원자 접수 알림 + 버튼 상호작용)

1. [Discord 개발자 포털](https://discord.com/developers/applications)에서 애플리케이션/봇 생성 → **Bot → Token**, **General Information → Public Key** 확보
2. **OAuth2 → URL Generator**: 스코프 `bot`, 권한 `Send Messages` + `View Channel`로 초대 링크 생성 → 봇을 서버에 초대
3. 관리자로 로그인 후 `admin/discord_bot_settings.php` 접속 → 봇 토큰/Public Key/관리자 Discord ID(버튼 클릭 허용 목록) 입력 → 채널 선택 → 저장
4. 개발자 포털 **General Information → Interactions Endpoint URL**에 `https://YOUR_DOMAIN/api/discord_interactions.php` 입력 후 저장 (**OAuth2 → Redirects가 아님**). 저장 시 Discord가 즉시 검증 PING을 보내고, 통과하면 에러 없이 저장됩니다.

---

### 8. 파일 권한 (Linux 배포 시)

`sync/log.txt`(접수 로그), `config/*.local.php`(설정 화면에서 저장) 등은 웹서버가 런타임에 직접 씁니다. 파일 소유자와 웹서버 실행 계정(PHP-FPM, 보통 `www-data`)이 다르면 권한 오류로 조용히 실패합니다.

```bash
sudo usermod -aG <프로젝트소유그룹> www-data
sudo systemctl restart php8.4-fpm   # 버전에 맞게 변경
chmod g+w sync config sync/log.txt
```

---

## 🗄️ pass_status 상태값

| 값 | 화면 표시 | 설명 |
|---|---|---|
| `passed` | ✅ 합격 | 면접 일정 DM 전달 안내 |
| `failed` | ❌ 불합격 | 3일 후 재지원 가능 안내 |
| `pending` | 🕐 검토 중 | 인사팀 검토 중 (접수 시 기본값) |
| (없음) | ⚠️ 오류 | 미지원 또는 등록 오류 |

---

## ⚠️ 주의사항 및 보안

- `config/*.local.php`는 절대 커밋하지 마세요 (`.gitignore`에 이미 등록되어 있습니다).
- `DB.sql`의 샘플 계정은 운영 반영 전에 삭제하거나 비밀번호를 바꾸세요.
- 비밀번호는 항상 `password_hash()`로 해시해서 저장하세요. 평문 INSERT 금지.
- 상태를 바꾸는 폼을 새로 추가할 때는 `includes/csrf.php`의 `csrf_field()` / `csrf_require()`를 반드시 같이 쓰세요.
- `.git` 웹 접근은 nginx에서 차단되어 있습니다. 다른 웹서버로 옮길 경우 동일하게 차단하세요.
- 이메일 발송은 `$email_enabled`가 꺼져 있으면(기본값) 모든 코드 경로에서 조용히 스킵됩니다. 실제로 발송되게 하려면 `config/email_config.local.php`를 만들어야 합니다.

---

## 📄 라이선스

이 프로젝트는 b1n2da의 원본 코드를 기반으로 수정 및 개선되었습니다.
원본은 디스코드 서버 부스터 한정으로 배포되었으나 현재 해당 서버가 존재하지 않으며, 별도의 라이선스 명시가 없었습니다. 원작자의 기여를 존중하며 출처를 밝힙니다.
개인 및 커뮤니티 서버 용도로 자유롭게 사용할 수 있습니다. 단, 상업적 이용은 삼가주세요.
