# Cafe24 백엔드 구축 A-Z (더와이랩 폼 백엔드)

> 이 사이트의 백엔드는 **PHP + MySQL** 로 된 폼 처리기입니다.
> `/contact/`(교육 문의)·`/apply/`(강사 지원) 폼이 `POST /api/submit.php` 로 제출 →
> **MySQL 저장 + 알림 메일(`thewhhylab@naver.com`) 발송**.
> 별도 서버·프레임워크 설치 없이 **Cafe24 웹호스팅에 파일만 올리면** 동작합니다.

전체 흐름: **호스팅 준비 → DB 생성 → 파일 업로드 → PHP 버전 → config.php → 업로드 한도 → SSL → 메일 → 테스트**

---

## 0. 미리 알아둘 것 (요건)
| 항목 | 필요값 |
|------|--------|
| PHP | **7.1 이상** (8.x 권장) — 기본 5.x면 폼 동작 안 함 |
| DB | MySQL/MariaDB 1개 (Cafe24 웹호스팅 기본 포함) |
| 메일 | PHP `mail()` (Cafe24 지원) |
| 도메인 | `www.thewhhylab.com` (canonical, 메인 사이트) · `thewhhylab.ai.kr`(AX_Discovery 전용 서브서버) |
| 문서 루트 | 보통 `/www/` |

준비물: **Cafe24 호스팅 계정**, **FTP 클라이언트**(FileZilla 등), 프로젝트 폴더 전체.

---

## A. Cafe24 웹호스팅 상품 준비
1. cafe24.com 로그인 → **호스팅센터 > 웹호스팅** 신청(이미 있으면 생략). 절약형 이상이면 PHP+MySQL 포함.
2. 도메인 연결: **나의서비스관리 > 도메인 연결**에서 `www.thewhhylab.com` 을 이 웹호스팅에 연결.
   (도메인을 Cafe24에서 산 게 아니면, 도메인 등록기관에서 네임서버를 Cafe24로 지정하거나 A레코드를 호스팅 IP로.)

## B. 접속 정보 확인 (FTP / DB)
**나의서비스관리 > 웹호스팅 > 접속정보** 에서 메모:
- FTP 호스트(보통 도메인 또는 `ftp.도메인`), **FTP 아이디 / 비밀번호**, 포트 21
- **문서 루트 경로**(보통 `/www/`)
- MySQL: 호스트 `localhost`, **DB명 / DB 아이디 / DB 비밀번호** (보통 호스팅 아이디와 동일 계열)

## C. 파일 업로드 (FTP → /www/)
FileZilla로 FTP 접속 후 **프로젝트 폴더 전체를 `/www/` 아래에 업로드**.
폼 action 이 `/api/submit.php`(루트 절대경로)라 **반드시 문서 루트에** 올려야 `https://도메인/api/submit.php` 로 매칭됩니다.

**업로드하지 말 것(불필요/민감):**
```
.git/   test/   docs/   node_modules/   *.bak
```
- `api/config.php` 는 저장소에 없음 → **F단계에서 직접 생성**.
- `db/schema.sql` 은 임포트용 → 올릴 필요 없음(올려도 루트 `.htaccess`가 `.sql` 접근 차단).

업로드 후 구조 확인:
```
/www/index.html
/www/api/submit.php
/www/api/lib/...
/www/api/uploads/        ← 비어있는 폴더(+ .htaccess)
/www/shared/ ...
```

## D. PHP 버전 7.1+ 로 설정  ⚠️중요
**나의서비스관리 > 웹호스팅 > PHP 버전관리(또는 PHP 설정)** → **7.4 또는 8.x 선택 후 저장**.
> 기본값이 PHP 5.x면 코드(`??`, `random_bytes`, 타입힌트)가 안 돌아 폼이 전부 실패합니다. 이 단계 필수.

## E. MySQL DB 생성 + 스키마 임포트
1. **나의서비스관리 > MySQL 관리** 에서 DB가 없으면 생성. 접속정보(DB명/아이디/비번/호스트=localhost) 확인.
2. **phpMyAdmin** 접속(같은 메뉴에 링크) → 해당 DB 선택 → **가져오기(Import)** →
   로컬의 `db/schema.sql` 파일 업로드 실행.
3. 생성 확인: 테이블 **`inquiries`**(교육문의), **`member_applications`**(강사지원) 2개.

## F. config.php 생성 (서버 비밀값)
로컬에서 `api/config.sample.php` 를 복사해 **`api/config.php`** 로 만들고 실제 값 입력 → FTP로 `/www/api/` 에 업로드.
(또는 Cafe24 **파일관리자**에서 직접 생성/편집)
```php
<?php
return [
  'db' => [
    'host' => 'localhost',
    'name' => '여기에 DB명',
    'user' => '여기에 DB아이디',
    'pass' => '여기에 DB비밀번호',
    'charset' => 'utf8mb4',
  ],
  'mail_to'   => 'thewhhylab@naver.com',       // 접수 알림 받을 주소
  'mail_from' => 'master@thewhhylab.com',  // 발신 표기(호스팅 도메인 권장)
  'upload_dir' => '',                           // 비우면 api/uploads/ 사용
  'max_upload_bytes' => 10 * 1024 * 1024,       // 10MB (유지)
  'allowed_ext' => ['pdf','ppt','pptx','doc','docx','jpg','jpeg','png'],
];
```
> `api/config.php` 는 `.gitignore` 처리되며, 루트 `.htaccess`가 웹에서 `/api/config.php` 직접 열람도 차단합니다.

