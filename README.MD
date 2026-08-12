# 📋 FiveM 서버 지원자 관리 대시보드

FiveM 서버의 지원자 합격/불합격을 관리하고, Discord 봇/웹훅을 통해 인사팀에 실시간으로 알림을 보내는 PHP 기반 웹 대시보드입니다.

---

## 📝 최근 변경 이력 (2026-08-12)

### ➕ 추가된 기능
- **Discord 봇 연동** — 새 지원자 접수 알림을 웹훅 대신 봇(Bot Token) 기반으로 전송. 메시지에 합격/불합격/보류 버튼이 붙고, 화이트리스트에 등록된 관리자만 버튼을 눌러 그 자리에서 결과를 즉시 반영 가능
  - `config/discord_bot_config.php` / `discord_bot_config.sample.php` — 봇 설정 로더
  - `includes/discord_bot.php` — Discord REST API 호출 + Components V2 메시지 생성
  - `admin/discord_bot_settings.php` — 봇 토큰 입력 → 로그인 확인 → 채널 선택 → 저장까지 처리하는 관리자 설정 화면
  - `api/discord_interactions.php` — 버튼 클릭 처리 (Ed25519 서명 검증 → 관리자 화이트리스트 확인 → DB 업데이트 → 메시지 즉시 수정)
- **실시간 번호 조회 (SSE)** — `api/numbers.php`의 2초 폴링 방식을 Server-Sent Events로 교체 (`api/numbers_stream.php`). 값이 실제로 바뀔 때만 서버가 push하며, 연결이 끊겨도 브라우저가 자동 재연결

### 🔧 수정된 문제
- **[치명적] 지원서 접수가 전부 실패하던 버그** — `exam_results.pass_status` ENUM에 `pending` 값이 없어서 신규 지원자 접수 시마다 DB INSERT가 fatal error로 죽고 있었음 (5월 이후 미저장분은 복구 불가). ENUM에 `pending` 추가로 해결
- **닉네임 미저장 버그** — `sync/sync.php`가 닉네임을 받아놓고 INSERT 문에서 빠뜨려 계속 유실되고 있던 것 수정
- **관리자 추가 무인증 + SQL 인젝션** — `admin/admin.php`에 로그인 체크가 아예 없었고 쿼리도 문자열 결합이었음. 인증 체크 + prepared statement + 비밀번호 해시 저장으로 수정
- **삭제 기능 인증 우회 + SQL 인젝션** — `admin/delete.php`, `api/delete.php`의 로그인 체크가 주석 처리돼 있었고 DELETE 쿼리도 취약했음. 인증 복구 + prepared statement로 수정
- **중복 로그인 폼의 SQL 인젝션** — `admin/logins.php`, `api/logins.php`가 비밀번호 검증도 안 하면서 SQL 인젝션에 노출돼 있던 레거시 코드. 제거하고 정상 로그인 페이지로 리다이렉트하도록 변경
- **비밀번호 평문 저장/비교** — `password_hash()` / `password_verify()`로 전환, 기존 계정 비밀번호도 운영 DB에서 해시로 마이그레이션 완료
- **DB 자격증명 git 노출 위험** — 운영 DB 비밀번호가 커밋되는 파일에 하드코딩돼 있던 것을 `config/dbconfig.local.php`(git 미추적, `.gitignore` 등록)로 분리
- **합격자 명단 무인증 공개** — `api/numbers.php`가 로그인 없이 전체 지원자 합불 현황을 노출하던 것에 로그인 체크 추가
- **파일 쓰기 권한 오류** — `sync/`, `config/` 디렉토리가 웹서버 실행 계정(`www-data`) 쓰기 권한이 없어서 접수 로그 기록/봇 설정 저장이 실패하던 것을, `www-data`를 `hellovend` 그룹에 추가해 해결

### ♻️ 변경된 동작
- 새 지원자 접수 알림 방식: Discord Webhook → Discord Bot (Components V2, 상호작용 버튼). 로그인 알림 등 나머지 웹훅은 기존 방식 유지
- `exam_results.pass_status` 기본값: 없음 → `pending`

### 🔧 추가로 수정된 문제
- **`api/phpinfo.php` 서버 정보 노출** — 파일 삭제
- **CSRF 방어 없음** — `includes/csrf.php` 공통 헬퍼 추가, 로그인/삭제/결과 등록/지원불가자 추가/관리자 추가/봇 설정 등 상태를 바꾸는 폼 9곳 전체에 토큰 검증 적용
- **`.git` 웹 노출** — nginx `pass` vhost에 `location ~ /\.git { deny all; }` 추가

