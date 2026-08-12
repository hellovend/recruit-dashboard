-- ============================================================
--  Application-Status-Page 데이터베이스 스키마
--  GitHub: https://github.com/hellovend/Application-Status-Page
--  코드 분석 기반으로 자동 생성
-- ============================================================

-- 데이터베이스 생성 (필요 시 사용)
-- CREATE DATABASE IF NOT EXISTS samsam_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE samsam_db;

-- ============================================================
-- 1. users 테이블
--    참조 파일: api/login.php, api/logins.php
--    용도: 인사팀 로그인 계정 관리
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id`         INT          NOT NULL AUTO_INCREMENT,
    `username`   VARCHAR(100) NOT NULL UNIQUE COMMENT '로그인 아이디',
    `password`   VARCHAR(255) NOT NULL       COMMENT '비밀번호 (평문 저장 — 운영 시 hash로 교체 권장)',
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='인사팀 로그인 계정';

-- 초기 관리자 계정 예시 (비밀번호는 반드시 변경하세요)
-- INSERT INTO `users` (`username`, `password`) VALUES ('admin', 'changeme');


-- ============================================================
-- 2. admins 테이블
--    참조 파일: admin/admin.php
--    용도: 어드민(관리자) 계정 및 접속 허용 IP 관리
-- ============================================================
CREATE TABLE IF NOT EXISTS `admins` (
    `id`         INT          NOT NULL AUTO_INCREMENT,
    `username`   VARCHAR(100) NOT NULL UNIQUE COMMENT '관리자 아이디',
    `password`   VARCHAR(255) NOT NULL       COMMENT '관리자 비밀번호',
    `ip`         VARCHAR(45)  NOT NULL       COMMENT '접속 허용 IP (IPv4/IPv6)',
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='관리자 계정 및 허용 IP';


-- ============================================================
-- 3. blocked_ips 테이블
--    참조 파일: api/login.php
--    용도: SQL 인젝션 시도 등 보안 위협 IP 차단 목록
-- ============================================================
CREATE TABLE IF NOT EXISTS `blocked_ips` (
    `id`         INT         NOT NULL AUTO_INCREMENT,
    `ip_address` VARCHAR(45) NOT NULL UNIQUE COMMENT '차단된 IP 주소',
    `blocked_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '차단 일시',
    PRIMARY KEY (`id`),
    INDEX `idx_ip_address` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='IP 차단 목록';


