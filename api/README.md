# 폼 백엔드 배포 가이드 (Cafe24 웹호스팅 기준)

더와이랩 `/contact/`(교육 문의)·`/apply/`(교육멤버 지원) 폼은
`POST /api/submit.php` 로 제출되어 **MySQL 저장 + 알림 메일** 을 처리합니다.

## 0. 환경 요건 (Cafe24)
- **PHP 7.1 이상** (코드가 `??`, `random_bytes`, nullable 타입힌트, `Throwable` 사용).
  Cafe24 [나의서비스관리 > 웹호스팅 > PHP 버전관리]에서 **7.x 또는 8.x** 선택.
  (구버전 계정 기본값이 PHP 5.x이면 폼이 동작하지 않음.)
- **MySQL(MariaDB)** 1개 — Cafe24 호스팅에 기본 포함.
- **mod_rewrite / mod_deflate / mod_expires** — 기본 제공(루트 `.htaccess`는 없어도 무시되게 작성됨).

## 1. 업로드 위치 / 문서 루트
- Cafe24 문서 루트는 보통 `/www/`. **프로젝트 폴더 전체를 `/www/` 아래에 업로드**하면
  `https://도메인/api/submit.php` 로 폼 action(`/api/submit.php`)이 정확히 매칭됩니다.
- **업로드 제외(업로드하지 말 것):** `.git/`, `test/`, `docs/`, `node_modules/`, `*.bak`,
  `api/config.php`는 저장소엔 없지만 **서버에서 직접 생성**(아래 3번). `db/schema.sql`은 임포트용이므로
  올릴 필요 없음(올려도 루트 `.htaccess`가 `.sql` 접근을 차단).

## 2. DB 준비
1. Cafe24 [MySQL 관리]에서 DB·계정 확인(호스트는 보통 `localhost`).
2. phpMyAdmin 등으로 `db/schema.sql` 임포트 → `inquiries`, `member_applications` 테이블 생성.

## 3. 설정 파일 생성 (서버에서)
`api/config.sample.php` → `api/config.php` 로 복사 후 실제 값 입력:
```php
'db' => ['host'=>'localhost','name'=>'DB명','user'=>'DB계정','pass'=>'DB비번','charset'=>'utf8mb4'],
'mail_to'   => 'thewhhylab@naver.com',          // 알림 받을 주소
'mail_from' => 'master@thewhhylab.com',     // 발신 표기(호스팅 도메인 권장)
'upload_dir'=> '',                               // 비우면 api/uploads/ 사용
'max_upload_bytes' => 10*1024*1024,              // 10MB (유지)
```
> `api/config.php` 는 `.gitignore` 처리되어 저장소에 올라가지 않으며, 루트 `.htaccess`가
> 웹 직접 접근(`/api/config.php`)도 차단합니다.

## 4. 업로드 폴더 / 한도
- `api/uploads/` 에 **쓰기 권한(0755)** 부여. `api/uploads/.htaccess`(Deny)로 직접 접근 차단됨 — 확인.
- **파일 업로드 10MB**가 되려면 PHP 한도가 충분해야 합니다(기본값이 8M/2M이면 실패):
  - `api/.user.ini` 에 `upload_max_filesize=12M / post_max_size=13M` 설정 포함.
    **이 파일은 PHP가 CGI/FastCGI일 때만 적용**됩니다.
  - PHP가 **mod_php(Apache 모듈)** 이면 `.user.ini`가 무시되므로,
    Cafe24 [PHP 설정 / php.ini]에서 `upload_max_filesize`·`post_max_size`를 직접 올리세요.

## 5. 메일(mail()) 주의
- `api/lib/mailer.php`는 PHP `mail()` 사용. Cafe24는 mail()을 지원하지만,
  **발신 도메인(`mail_from`)이 호스팅 도메인과 일치**하고 **SPF 레코드**가 있어야 스팸 처리를 피합니다.
- 네이버(`thewhhylab@naver.com`) 수신이 누락되면 발신을 호스팅 도메인 메일로 바꾸거나,
  `mailer.php`를 PHPMailer(SMTP)로 교체.

## 6. SSL / HTTPS
- Cafe24 무료 SSL(Let's Encrypt) 적용 후, 루트 `.htaccess`의 **5) HTTPS 강제 블록 주석 해제**.
  (인증서 적용 전 해제 시 사이트 접속 불가 — 반드시 적용 완료 후.)
- 폼은 개인정보(이름/연락처/파일)를 전송하므로 HTTPS 권장.

## 7. 제출 테스트 (배포 후)
- `/contact/` 정상 제출 → `inquiries` row 생성 + 알림 메일 수신.
- `/apply/` 파일 업로드 → `api/uploads/`에 난수 파일명 저장 + 메일에 원본 파일명 안내.
- 봇 방지: 허니팟(`company_website`) 채워지면 조용히 성공처럼 처리, 3초 미만 제출은 차단.
- (선택) 서버에서 `php -l api/submit.php` 로 문법 확인.

## 스팸/보안 요약
- 허니팟 + 제출 타이밍(3초) 검사, 서버측 필수값/형식 재검증(클라이언트 우회 방지).
- PDO prepared statement(SQL 인젝션 방지), 업로드 확장자 화이트리스트 + 난수 파일명 + 직접접근 차단.