### ⏳ 아직 남은 이슈 (다음 배포 예정)
- nginx `pass` vhost에 IPv6 리스너(`listen [::]:80`)가 없어 IPv6로 접속 시 다른 사이트로 라우팅될 수 있음

---

## ✨ 주요 기능

- 🔐 **관리자 로그인** — 세션 기반 인증으로 관리자만 접근 가능하며, IP 기반 접근 제어 지원
- ✅ **합격/불합격/지원불가 처리** — 고유번호 기반으로 지원자 결과 등록 (UPsert 방식, 중복 등록 시 자동 업데이트)
- 🔍 **합격 여부 조회** — 지원자가 고유번호로 본인의 결과 확인 (`passed` / `failed` / `pending`)
- 📢 **Discord 알림** — 새 지원자 접수는 Discord 봇으로 전송되며, 관리자만 클릭 가능한 합격/불합격/보류 버튼이 함께 붙어서 옴. 나머지 이벤트(로그인 등)는 웹훅 방식
- 📡 **실시간 현황 조회** — SSE 기반으로 합격/불합격/지원불가 번호 목록을 폴링 없이 실시간 갱신
- 🚫 **IP 차단** — SQL 인젝션 시도 등 비정상 접근 IP를 `blocked_ips` 테이블에 자동 등록
- 🛠️ **서비스 상태 제어** — `$service_status` 변수로 정상/점검/종료 모드 전환
- 🗑️ **데이터 삭제** — 로그인한 관리자만 등록된 지원자 데이터 삭제 가능
- 🤖 **구글 폼 자동화** — Google Apps Script를 통해 지원서 제출 시 `sync/sync.php`로 자동 DB 등록
- 🚫 **지원불가자 접속 차단** *(추가 예정)* — `notpassed_candidates`에 등록된 번호로 조회 시 차단 페이지로 리다이렉트
- 📣 **공지 시스템** *(추가 예정)* — 관리자가 등록한 공지를 조회 화면 상단 중앙에 배너로 표시
- 📫 **이메일 통한 안내** *(추가 예정)* — 구글 폼으로 저장된 이메일로 안내와 등록 완료를 알려주는 시스템

---

## 🗺️ 서비스 흐름

```
[지원자] → Google Form 제출
    └→ Apps Script → sync/sync.php → candidates 테이블 (pending 상태)

[관리자] → admin/index.php 로그인
    └→ 고유번호 입력 + 결과 선택 (합격/불합격/지원불가)
        └→ exam_results 테이블 UPsert
        └→ Discord 웹훅 알림 전송

[지원자] → api/check.php에서 고유번호 조회
    └→ exam_results에서 pass_status 확인
        └→ 합격 / 불합격 / 검토중 / 오류 결과 화면 표시
```

---

## 📁 프로젝트 구조