-- ============================================================
-- 4. candidates 테이블
--    참조 파일: sync/sync.php
--    용도: 지원자 정보 동기화 수신 (외부 봇 → DB)
--          pass_status 기본값 = 'pending'
-- ============================================================
CREATE TABLE IF NOT EXISTS `candidates` (
    `id`            INT          NOT NULL AUTO_INCREMENT,
    `unique_number` VARCHAR(50)  NOT NULL UNIQUE COMMENT '지원자 고유번호',
    `nickname`      VARCHAR(100) NOT NULL         COMMENT '닉네임',
    `age`           INT          DEFAULT NULL     COMMENT '나이',
    `created_at`    DATETIME     DEFAULT NULL     COMMENT '지원 시각 (외부 전달값)',
    `pass_status`   ENUM('pending','passed','failed','notpassed')
                                 NOT NULL DEFAULT 'pending' COMMENT '심사 상태',
    PRIMARY KEY (`id`),
    INDEX `idx_unique_number` (`unique_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='지원자 원본 데이터 (sync/sync.php 수신)';


-- ============================================================
-- 5. exam_results 테이블
--    참조 파일: admin/index.php, api/check.php,
--               api/exam_results_insert.php, api/delete.php,
--               api/numbers.php, api/unique_numbers.php
--    용도: 합격 / 불합격 심사 결과 저장
-- ============================================================
CREATE TABLE IF NOT EXISTS `exam_results` (
    `id`            INT         NOT NULL AUTO_INCREMENT,
    `unique_number` VARCHAR(50) NOT NULL UNIQUE COMMENT '지원자 고유번호',
    `pass_status`   ENUM('pending','passed','failed')
                                NOT NULL DEFAULT 'pending' COMMENT '대기(pending) / 합격(passed) / 불합격(failed) — sync.php가 접수 시 pending으로 INSERT함',
    `nickname`      VARCHAR(100) DEFAULT NULL      COMMENT '닉네임 (check.php 표시용)',
    `email`         VARCHAR(255) DEFAULT NULL      COMMENT '지원자 이메일 (알림 발송용, 선택)',
    `email_sent_at` DATETIME    DEFAULT NULL       COMMENT '가장 최근 이메일 발송 시각',
    `registered_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '등록 일시',
    PRIMARY KEY (`id`),
    INDEX `idx_unique_number` (`unique_number`),
    INDEX `idx_pass_status`   (`pass_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='합격/불합격 심사 결과';


-- ============================================================
-- 6. notpassed_candidates 테이블
--    참조 파일: admin/index.php, api/numbers.php, api/rejected.php
--    용도: 지원불가자(영구 제한) 고유번호 관리
--          코드에서 `notpassed_candidates` 와 `rejected_applicants`
--          두 이름이 혼재 — 동일 목적이므로 하나로 통합
-- ============================================================
CREATE TABLE IF NOT EXISTS `notpassed_candidates` (
    `id`            INT         NOT NULL AUTO_INCREMENT,
    `unique_number` VARCHAR(50) NOT NULL UNIQUE COMMENT '지원불가자 고유번호',
    `added_at`      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '등록 일시',
    PRIMARY KEY (`id`),
    INDEX `idx_unique_number` (`unique_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='지원불가자 목록 (= rejected_applicants)';

-- api/rejected.php 는 `rejected_applicants` 테이블명을 사용하므로
-- 아래 뷰(View)로 동일하게 접근 가능하게 처리
CREATE OR REPLACE VIEW `rejected_applicants` AS
    SELECT `id`, `unique_number`, `added_at`
    FROM   `notpassed_candidates`;


-- ============================================================
-- 샘플 데이터 (테스트용 — 운영 DB에서는 삭제하세요)
-- ============================================================

-- 인사팀 로그인 계정
-- password 컬럼은 반드시 password_hash()로 해시된 값을 넣어야 합니다 (평문 저장 금지).
-- 아래 값은 각각 'password123', 'password456'을 password_hash(..., PASSWORD_DEFAULT)로 해시한 예시입니다.
INSERT INTO `users` (`username`, `password`) VALUES
    ('hr_staff1', '$2y$12$3EYp.DYuJToI6c2E8wvNN.cujE3egSWH/D6dTSpfiS.J8TWM6aFZC'),
    ('hr_staff2', '$2y$12$NHsIUUDr55qm4ovyCrSFL.nUW66VYSGqpryh2S8SQWSiaq4mtTImK');
-- 심사 결과 샘플
INSERT INTO `exam_results` (`unique_number`, `pass_status`, `nickname`) VALUES
    ('10001', 'passed',  '합격자닉네임'),
    ('10002', 'failed',  '불합격자닉네임'),
    ('10003', 'pending',  '합격자닉네임2');

-- 지원불가자 샘플
INSERT INTO `notpassed_candidates` (`unique_number`) VALUES
    ('99001'),
    ('99002');

-- ============================================================
-- 7. notices 테이블
--    참조 파일: admin/notices.php, api/notices.php
--    용도: 조회 화면 상단에 표시하는 공지 배너
-- ============================================================
CREATE TABLE IF NOT EXISTS `notices` (
    `id`         INT          NOT NULL AUTO_INCREMENT,
    `title`      VARCHAR(200) NOT NULL         COMMENT '공지 제목',
    `content`    TEXT         DEFAULT NULL     COMMENT '공지 상세 내용 (선택)',
    `type`       ENUM('info','warning','success','danger')
                              NOT NULL DEFAULT 'info' COMMENT '배너 종류',
    `is_active`  TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '활성 여부',
    `starts_at`  DATETIME     DEFAULT NULL     COMMENT '노출 시작 시각 (NULL=즉시)',
    `ends_at`    DATETIME     DEFAULT NULL     COMMENT '노출 종료 시각 (NULL=무기한)',
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='공지 배너';

-- ============================================================
-- 주요 쿼리 레퍼런스 (코드 내 사용 쿼리 정리)
-- ============================================================

-- [check.php] 고유번호로 합격 여부 조회
-- SELECT * FROM exam_results WHERE unique_number = ?;

-- [admin/index.php] 합격자 등록
-- INSERT INTO exam_results (unique_number, pass_status) VALUES (?, ?);

-- [admin/index.php] 지원불가자 등록
-- INSERT INTO notpassed_candidates (unique_number) VALUES (?);

-- [api/delete.php] 고유번호 삭제
-- DELETE FROM exam_results WHERE unique_number = ?;

-- [api/login.php] IP 차단 여부 확인
-- SELECT id FROM blocked_ips WHERE ip_address = ?;

-- [api/login.php] IP 차단 등록
-- INSERT INTO blocked_ips (ip_address) VALUES (?);

-- [api/numbers.php] 합격자 목록 조회
-- SELECT unique_number FROM exam_results WHERE pass_status = 'passed';

-- [api/numbers.php] 불합격자 목록 조회
-- SELECT unique_number FROM exam_results WHERE pass_status = 'failed';

-- [api/numbers.php] 지원불가자 목록 조회
-- SELECT unique_number FROM notpassed_candidates;

-- [sync/sync.php] 지원자 중복 확인 및 등록
-- SELECT id FROM candidates WHERE unique_number = ?;
-- INSERT INTO candidates (unique_number, nickname, age, created_at, pass_status) VALUES (?, ?, ?, ?, 'pending');