## G. 업로드 폴더 권한 + 용량 한도
1. `api/uploads/` 폴더 **쓰기 권한 0755**(FTP에서 우클릭 > 파일권한). 강사 프로필 파일이 여기 저장됨.
   `api/uploads/.htaccess`(Deny)로 외부 직접 접근은 차단됨 — 브라우저로 `/api/uploads/아무파일` 열어 **차단(403)** 확인.
2. **파일 업로드 10MB** 가 되려면 PHP 한도가 충분해야 함:
   - 함께 올라간 **`api/.user.ini`** 가 `upload_max_filesize=12M / post_max_size=13M` 로 설정함 → **PHP가 CGI/FastCGI면 자동 적용**.
   - PHP가 **mod_php(아파치 모듈)** 면 `.user.ini`가 무시되므로, **나의서비스관리 > 웹호스팅 > PHP 설정/php.ini** 에서 `upload_max_filesize`·`post_max_size` 를 직접 12M 이상으로.

## H. SSL(HTTPS) + 루트 .htaccess
1. **나의서비스관리 > 부가서비스 > 무료 SSL 보안서버(Let's Encrypt)** 신청·발급(반영까지 수십 분). → **2026-06-06 발급 완료(두 도메인)**.
2. 루트 **`.htaccess`** 의 "5) HTTPS 강제" 블록은 SSL 발급 완료에 맞춰 **이미 활성화(주석 해제)되어 있음**. http 접속을 https로 301 리다이렉트.
   ```apache
   <IfModule mod_rewrite.c>
     RewriteEngine On
     RewriteCond %{HTTPS} off
     RewriteCond %{HTTP:X-Forwarded-Proto} !https
     RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
   </IfModule>
   ```
   > ⚠️ 만약 어떤 도메인이 아직 SSL 미발급 상태라면, 그 도메인은 위 블록 때문에 접속 불가가 됨 → 발급 전이면 각 줄 앞에 `#`을 다시 붙여 비활성화할 것.
   - 폼은 이름·연락처·파일 등 개인정보를 보내므로 HTTPS 권장.

## I. 메일(mail) / 스팸 회피
- `api/lib/mailer.php` 는 PHP `mail()` 사용. Cafe24는 지원하지만:
  - **발신(`mail_from`) 도메인을 호스팅 도메인과 일치**시키고, 도메인 DNS에 **SPF 레코드**(`v=spf1 include:cafe24.com ~all` 류 — Cafe24 안내 따름) 추가하면 스팸함 행 방지.
  - 네이버(`thewhhylab@naver.com`) 수신이 누락되면: 발신을 호스팅 도메인 메일로 바꾸거나, `mailer.php` 를 **PHPMailer(SMTP)** 로 교체.

## J. 동작 테스트
1. `https://www.thewhhylab.com/contact/` 정상값 제출 →
   - 화면에 "문의가 정상 접수되었습니다" + phpMyAdmin `inquiries` 테이블에 **새 행** + 알림 메일 수신.
2. `https://www.thewhhylab.com/apply/` 에서 파일 첨부 제출 →
   - `member_applications` 행 생성 + `api/uploads/` 에 난수 파일명 저장 + 메일에 원본 파일명 안내.
3. 스팸 방지 동작(개발자도구 불필요, 정상 사용이면 통과): 허니팟·3초 미만 제출 차단은 자동.

## K. 트러블슈팅
| 증상 | 원인/해결 |
|------|-----------|
| 제출 시 **"서버 설정이 필요합니다"** | `api/config.php` 없음 → F단계 |
| 제출 시 **500 / "서버 오류"** | DB 접속정보 오류(config의 name/user/pass) 또는 PHP 5.x → D·E·F 확인 |
| **"알 수 없는 폼 유형"** | (이 코드엔 form_type hidden 있음) 폼 마크업 손상 의심 |
| 파일 업로드 실패(큰 파일) | PHP 업로드 한도 < 10MB → G-2 |
| 메일 미수신 | mail() 정책/SPF → I단계, 또는 SMTP 전환 |
| `/api/uploads/파일` 이 열림 | `.htaccess` 미적용 → uploads/.htaccess 업로드 확인 |
| 한글 깨짐 | DB charset `utf8mb4` 확인(schema·config 모두 utf8mb4) |
| (선택) 서버 문법 점검 | SSH 가능 시 `php -l api/submit.php` |

## L. 운영 — 접수 데이터 확인
- **문의/지원 내역**: phpMyAdmin 에서 `inquiries`·`member_applications` 테이블 조회.
- **첨부 파일**: FTP로 `api/uploads/` 에서 다운로드(메일의 "저장명"으로 매칭).
- 정기 백업: MySQL 관리에서 DB 덤프, `api/uploads/` 폴더 주기적 백업 권장.

---

### 한눈에 보는 순서 체크박스
- [ ] A. 웹호스팅 + 도메인 연결
- [ ] B. FTP/DB 접속정보 확보
- [ ] C. `/www/` 에 파일 업로드(.git/test/docs 제외)
- [ ] D. PHP 7.1+ 선택
- [ ] E. DB 생성 + `db/schema.sql` 임포트(테이블 2개)
- [ ] F. `api/config.php` 생성(DB 비밀값)
- [ ] G. `api/uploads/` 0755 + 업로드 한도 12M
- [ ] H. 무료 SSL 발급 → `.htaccess` HTTPS 주석 해제
- [ ] I. 메일 발신/SPF 점검
- [ ] J. /contact/·/apply/ 실제 제출 테스트