```
Application-Status-Page/
├── admin/                         # 관리자용 페이지 및 처리 스크립트
│   ├── admin.php                 # 관리자 계정/IP 관리 (로그인 필수, prepared statement)
│   ├── delete.php                # 데이터 삭제 처리 (로그인 필수, prepared statement)
│   ├── discord_bot_settings.php  # Discord 봇 토큰/채널/관리자 화이트리스트 설정 화면
│   ├── exam_results_insert.php   # 합격/불합격 결과 등록 처리
│   ├── forms.php                 # 관리자 폼 페이지
│   ├── index.php                 # 관리자 메인 페이지 (결과 등록 UI + UPsert 로직)
│   ├── login.php                 # 관리자 로그인 처리 (password_verify)
│   ├── logins.php                # (레거시) login.php로 리다이렉트만 함
│   └── rejected.php              # 불합격 처리 페이지
├── api/                           # 클라이언트/서비스 연동 API 엔드포인트
│   ├── check.php                 # 지원자 결과 조회 및 서비스 상태 체크
│   ├── delete.php                # API 방식 데이터 삭제 (로그인 필수, prepared statement)
│   ├── discord_interactions.php  # Discord 버튼 클릭 처리 (서명 검증 + 관리자 화이트리스트 + DB 반영)
│   ├── exam_results_insert.php   # API 방식 결과 등록
│   ├── index.php                 # API 진입점
│   ├── login.php                 # 인사팀 로그인 + IP 차단 검사 (password_verify)
│   ├── logins.php                # (레거시) login.php로 리다이렉트만 함
│   ├── numbers.php               # 합격/불합격/지원불가자 번호 목록 조회 (로그인 필수, SSE로 실시간 갱신)
│   ├── numbers_stream.php        # numbers.php용 Server-Sent Events 스트림
│   ├── rejected.php              # 지원불가자 API
│   ├── sumbit.php                # 외부 제출 데이터 수신
│   └── unique_numbers.php        # 고유번호 중복 검사 API
├── assets/                        # 정적 리소스 (CSS, JS, 이미지)
│   ├── css/
│   │   ├── check.css             # 조회 페이지 스타일
│   │   ├── darkmode.css          # 다크모드 CSS 변수
│   │   ├── delete.css
│   │   ├── login.css
│   │   ├── logins.css
│   │   ├── LoginStyle.css
│   │   ├── registration.css      # 결과 등록 페이지 스타일
│   │   └── styles.css
│   ├── logo/                     # 서버 로고/이미지 리소스
│   ├── script/
│   │   ├── darkmode.js           # 다크모드 토글 JS
│   │   └── main.js               # 공통 UI 스크립트
│   └── index.html
├── config/                        # 환경 설정 및 웹훅 설정
│   ├── dbconfig.php              # DB 연결 로더 (기본값만 포함, git 추적됨)
│   ├── dbconfig.local.php        # 실제 DB 자격증명 (git 미추적, .gitignore 등록됨 — 직접 생성 필요)
│   ├── dbconfig.sample.php       # DB 설정 샘플 파일
│   ├── discord_bot_config.php    # Discord 봇 설정 로더 (기본값만 포함, git 추적됨)
│   ├── discord_bot_config.sample.php  # 봇 설정 샘플 파일
│   ├── discord_config.php        # Discord 웹훅 URL 설정 (⚠️ .gitignore에 추가 필요)
│   ├── discord_config.sample.php # Discord 설정 샘플 파일
│   ├── discord_webhook.php       # 메인 Discord 웹훅 전송
│   ├── discord_webhook_admin_added.php    # 관리자 추가 알림 웹훅
│   └── discord_webhook_ip_mismatch.php   # IP 불일치 알림 웹훅
├── includes/
│   ├── csrf.php                   # CSRF 토큰 발급/검증 공통 헬퍼
│   ├── discord_bot.php            # Discord REST API 호출 + Components V2 메시지 생성
│   └── discord_notification.php   # Discord 알림 함수 공통 모듈
├── pages/                         # 상태 페이지 템플릿
│   ├── 404.html
│   ├── faq.html
│   ├── ipblock.html              # IP 차단 안내 페이지
│   ├── maintenance.html          # 서비스 점검 페이지
│   └── service_ended.html        # 서비스 종료 페이지
├── sync/
│   ├── log.txt                   # 동기화 로그 파일 (www-data 쓰기 권한 필요)
│   └── sync.php                  # 구글 폼 → DB 자동 동기화 엔드포인트 (접수 시 Discord 봇으로 버튼 포함 알림)
├── .gitignore                    # dbconfig.local.php, discord_bot_config.local.php, sync/log.txt 제외
├── DB.sql                        # 데이터베이스 전체 스키마 (샘플 데이터 포함)
├── index.php                    # 사용자용 메인 진입 페이지
├── login.php                    # 사용자 로그인/조회 페이지
└── server.bat                   # 로컬 개발/테스트용 실행 배치
```

---

## ⚙️ 설치 및 설정

### 요구사항

- PHP 7.4 이상 (PHP 8.x 권장, `match` 표현식 사용)
- MySQL 5.7+ / MariaDB 10.3+
- 웹 서버 (Apache 권장, `.htaccess` 지원 필요)
- cURL 확장 (Discord 웹훅 전송에 필요)

---

### 0. 파일 다운로드

```bash
git clone https://github.com/hellovend/recruit-dashboard.git
cd recruit-dashboard
```

---

### 1. 파일 업로드

FileZilla 등 FTP 클라이언트를 이용해 웹 서버에 파일을 업로드합니다.

> 호스팅 설정 참고: [닷홈 호스팅 설정 영상](https://www.youtube.com/watch?v=cVMM9PXtWXI&t=1338s)

---

### 2. 데이터베이스 설정

`DB.sql`을 MySQL에 임포트하면 아래 테이블이 자동 생성됩니다.

| 테이블명 | 설명 |
|---|---|
| `users` | 인사팀 로그인 계정 (비밀번호는 `password_hash()`로 해시 저장) |
| `admins` | 관리자 계정 및 접속 허용 IP (비밀번호는 해시 저장) |
| `blocked_ips` | 차단된 IP 목록 |
| `candidates` | 구글 폼을 통해 수신된 지원자 원본 데이터 |
| `exam_results` | 합격(`passed`) / 불합격(`failed`) / 대기(`pending`) 심사 결과 |
| `notpassed_candidates` | 지원불가자 목록 (= `rejected_applicants` 뷰로도 접근 가능) |

`config/dbconfig.php`는 기본값만 들어있는 로더입니다. **실제 접속 정보는 커밋되지 않도록** 같은 디렉토리에 `config/dbconfig.local.php`를 새로 만들어 아래처럼 작성하세요 (있으면 자동으로 불러옵니다).

```php
<?php
$username = "YOUR_DB_USERNAME";
$password = "YOUR_DB_PASSWORD";
$dbname   = "YOUR_DB_NAME";
```

---

### 3. Discord 웹훅 설정

`config/discord_config.sample.php`를 복사해 `config/discord_config.php`를 만들고 웹훅 URL을 입력합니다.

```php
$discordWebhook = "https://discord.com/api/webhooks/YOUR_WEBHOOK_URL";
```

> `config/dbconfig.php`와 `config/discord_config.php`는 개인 설정 파일이므로 반드시 `.gitignore`에 추가하세요.

---

### 4. 서버 로고 설정

'../assets/logo/logo.png' 해당 파일을 교체하십쇼

---

### 5. 서비스 상태 설정

`api/check.php`의 `$service_status` 변수로 서비스 상태를 제어합니다.

```php
$service_status = 0; // 0 = 정상, 1 = 점검, 2 = 종료
```

| 값 | 동작 |
|---|---|
| `0` | 정상 서비스 |
| `1` | `pages/maintenance.html`로 리다이렉트 |
| `2` | `pages/service_ended.html`로 리다이렉트 |

---

### 6. Google Apps Script (구글 폼 자동화) 설정

구글 폼을 스프레드시트와 연결한 후, **Apps Script 편집기**에 아래 코드를 추가하고 **onFormSubmit 트리거**를 등록합니다.

```javascript
function onFormSubmit(e) {
  if (!e || !e.response) {
    Logger.log("이벤트 없음");
    return;
  }

  var itemResponses = e.response.getItemResponses();

  // ⚠️ 폼 질문 순서에 맞게 인덱스를 조정하세요
  var data = {
    timestamp:     new Date().toISOString(),
    nickname:      itemResponses[0].getResponse(), // 닉네임
    unique_number: itemResponses[1].getResponse(), // 고유번호
    age:           itemResponses[2].getResponse()  // 나이
  };

  UrlFetchApp.fetch("https://YOUR_DOMAIN/sync/sync.php", {
    method:      "post",
    contentType: "application/json",
    payload:     JSON.stringify(data)
  });
}
```

> 트리거 등록 경로: Apps Script → 시계 아이콘(트리거) → `onFormSubmit` 함수, 이벤트 유형: **양식 제출 시**

---

### 7. Discord 봇 설정 (지원자 접수 알림 + 버튼 상호작용)

1. [Discord 개발자 포털](https://discord.com/developers/applications)에서 애플리케이션/봇 생성 후, **Bot → Token**과 **General Information → Public Key**를 준비합니다.
2. OAuth2 → URL Generator에서 `bot` 스코프 + `Send Messages`, `View Channel` 권한으로 초대 링크를 만들어 봇을 서버에 초대합니다.
3. 관리자로 로그인한 뒤 `admin/discord_bot_settings.php`에 접속해 봇 토큰 / Public Key / 관리자 Discord 사용자 ID(버튼 클릭 허용 목록)를 입력하고, 채널을 불러와 저장합니다. (이 화면에서 `config/discord_bot_config.local.php`가 자동 생성됩니다 — git 미추적)
4. 개발자 포털 **General Information → Interactions Endpoint URL**에 `https://YOUR_DOMAIN/api/discord_interactions.php`를 입력하고 저장합니다. **OAuth2 → Redirects가 아닙니다.** 저장 시 Discord가 즉시 검증 PING을 보내며, 정상이면 에러 배너 없이 저장됩니다.

> ⚠️ `admin/discord_bot_settings.php`가 `config/discord_bot_config.local.php`를 쓰려면, 웹서버 실행 계정(예: `www-data`)이 `config/` 디렉토리에 쓰기 권한이 있어야 합니다. 아래 "파일 권한" 항목을 참고하세요.

---

### 8. 파일 권한 (Linux 배포 시)

이 프로젝트는 `sync/log.txt`(접수 로그)와 `config/discord_bot_config.local.php`(봇 설정)처럼 **웹서버가 런타임에 직접 파일을 쓰는 경로**가 있습니다. 파일 소유자와 웹서버 실행 계정(PHP-FPM 등, 보통 `www-data`)이 다르면 권한 오류로 조용히 실패할 수 있습니다.

```bash
# 예: 프로젝트 소유자 그룹에 웹서버 계정을 추가하고 그룹 쓰기 권한 부여
sudo usermod -aG <프로젝트소유그룹> www-data
sudo systemctl restart php8.4-fpm   # 버전에 맞게 변경
chmod g+w sync config sync/log.txt
```

---

## 🗄️ pass_status 상태값 정의

`exam_results.pass_status` 컬럼에서 사용하는 값은 다음과 같습니다.

| 값 | 화면 표시 | 설명 |
|---|---|---|
| `passed` | ✅ 합격 | 면접 일정 DM 전달 안내 |
| `failed` | ❌ 불합격 | 3일 후 재지원 가능 안내 |
| `pending` | 🕐 검토 중 | 현재 인사팀 검토 중 |
| (없음) | ⚠️ 오류 | 미지원 또는 등록 오류 |

---

## ⚠️ 주의사항 및 보안

- `config/dbconfig.local.php`, `config/discord_bot_config.local.php`, `config/discord_config.php`에는 민감한 정보가 포함됩니다. **절대 공개 저장소에 커밋하지 마세요.** (`dbconfig.local.php`, `discord_bot_config.local.php`는 `.gitignore`에 이미 등록되어 있음)
- `DB.sql`의 샘플 데이터(`users` 테이블 초기 계정 등)는 운영 DB 적용 전에 삭제하거나 비밀번호를 변경하세요.
- 비밀번호는 `password_hash()` / `password_verify()`로 해시 저장/검증합니다. `DB.sql`에 직접 `INSERT`할 때도 반드시 해시된 값을 넣으세요.
- Discord 웹훅 URL도 코드에 직접 포함하지 말고 `config/discord_config.php`로 분리해서 관리하세요.
- 상태를 바꾸는 폼(로그인/삭제/결과 등록 등)은 `includes/csrf.php`로 CSRF 토큰을 검증합니다. 새 폼을 추가할 때도 `csrf_field()` / `csrf_require()`를 반드시 같이 쓰세요.
- `.git` 웹 접근은 nginx에서 차단되어 있습니다 (`location ~ /\.git { deny all; }`). 다른 웹서버로 옮길 경우 동일한 차단을 직접 설정하세요.

---

## 🌍 테스트 서버 접속

- [메인 페이지](https://pass.hellovend.xyz/)
- [관리자 페이지](https://pass.hellovend.xyz/login.php) <- admin / admin1234
- [구글 폼 페이지](https://forms.gle/P8k45qJ3nXNZWAn28)

---

## 📄 라이선스

이 프로젝트는 **b1n2da의 원본 코드를 기반으로 수정 및 개선되었습니다.**
원본 코드는 당시 디스코드 서버 부스터를 대상으로 배포되었으나, 현재 해당 서버는 운영되지 않고 있으며 별도의 라이선스가 명시되어 있지 않은 상태입니다. 원작자의 기여를 존중하며, 원본 코드를 기반으로 제작되었음을 명시합니다.
본 프로젝트는 **개인 및 비영리 커뮤니티 서버 용도**로 자유롭게 사용할 수 있습니다.
다만, 원본 코드의 저작권 및 기여를 존중하기 위해 **상업적인 목적으로 이용하거나 판매하는 것은 삼가주시기 바랍니다.**
원본 코드에 대한 권리는 원작자에게 있으며, 본 프로젝트에서 추가·수정된 부분에 대해서는 해당 프로젝트의 기여자에게 권리가 있을 수 있습니다.
