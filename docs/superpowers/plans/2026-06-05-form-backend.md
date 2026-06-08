# 폼 백엔드화 (Phase 1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 구글폼 의존을 제거하고, 자체 폼 페이지 2개(`/contact/`, `/apply/`)와 PHP 엔드포인트로 제출을 받아 MySQL 저장 + 이메일 알림을 처리한다.

**Architecture:** 정적 HTML 폼 페이지 → `fetch`로 `/api/submit.php`에 multipart POST → 서버에서 검증·MySQL 저장·메일 발송 후 JSON 응답 → 페이지가 aria-live로 결과 표시. 폼은 `action`/`method`도 지정해 JS 미동작 시에도 제출되도록 점진적 향상. 기존 디자인 토큰(`shared/css/site.css`) 재사용으로 외형 일관.

**Tech Stack:** Vanilla JS(fetch), PHP 7.4+/PDO, MySQL, jsdom(테스트), Node v24.

**환경 메모:**
- 셸 PowerShell. 정적 서버: `py -m http.server 8765 --bind 127.0.0.1`.
- **로컬에 PHP 없음** → `php -l` 문법검사·실제 DB/메일 동작은 호스팅 업로드 후 확인(Task 10 체크리스트). 로컬 테스트는 JS(jsdom)·HTML 육안에 집중.
- 폴더는 git 미초기화. 커밋 단계는 git 사용 시에만 수행(미사용이면 건너뛰고 `.bak` 백업으로 대체).

**원문 데이터 출처:** 폼 항목·선택지 verbatim은 `docs/superpowers/specs/2026-06-05-form-backend-and-renewal-design.md` §4 참조. 본 플랜의 HTML 작성 시 그 목록을 그대로 사용한다.

---

## File Structure

| 파일 | 책임 |
|---|---|
| `db/schema.sql` | 테이블 2개 생성 DDL |
| `api/config.sample.php` | 설정 템플릿(DB·수신메일·업로드 경로) |
| `api/lib/db.php` | PDO 연결 1개 함수 |
| `api/lib/validate.php` | 검증 헬퍼(필수/형식/파일) |
| `api/lib/mailer.php` | 메일 발송 헬퍼(mail() 격리) |
| `api/submit.php` | 진입점: 분기·검증·저장·메일·JSON 응답 |
| `api/uploads/.htaccess` | 업로드 폴더 외부 접근 차단 |
| `shared/css/site.css` | (기존 파일에 추가) `.form-*` 스타일 블록 |
| `shared/js/forms.js` | 폼 공통: 클라 검증·fetch 제출·상태 표시 |
| `contact/index.html` | 일반 교육 문의 폼 페이지 |
| `apply/index.html` | 교육 멤버 모집 폼 페이지 |
| `test/forms.test.mjs` | jsdom 스모크 테스트 |
| `test/package.json` | 테스트용 jsdom 의존성 |

---

## Task 1: DB 스키마 + 설정 템플릿

**Files:**
- Create: `db/schema.sql`
- Create: `api/config.sample.php`

- [ ] **Step 1: 스키마 작성**

`db/schema.sql`:
```sql
-- 더와이랩 폼 백엔드 스키마 (utf8mb4)
CREATE TABLE IF NOT EXISTS inquiries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  email VARCHAR(190) NOT NULL,
  affiliation VARCHAR(255) NOT NULL,
  topic VARCHAR(255) NOT NULL,
  purpose TEXT NOT NULL,
  audience VARCHAR(255) NOT NULL,
  location VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  privacy_agreed TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  ip VARCHAR(45) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS member_applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  referral VARCHAR(150) NULL,
  privacy_agreed TINYINT(1) NOT NULL DEFAULT 0,
  name VARCHAR(100) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  gender VARCHAR(10) NULL,
  birth_year CHAR(4) NULL,
  region VARCHAR(120) NULL,
  career_years VARCHAR(60) NULL,
  prev_career VARCHAR(500) NULL,
  fields TEXT NULL,
  styles TEXT NULL,
  intro TEXT NULL,
  client_types VARCHAR(255) NULL,
  min_fee VARCHAR(100) NULL,
  profile_file VARCHAR(255) NULL,
  profile_original VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  ip VARCHAR(45) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 2: 설정 템플릿 작성**

`api/config.sample.php` (실제 서버에서는 이 파일을 `config.php`로 복사 후 값 채움):
```php
<?php
// 복사: config.sample.php -> config.php 후 실제 값 입력. config.php는 저장소에 올리지 않음.
return [
  'db' => [
    'host' => 'localhost',
    'name' => 'DB_NAME',
    'user' => 'DB_USER',
    'pass' => 'DB_PASS',
    'charset' => 'utf8mb4',
  ],
  // 알림 수신 메일
  'mail_to' => 'thewhhylab@naver.com',
  // 발신 표기 (호스팅 도메인 메일 권장; mail() 기본 동작)
  'mail_from' => 'master@thewhhylab.com',
  // 업로드 저장 절대경로 (웹 비공개 권장). 비우면 api/uploads/ 사용.
  'upload_dir' => '',
  'max_upload_bytes' => 10 * 1024 * 1024, // 10MB
  'allowed_ext' => ['pdf','ppt','pptx','doc','docx','jpg','jpeg','png'],
];
```

- [ ] **Step 3: 커밋(또는 백업 불필요 — 신규 파일)**
```bash
git add db/schema.sql api/config.sample.php
git commit -m "feat(forms): add db schema and config template"
```

---

## Task 2: PHP 라이브러리 (db / validate / mailer)

**Files:**
- Create: `api/lib/db.php`, `api/lib/validate.php`, `api/lib/mailer.php`

- [ ] **Step 1: DB 헬퍼**

`api/lib/db.php`:
```php
<?php
function db_connect(array $cfg): PDO {
  $dsn = "mysql:host={$cfg['host']};dbname={$cfg['name']};charset={$cfg['charset']}";
  return new PDO($dsn, $cfg['user'], $cfg['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
}
```

- [ ] **Step 2: 검증 헬퍼**

`api/lib/validate.php`:
```php
<?php
// 필수 문자열: 트림 후 비어있지 않아야 함
function v_required(?string $s): bool { return $s !== null && trim($s) !== ''; }
function v_email(?string $s): bool { return (bool)filter_var((string)$s, FILTER_VALIDATE_EMAIL); }
function v_year(?string $s): bool { return (bool)preg_match('/^\d{4}$/', (string)$s); }
function v_phone(?string $s): bool { return (bool)preg_match('/^[0-9\-]{8,20}$/', (string)$s); }

// 다중선택(배열/문자열) → 콤마 결합 문자열
function v_join($val): string {
  if (is_array($val)) return implode(', ', array_map('trim', $val));
  return trim((string)$val);
}

// 파일 검증. 업로드 없으면 [ok=>true, skip=>true]. 오류 시 ok=>false,error
function v_file(array $cfg, ?array $f): array {
  if (!$f || !isset($f['error']) || $f['error'] === UPLOAD_ERR_NO_FILE) {
    return ['ok' => true, 'skip' => true];
  }
  if ($f['error'] !== UPLOAD_ERR_OK) return ['ok' => false, 'error' => '파일 업로드에 실패했습니다.'];
  if ($f['size'] > $cfg['max_upload_bytes']) return ['ok' => false, 'error' => '파일이 너무 큽니다(최대 10MB).'];
  $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, $cfg['allowed_ext'], true)) return ['ok' => false, 'error' => '허용되지 않는 파일 형식입니다.'];
  return ['ok' => true, 'skip' => false, 'ext' => $ext];
}
```

- [ ] **Step 3: 메일 헬퍼 (mail() 격리 — 추후 SMTP 교체 지점)**

`api/lib/mailer.php`:
```php
<?php
// 알림 메일 발송. 제목/본문은 호출측에서 구성. 실패해도 예외 던지지 않음(저장은 이미 완료).
function send_notification(array $cfg, string $subject, string $bodyText, ?string $replyTo = null): bool {
  $to = $cfg['mail_to'];
  $headers = [];
  $headers[] = 'From: ' . $cfg['mail_from'];
  if ($replyTo) $headers[] = 'Reply-To: ' . $replyTo;
  $headers[] = 'Content-Type: text/plain; charset=UTF-8';
  $headers[] = 'MIME-Version: 1.0';
  $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
  return @mail($to, $encodedSubject, $bodyText, implode("\r\n", $headers));
}
```
> 메모: 멤버 폼 첨부는 v1에서 "서버 저장 + 메일 본문에 파일 경로/원본명 안내"로 처리(첨부 미포함, mail() 첨부는 불안정). 도달률/첨부 필요 시 이 파일을 PHPMailer로 교체.

- [ ] **Step 4: 커밋**
```bash
git add api/lib/db.php api/lib/validate.php api/lib/mailer.php
git commit -m "feat(forms): add php db/validate/mailer helpers"
```

---

## Task 3: 제출 엔드포인트 + 업로드 보호

**Files:**
- Create: `api/submit.php`, `api/uploads/.htaccess`

- [ ] **Step 1: 업로드 폴더 보호**

`api/uploads/.htaccess`:
```apache
Deny from all
```

- [ ] **Step 2: submit.php 작성**

`api/submit.php`:
```php
<?php
header('Content-Type: application/json; charset=UTF-8');

$base = __DIR__;
$cfgFile = $base . '/config.php';
if (!is_file($cfgFile)) { http_response_code(500); echo json_encode(['ok'=>false,'message'=>'서버 설정이 필요합니다.']); exit; }
$cfg = require $cfgFile;
require $base . '/lib/db.php';
require $base . '/lib/validate.php';
require $base . '/lib/mailer.php';

function fail($msg, $code = 400) { http_response_code($code); echo json_encode(['ok'=>false,'message'=>$msg]); exit; }
function ok($msg) { echo json_encode(['ok'=>true,'message'=>$msg]); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('잘못된 요청입니다.', 405);

// 스팸 차단: 허니팟(빈 값이어야 함) + 최소 제출시간(3초)
if (!empty($_POST['company_website'])) ok('접수되었습니다.'); // 봇: 조용히 성공처럼 처리
$ts = (int)($_POST['form_ts'] ?? 0);
if ($ts > 0 && (time() * 1000 - $ts) < 3000) fail('너무 빠른 제출입니다. 잠시 후 다시 시도해주세요.');

$type = $_POST['form_type'] ?? '';
$ip = $_SERVER['REMOTE_ADDR'] ?? null;
$now = date('Y-m-d H:i:s');

try {
  $pdo = db_connect($cfg['db']);

  if ($type === 'contact') {
    $f = [
      'name' => $_POST['name'] ?? '', 'phone' => $_POST['phone'] ?? '',
      'email' => $_POST['email'] ?? '', 'affiliation' => $_POST['affiliation'] ?? '',
      'topic' => $_POST['topic'] ?? '', 'purpose' => $_POST['purpose'] ?? '',
      'audience' => $_POST['audience'] ?? '', 'location' => $_POST['location'] ?? '',
      'message' => $_POST['message'] ?? '',
    ];
    foreach ($f as $k => $v) { if (!v_required($v)) fail('모든 필수 항목을 입력해주세요.'); }
    if (!v_email($f['email'])) fail('이메일 형식이 올바르지 않습니다.');
    if (!v_phone($f['phone'])) fail('연락처 형식이 올바르지 않습니다.');
    if (empty($_POST['privacy_agreed'])) fail('개인정보 수집·이용에 동의해주세요.');

    $sql = 'INSERT INTO inquiries (name,phone,email,affiliation,topic,purpose,audience,location,message,privacy_agreed,created_at,ip)
            VALUES (:name,:phone,:email,:affiliation,:topic,:purpose,:audience,:location,:message,1,:created_at,:ip)';
    $st = $pdo->prepare($sql);
    $st->execute($f + ['created_at'=>$now,'ip'=>$ip]);

    $body = "[교육 문의]\n";
    foreach (['name'=>'이름','phone'=>'연락처','email'=>'이메일','affiliation'=>'소속','topic'=>'교육주제','purpose'=>'교육목적','audience'=>'교육대상','location'=>'교육장소','message'=>'문의내용/RFP'] as $k=>$lbl) {
      $body .= "{$lbl}: {$f[$k]}\n";
    }
    send_notification($cfg, '[더와이랩] 교육 문의 접수', $body, $f['email']);
    ok('문의가 정상 접수되었습니다. 빠르게 연락드리겠습니다.');
  }

  if ($type === 'member') {
    if (($_POST['privacy_agreed'] ?? '') !== 'yes') fail('개인정보 수집·활용에 동의해야 지원할 수 있습니다.');
    $name = $_POST['name'] ?? ''; $phone = $_POST['phone'] ?? '';
    if (!v_required($name) || !v_required($phone)) fail('성함과 연락처는 필수입니다.');
    if (!v_phone($phone)) fail('연락처 형식이 올바르지 않습니다(010-0000-0000).');
    $birth = $_POST['birth_year'] ?? '';
    if ($birth !== '' && !v_year($birth)) fail('출생년도는 4자리 숫자로 입력해주세요.');

    // 파일
    $chk = v_file($cfg, $_FILES['profile'] ?? null);
    if (!$chk['ok']) fail($chk['error']);
    $storedName = null; $originalName = null;
    if (empty($chk['skip'])) {
      $dir = $cfg['upload_dir'] !== '' ? rtrim($cfg['upload_dir'],'/\\') : ($base . '/uploads');
      if (!is_dir($dir)) @mkdir($dir, 0750, true);
      $originalName = $_FILES['profile']['name'];
      $storedName = bin2hex(random_bytes(16)) . '.' . $chk['ext'];
      if (!move_uploaded_file($_FILES['profile']['tmp_name'], $dir . '/' . $storedName)) fail('파일 저장에 실패했습니다.', 500);
    }

    $row = [
      'referral' => v_join($_POST['referral'] ?? ''),
      'name' => $name, 'phone' => $phone,
      'gender' => $_POST['gender'] ?? '', 'birth_year' => $birth,
      'region' => $_POST['region'] ?? '', 'career_years' => $_POST['career_years'] ?? '',
      'prev_career' => $_POST['prev_career'] ?? '',
      'fields' => v_join($_POST['fields'] ?? []),
      'styles' => v_join($_POST['styles'] ?? []),
      'intro' => $_POST['intro'] ?? '',
      'client_types' => v_join($_POST['client_types'] ?? []),
      'min_fee' => $_POST['min_fee'] ?? '',
      'profile_file' => $storedName, 'profile_original' => $originalName,
      'created_at' => $now, 'ip' => $ip,
    ];
    $cols = implode(',', array_keys($row));
    $ph = ':' . implode(',:', array_keys($row));
    $st = $pdo->prepare("INSERT INTO member_applications ($cols, privacy_agreed) VALUES ($ph, 1)");
    $st->execute($row);

    $body = "[교육 멤버 지원]\n";
    foreach (['name'=>'성함','phone'=>'연락처','gender'=>'성별','birth_year'=>'출생년도','region'=>'거주지역','career_years'=>'경력기간','prev_career'=>'주요경력','fields'=>'강의분야','styles'=>'강의스타일','intro'=>'소개','client_types'=>'출강기관','min_fee'=>'최소섭외비용','referral'=>'신청경로'] as $k=>$lbl) {
      $body .= "{$lbl}: {$row[$k]}\n";
    }
    $body .= '프로필파일: ' . ($originalName ? "{$originalName} (저장명 {$storedName})" : '없음') . "\n";
    send_notification($cfg, '[더와이랩] 교육 멤버 지원 접수', $body);
    ok('지원이 정상 접수되었습니다. 검토 후 개별 연락드리겠습니다.');
  }

  fail('알 수 없는 폼 유형입니다.');
} catch (Throwable $e) {
  fail('서버 오류가 발생했습니다. 잠시 후 다시 시도해주세요.', 500);
}
```

- [ ] **Step 3: 커밋**
```bash
git add api/submit.php api/uploads/.htaccess
git commit -m "feat(forms): add submit endpoint with spam/file handling"
```

---

## Task 4: 폼 스타일 (site.css에 추가)

**Files:**
- Modify: `shared/css/site.css` (파일 끝에 블록 추가)

- [ ] **Step 1: 작업 전 백업**

PowerShell: `Copy-Item shared\css\site.css shared\css\site.css.bak`

- [ ] **Step 2: 스타일 블록을 파일 끝에 추가**

기존 토큰(`--brand`, `--ink`, `--muted`, `--line`, `--bg-soft`, `--max`, `--nav-h`, `--f-display`) 재사용. `shared/css/site.css` 맨 끝에 append:
```css
/* ===== Form pages (contact / apply) ===== */
.form-wrap { max-width: 760px; margin: 0 auto; padding: calc(var(--nav-h) + 48px) 20px 96px; }
.form-wrap .form-intro { color: var(--muted); line-height: 1.8; font-size: 15px; white-space: pre-line; margin-bottom: 32px; }
.form-wrap h1 { font-family: var(--f-display); font-size: clamp(28px,5vw,42px); letter-spacing: -0.03em; margin: 0 0 12px; }
.form-field { margin-bottom: 22px; }
.form-field > label { display: block; font-weight: 700; font-size: 15px; margin-bottom: 8px; color: var(--ink); }
.form-field .req { color: #e0245e; margin-left: 4px; }
.form-field .hint { display: block; font-weight: 400; font-size: 13px; color: var(--muted); margin-top: 4px; }
.form-field input[type=text], .form-field input[type=email], .form-field input[type=tel], .form-field textarea, .form-field select {
  width: 100%; padding: 13px 14px; border: 1px solid var(--line); border-radius: 12px;
  font: inherit; font-size: 15px; background: #fff; color: var(--ink); box-sizing: border-box;
  transition: border-color var(--t-fast) var(--ease);
}
.form-field input:focus-visible, .form-field textarea:focus-visible, .form-field select:focus-visible {
  outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-soft);
}
.form-field textarea { min-height: 120px; resize: vertical; }
.form-field.invalid input, .form-field.invalid textarea, .form-field.invalid select { border-color: #e0245e; }
.form-field .err-msg { display: none; color: #e0245e; font-size: 13px; margin-top: 6px; }
.form-field.invalid .err-msg { display: block; }
.check-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px,1fr)); gap: 8px 16px; }
.check-item { display: flex; align-items: flex-start; gap: 8px; font-size: 14px; font-weight: 400; line-height: 1.5; }
.check-item input { margin-top: 3px; flex: none; }
.form-consent { display: flex; align-items: flex-start; gap: 10px; padding: 16px; background: var(--bg-soft); border-radius: 12px; font-size: 14px; }
.form-honey { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
.form-submit { width: 100%; padding: 16px; border: none; border-radius: 14px; background: var(--brand); color: #fff; font: inherit; font-weight: 700; font-size: 16px; cursor: pointer; transition: background var(--t-fast) var(--ease); }
.form-submit:hover { background: var(--brand-2); }
.form-submit:disabled { opacity: 0.6; cursor: progress; }
.form-status { margin-top: 18px; padding: 14px 16px; border-radius: 12px; font-size: 15px; display: none; }
.form-status.show { display: block; }
.form-status.success { background: #e7f6ec; color: #1a7f43; }
.form-status.error { background: #fdecef; color: #b3123e; }
```

- [ ] **Step 3: 문법 확인 (육안 + 후속 페이지 로드)**

`py -m http.server`로 로드 시 콘솔 CSS 파싱 오류 없음 확인(Task 9에서 페이지와 함께).

- [ ] **Step 4: 커밋**
```bash
git add shared/css/site.css
git commit -m "feat(forms): add form page styles"
```

---

## Task 5: 폼 공통 JS (TDD)

**Files:**
- Create: `shared/js/forms.js`
- Create: `test/package.json`, `test/forms.test.mjs`

설계: `forms.js`는 전역 함수 `initForm(formEl)`를 노출(테스트 가능). DOMContentLoaded 시 `form[data-form]`를 찾아 자동 초기화. 검증은 `[required]`·`[data-validate]`·동의 체크 기준. 제출은 `fetch`로 FormData 전송 후 `.form-status`에 결과 표시.

- [ ] **Step 1: 테스트 의존성 정의**

`test/package.json`:
```json
{
  "name": "thewhhylab-tests",
  "private": true,
  "type": "module",
  "version": "1.0.0",
  "devDependencies": { "jsdom": "^24.0.0" }
}
```
Run: `cd test; npm install`  Expected: jsdom 설치 완료.

- [ ] **Step 2: 실패하는 테스트 작성**

`test/forms.test.mjs`:
```javascript
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';

const formsSrc = readFileSync(new URL('../shared/js/forms.js', import.meta.url), 'utf8');

function setup(formHtml) {
  const dom = new JSDOM(`<!doctype html><body>${formHtml}</body>`, { runScripts: 'outside-only', url: 'http://localhost/' });
  const { window } = dom;
  // forms.js 평가
  window.eval(formsSrc);
  return window;
}

const contactForm = `
<form data-form="contact" action="/api/submit.php" method="post">
  <input type="hidden" name="form_type" value="contact">
  <input type="hidden" name="form_ts" value="0">
  <div class="form-field"><label>이름<span class="req">*</span></label>
    <input type="text" name="name" required><span class="err-msg">필수입니다.</span></div>
  <div class="form-field"><label>이메일<span class="req">*</span></label>
    <input type="email" name="email" required data-validate="email"><span class="err-msg">이메일 형식</span></div>
  <label class="form-consent"><input type="checkbox" name="privacy_agreed" required value="1">동의</label>
  <input class="form-honey" type="text" name="company_website" tabindex="-1" autocomplete="off">
  <button type="submit" class="form-submit">제출</button>
  <div class="form-status" role="status" aria-live="polite"></div>
</form>`;

test('필수값 비면 제출 차단 + invalid 가시화', () => {
  const w = setup(contactForm);
  const form = w.document.querySelector('form');
  w.initForm(form);
  form.dispatchEvent(new w.Event('submit', { cancelable: true, bubbles: true }));
  const nameField = w.document.querySelector('input[name=name]').closest('.form-field');
  assert.equal(nameField.classList.contains('invalid'), true);
});

test('이메일 형식 오류 감지', () => {
  const w = setup(contactForm);
  const form = w.document.querySelector('form');
  w.document.querySelector('input[name=name]').value = '홍길동';
  w.document.querySelector('input[name=email]').value = 'not-an-email';
  w.document.querySelector('input[name=privacy_agreed]').checked = true;
  w.initForm(form);
  form.dispatchEvent(new w.Event('submit', { cancelable: true, bubbles: true }));
  const emailField = w.document.querySelector('input[name=email]').closest('.form-field');
  assert.equal(emailField.classList.contains('invalid'), true);
});

test('허니팟 채워지면 차단(검증 통과해도 제출 안 함)', () => {
  const w = setup(contactForm);
  let fetched = false;
  w.fetch = async () => { fetched = true; return { json: async () => ({ ok: true, message: 'ok' }) }; };
  const form = w.document.querySelector('form');
  w.document.querySelector('input[name=name]').value = '홍길동';
  w.document.querySelector('input[name=email]').value = 'a@b.com';
  w.document.querySelector('input[name=privacy_agreed]').checked = true;
  w.document.querySelector('input[name=company_website]').value = 'bot';
  w.initForm(form);
  form.dispatchEvent(new w.Event('submit', { cancelable: true, bubbles: true }));
  assert.equal(fetched, false);
});

test('정상 입력 시 fetch 호출 + 성공 메시지 가시화', async () => {
  const w = setup(contactForm);
  w.fetch = async () => ({ json: async () => ({ ok: true, message: '접수 완료' }) });
  const form = w.document.querySelector('form');
  w.document.querySelector('input[name=name]').value = '홍길동';
  w.document.querySelector('input[name=email]').value = 'a@b.com';
  w.document.querySelector('input[name=privacy_agreed]').checked = true;
  w.initForm(form);
  form.dispatchEvent(new w.Event('submit', { cancelable: true, bubbles: true }));
  await new Promise(r => setTimeout(r, 20));
  const status = w.document.querySelector('.form-status');
  assert.equal(status.classList.contains('show'), true);
  assert.equal(status.classList.contains('success'), true);
  assert.match(status.textContent, /접수 완료/);
});
```

- [ ] **Step 3: 테스트 실행 → 실패 확인**

Run: `cd test; node --test`
Expected: FAIL (`initForm is not a function` / forms.js 없음).

- [ ] **Step 4: forms.js 구현**

`shared/js/forms.js`:
```javascript
/* 더와이랩 — 폼 페이지 공통 스크립트 */
(function (root) {
  function fieldOf(el) { return el.closest('.form-field'); }
  function setInvalid(el, bad) {
    var f = fieldOf(el) || (el.closest('.form-consent'));
    if (!f) return;
    f.classList.toggle('invalid', !!bad);
  }
  function validate(form) {
    var firstBad = null;
    // required (text/email/tel/textarea/select)
    var inputs = form.querySelectorAll('[required]');
    inputs.forEach(function (el) {
      var bad = false;
      if (el.type === 'checkbox') { bad = !el.checked; }
      else if (el.type === 'radio') {
        bad = !form.querySelector('input[name="' + el.name + '"]:checked');
      } else { bad = el.value.trim() === ''; }
      if (!bad && el.dataset.validate === 'email') {
        bad = !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(el.value.trim());
      }
      if (!bad && el.dataset.validate === 'year') { bad = !/^\d{4}$/.test(el.value.trim()); }
      setInvalid(el, bad);
      if (bad && !firstBad) firstBad = el;
    });
    return firstBad;
  }
  function showStatus(form, type, msg) {
    var s = form.querySelector('.form-status');
    if (!s) return;
    s.textContent = msg;
    s.classList.remove('success', 'error');
    s.classList.add('show', type);
  }
  function initForm(form) {
    if (!form) return;
    var ts = form.querySelector('input[name=form_ts]');
    if (ts) ts.value = String(Date.now());
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      // 허니팟
      var honey = form.querySelector('.form-honey input, input.form-honey');
      if (honey && honey.value) { showStatus(form, 'success', '접수되었습니다.'); return; }
      var bad = validate(form);
      if (bad) { if (bad.focus) bad.focus(); showStatus(form, 'error', '입력 내용을 확인해주세요.'); return; }
      var btn = form.querySelector('.form-submit');
      if (btn) btn.disabled = true;
      var data = new FormData(form);
      root.fetch(form.action, { method: 'POST', body: data })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res && res.ok) {
            showStatus(form, 'success', res.message || '접수되었습니다.');
            form.reset();
          } else {
            showStatus(form, 'error', (res && res.message) || '제출에 실패했습니다.');
          }
        })
        .catch(function () { showStatus(form, 'error', '네트워크 오류가 발생했습니다.'); })
        .finally(function () { if (btn) btn.disabled = false; });
    });
  }
  root.initForm = initForm;
  if (root.document) {
    root.document.addEventListener('DOMContentLoaded', function () {
      root.document.querySelectorAll('form[data-form]').forEach(initForm);
    });
  }
})(typeof window !== 'undefined' ? window : this);
```

- [ ] **Step 5: 테스트 실행 → 통과 확인**

Run: `cd test; node --test`
Expected: PASS (4 tests).

- [ ] **Step 6: 문법 확인**

Run: `node --check shared/js/forms.js`  Expected: 출력 없음(통과).

- [ ] **Step 7: 커밋**
```bash
git add shared/js/forms.js test/package.json test/forms.test.mjs
git commit -m "feat(forms): add client form script with jsdom tests"
```

---

## Task 6: 일반 문의 페이지 `/contact/`

**Files:**
- Create: `contact/index.html`

- [ ] **Step 1: 페이지 작성**

기존 페이지 헤더/메뉴/푸터 마크업을 `members-edu/index.html`에서 복사해 재사용(상대경로 `../shared/...` 유지). `<main>` 안을 아래 폼으로 구성. `data-page`는 임의 색 토큰 사용 가능하나 생략해도 됨.

`contact/index.html` (핵심 골격 — head/nav/footer는 기존 페이지에서 복사, main만 아래로):
```html
<main id="top">
  <div class="form-wrap">
    <div class="p-crumb"><a href="../index.html">Home</a><span class="sep">/</span><span>교육 문의</span></div>
    <h1>교육 문의하기</h1>
    <p class="form-intro">안녕하세요, 더와이랩입니다.
2026년, 더와이랩은 "AI로 일하는 방식이 바뀝니다. 더와이랩이 바꿉니다"라는 슬로건 아래, 사람 중심 교육을 통해 AI와 내일을 잇는 지속 가능한 혁신을 실현하고자 합니다.

본 설문은 교육 문의를 위한 1:1 상담 접수입니다. 남겨주신 내용을 담당자가 꼼꼼히 검토한 후, 고객님의 니즈에 가장 적합한 방향으로 제안드리겠습니다.

빠른 확인이 필요하신 경우: 전화 02-468-3555 · 이메일 thewhhylab@naver.com</p>

    <form data-form="contact" action="/api/submit.php" method="post" novalidate>
      <input type="hidden" name="form_type" value="contact">
      <input type="hidden" name="form_ts" value="0">
      <div class="form-honey" aria-hidden="true"><input type="text" name="company_website" tabindex="-1" autocomplete="off"></div>

      <div class="form-field"><label>이름<span class="req">*</span></label>
        <input type="text" name="name" required><span class="err-msg">이름을 입력해주세요.</span></div>
      <div class="form-field"><label>연락처<span class="req">*</span></label>
        <input type="tel" name="phone" required placeholder="010-0000-0000"><span class="err-msg">연락처를 입력해주세요.</span></div>
      <div class="form-field"><label>이메일 주소<span class="req">*</span></label>
        <input type="email" name="email" required data-validate="email"><span class="err-msg">올바른 이메일을 입력해주세요.</span></div>
      <div class="form-field"><label>회사명 / 부서명 / 직급(직책)<span class="req">*</span></label>
        <input type="text" name="affiliation" required><span class="err-msg">필수 항목입니다.</span></div>
      <div class="form-field"><label>교육 주제<span class="req">*</span></label>
        <input type="text" name="topic" required><span class="err-msg">필수 항목입니다.</span></div>
      <div class="form-field"><label>교육 진행 목적<span class="req">*</span><span class="hint">정확한 니즈 파악을 위해 구체적으로 작성 부탁드립니다.</span></label>
        <textarea name="purpose" required></textarea><span class="err-msg">필수 항목입니다.</span></div>
      <div class="form-field"><label>교육 대상<span class="req">*</span><span class="hint">직급 / 연령대 / 차수 / 인원 등</span></label>
        <input type="text" name="audience" required><span class="err-msg">필수 항목입니다.</span></div>
      <div class="form-field"><label>교육 장소<span class="req">*</span></label>
        <input type="text" name="location" required><span class="err-msg">필수 항목입니다.</span></div>
      <div class="form-field"><label>문의 내용 / RFP<span class="req">*</span><span class="hint">교육 관련 추가 요청사항을 구체적으로 작성해주시면 빠른 답변에 도움이 됩니다.</span></label>
        <textarea name="message" required></textarea><span class="err-msg">필수 항목입니다.</span></div>

      <div class="form-field">
        <label class="form-consent"><input type="checkbox" name="privacy_agreed" value="1" required>
          <span>개인정보 수집·이용에 동의합니다. (수집 항목: 이름·연락처·이메일·소속 / 목적: 문의 응대 / 보유: 문의 처리 후 파기)</span></label>
        <span class="err-msg">개인정보 수집·이용에 동의해주세요.</span>
      </div>

      <button type="submit" class="form-submit">문의 보내기</button>
      <div class="form-status" role="status" aria-live="polite"></div>
    </form>
  </div>
</main>
```
`<head>`의 `<title>`은 "교육 문의 · 더와이랩", `<script src="../shared/js/forms.js" defer></script>`를 `site.js` 다음에 추가.

- [ ] **Step 2: 문법/구조 확인**

`py -m http.server 8765 --bind 127.0.0.1` 후 http://127.0.0.1:8765/contact/ 접속 — 폼 렌더·스타일 적용 육안 확인(제출은 PHP 없어 동작 안 함, 정상).

- [ ] **Step 3: 커밋**
```bash
git add contact/index.html
git commit -m "feat(forms): add contact form page"
```

---

## Task 7: 멤버 모집 페이지 `/apply/`

**Files:**
- Create: `apply/index.html`

- [ ] **Step 1: 페이지 작성**

head/nav/footer는 `members-edu/index.html`에서 복사. `<main>`을 아래로 구성. 선택지 verbatim은 spec §4.2 목록(강의분야 35개, 강의스타일 8개, 출강기관 5개, 신청경로 6개)을 그대로 사용.

`apply/index.html` main 골격:
```html
<main id="top">
  <div class="form-wrap">
    <div class="p-crumb"><a href="../index.html">Home</a><span class="sep">/</span><span>교육 멤버 모집</span></div>
    <h1>교육 멤버(강사) 모집</h1>
    <p class="form-intro">2026년 더와이랩과 함께 성장할 교육 멤버(강사)를 모집합니다.
더와이랩은 "사람 중심 교육, AI를 품은 HRD"를 추구하는 강소 HRD 부띠끄 컨설팅 기업으로, 하나의 팀 정신(One Team Spirit)으로 고객의 가치를 높입니다.

1,000개 이상의 교육 수행 프로젝트, 멀티캠퍼스·신세계아이앤씨 협력사, 누적 강사비 40억 돌파로 성장 중입니다. 정기 전수 교육을 통해 멤버와의 WIN-WIN 성장을 지원합니다.

더와이랩이 더 궁금하시다면 <a href="../mission/index.html">더와이랩 소개</a>를 확인해 주세요.

설문은 10분 내외 소요되며, 마지막에 프로필 제출(파일 업로드, 선택)이 포함됩니다. 응답 후 제출해 주시면 검토하여 개별 연락드리겠습니다.</p>

    <form data-form="member" action="/api/submit.php" method="post" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="form_type" value="member">
      <input type="hidden" name="form_ts" value="0">
      <div class="form-honey" aria-hidden="true"><input type="text" name="company_website" tabindex="-1" autocomplete="off"></div>

      <!-- 1. 신청 경로 (단일선택 + 기타) -->
      <div class="form-field"><label>어느 경로를 통해 신청하셨나요?<span class="req">*</span></label>
        <select name="referral" required>
          <option value="">선택해주세요</option>
          <option>인스타그램</option><option>네이버카페 CS닷컴</option><option>네이버카페 강사쟁이</option>
          <option>오픈카톡방</option><option>더와이랩 멤버의 안내 및 추천</option><option>기타</option>
        </select><span class="err-msg">필수 항목입니다.</span></div>

      <!-- 2. 개인정보 동의 (radio yes/no) -->
      <div class="form-field"><label>개인정보 수집 및 활용에 동의하십니까?<span class="req">*</span>
        <span class="hint">개인정보는 더와이랩 내부에서 교육 멤버 지원 용도로만 사용됩니다.</span></label>
        <label class="check-item"><input type="radio" name="privacy_agreed" value="yes" required> 예, 동의합니다.</label>
        <label class="check-item"><input type="radio" name="privacy_agreed" value="no"> 아니오, 동의하지 않습니다.</label>
        <span class="err-msg">동의 여부를 선택해주세요.</span></div>

      <!-- 3~9 단답 -->
      <div class="form-field"><label>성함<span class="req">*</span></label><input type="text" name="name" required><span class="err-msg">필수 항목입니다.</span></div>
      <div class="form-field"><label>연락처<span class="req">*</span><span class="hint">010-0000-0000 형태로 기재해 주세요.</span></label><input type="tel" name="phone" required placeholder="010-0000-0000"><span class="err-msg">연락처를 확인해주세요.</span></div>
      <div class="form-field"><label>성별<span class="req">*</span></label>
        <label class="check-item"><input type="radio" name="gender" value="남" required> 남</label>
        <label class="check-item"><input type="radio" name="gender" value="녀"> 녀</label><span class="err-msg">성별을 선택해주세요.</span></div>
      <div class="form-field"><label>출생년도<span class="req">*</span><span class="hint">4자리 (예) 1980</span></label><input type="text" name="birth_year" required data-validate="year" inputmode="numeric"><span class="err-msg">4자리 숫자로 입력해주세요.</span></div>
      <div class="form-field"><label>현재 거주 지역<span class="req">*</span><span class="hint">출강 가능 지역 판단용. (예) 경기도 용인 또는 경상권 부산</span></label><input type="text" name="region" required><span class="err-msg">필수 항목입니다.</span></div>
      <div class="form-field"><label>강사 활동 경력 기간<span class="req">*</span><span class="hint">(예) 7년</span></label><input type="text" name="career_years" required><span class="err-msg">필수 항목입니다.</span></div>
      <div class="form-field"><label>강사 활동 전 주요 경력<span class="req">*</span><span class="hint">기업/기관 근무 경력 위주(최대 3개). (예) CJ 마케팅 부서, 삼성전자로지텍 교육그룹</span></label><input type="text" name="prev_career" required><span class="err-msg">필수 항목입니다.</span></div>

      <!-- 10. 강의분야 (체크박스 다중, 35개 + 기타) — spec §4.2 목록 그대로 -->
      <div class="form-field"><label>가능한 강의 분야 (중복 선택 가능)<span class="req">*</span><span class="hint">동일 주제로 5번 이상 출강한 경우만 선택해주세요.</span></label>
        <div class="check-grid">
          <!-- spec §4.2 문항10의 35개 항목을 각각 아래 형태로 작성:
               <label class="check-item"><input type="checkbox" name="fields[]" value="AI 기반 업무 적용 및 활용법"> AI 기반 업무 적용 및 활용법</label>
               ... (35개 전부) ... 마지막 '기타' 포함 -->
        </div></div>

      <!-- 11. 강의법·스타일 (체크박스 다중, 8개) -->
      <div class="form-field"><label>자신 있는 강의법과 강의 스타일 (중복 선택 가능)</label>
        <div class="check-grid">
          <!-- spec §4.2 문항11의 8개 항목:
               강의형 / 퍼실리테이션 중심 / 실습 중심 / 사례 공유 / 스토리텔링형 / 팀 활동 중심 / 에너지 넘치는 강의 / 안정감 있고 차분한 강의
               각각 <label class="check-item"><input type="checkbox" name="styles[]" value="강의형"> 강의형</label> -->
        </div></div>

      <!-- 12. 소개 -->
      <div class="form-field"><label>강점을 기반으로 한 간략한 소개<span class="req">*</span><span class="hint">(예) 15년차 조직문화 전문가, 게이미피케이션 기반 참여형 워크숍 특화</span></label><textarea name="intro" required></textarea><span class="err-msg">필수 항목입니다.</span></div>

      <!-- 13. 출강기관 (체크박스 다중, 5개) -->
      <div class="form-field"><label>주요 출강 기관 (중복 선택 가능)<span class="req">*</span></label>
        <div class="check-grid">
          <!-- 공공기관 또는 공기업 / 일반기업(대기업·중견·중소) / 대학교 / 초·중·고등학교 / 기타
               각각 name="client_types[]" -->
        </div></div>

      <!-- 14. 최소 섭외비용 -->
      <div class="form-field"><label>출강 가능한 최소 섭외 비용<span class="req">*</span><span class="hint">3시간 이상 출강 기준 시간당 단가. (예) 시간당 100,000원</span></label><input type="text" name="min_fee" required><span class="err-msg">필수 항목입니다.</span></div>

      <!-- 15. 프로필 (파일, 선택) -->
      <div class="form-field"><label>프로필 제출 <span class="hint">(선택) 역량을 잘 드러내는 기존 프로필이 있다면 업로드. PDF·PPT·DOC·이미지, 최대 10MB.</span></label>
        <input type="file" name="profile" accept=".pdf,.ppt,.pptx,.doc,.docx,.jpg,.jpeg,.png"></div>

      <button type="submit" class="form-submit">지원서 제출</button>
      <div class="form-status" role="status" aria-live="polite"></div>
    </form>
  </div>
</main>
```
`<title>`은 "교육 멤버 모집 · 더와이랩", `forms.js` 스크립트 태그 추가. **체크박스 그룹의 주석 부분은 spec §4.2 verbatim 목록을 실제 `<label class="check-item">`들로 모두 펼쳐 작성한다(생략 금지).**

- [ ] **Step 2: 렌더 확인**

http://127.0.0.1:8765/apply/ 접속 — 35개 강의분야 포함 전체 폼 렌더·체크박스 그리드 정렬 확인.

- [ ] **Step 3: 커밋**
```bash
git add apply/index.html
git commit -m "feat(forms): add member application form page"
```

---

## Task 8: 기존 버튼 46곳 → 신규 페이지 치환

**Files:**
- Modify: 22개 HTML 중 구글폼 링크 포함 파일 전부 (spec §5 목록)

- [ ] **Step 1: 전체 백업**

PowerShell:
```powershell
Get-ChildItem -Recurse -Filter *.html | Where-Object { (Select-String -Path $_.FullName -Pattern 'docs.google.com/forms' -Quiet) } | ForEach-Object { Copy-Item $_.FullName "$($_.FullName).bak" }
```

- [ ] **Step 2: 일반 문의 폼 링크 치환 (43곳)**

각 파일에서 일반 문의 폼 URL을 상대경로로 치환. 깊이 1 폴더(`xxx/index.html`)는 `../contact/`, 루트 `index.html`은 `contact/`.
- 치환 대상 문자열: `https://docs.google.com/forms/d/e/1FAIpQLSfCAMCU3X1yR4WoiVXRtRSzCcor4h8QpJ_5xxipweP70JUVnA/viewform`
- 폴더 페이지 → `../contact/` , 루트 `index.html` → `contact/`
- 동시에 같은 `<a>`의 ` target="_blank"`와 ` rel="noopener"`는 제거(내부 이동).

PowerShell 예(폴더 페이지 일괄):
```powershell
$old='https://docs.google.com/forms/d/e/1FAIpQLSfCAMCU3X1yR4WoiVXRtRSzCcor4h8QpJ_5xxipweP70JUVnA/viewform'
Get-ChildItem -Recurse -Filter index.html | Where-Object { $_.DirectoryName -ne (Get-Location).Path } | ForEach-Object {
  (Get-Content $_.FullName -Raw).Replace($old,'../contact/') | Set-Content $_.FullName -Encoding utf8
}
# 루트 index.html 별도
(Get-Content .\index.html -Raw).Replace($old,'contact/') | Set-Content .\index.html -Encoding utf8
```
> 주의: `target="_blank" rel="noopener"` 제거는 치환 후 Grep으로 남은 폼 링크 라인을 찾아 수동 정리하거나, 무해하므로 유지해도 됨(내부 링크에 `_blank`만 적용). **간결성 위해 v1은 href만 치환하고 `_blank`는 유지 허용** — 새 탭으로 폼 페이지가 열릴 뿐 기능 정상.

- [ ] **Step 3: 멤버 모집 폼 링크 치환 (3곳: members-edu 2, members-seongsu 1)**
```powershell
$oldm='https://docs.google.com/forms/d/e/1FAIpQLSds244fmx6FLoqpy5bdU6NPiC25lGML1DUxFgC78phtH1EOgg/viewform'
'members-edu\index.html','members-seongsu\index.html' | ForEach-Object {
  (Get-Content $_ -Raw).Replace($oldm,'../apply/') | Set-Content $_ -Encoding utf8
}
```

- [ ] **Step 4: 잔여 구글폼 링크 0건 확인**

Grep: `docs.google.com/forms` (output_mode count) → **0건**이어야 함(menu-cta 포함 전부 치환됐는지 확인). 남으면 해당 파일 수동 치환.

- [ ] **Step 5: 커밋**
```bash
git add -A
git commit -m "feat(forms): repoint 46 form buttons to /contact and /apply"
```

---

## Task 9: 통합 검증 (로컬)

- [ ] **Step 1: JS 문법**
Run: `node --check shared/js/forms.js` → 통과.

- [ ] **Step 2: jsdom 테스트 전체**
Run: `cd test; node --test` → 4 PASS.

- [ ] **Step 3: 정적 서버 육안 점검**
`py -m http.server 8765 --bind 127.0.0.1` 실행 후:
- http://127.0.0.1:8765/contact/ — 폼 렌더·필수 표시·동의 체크박스
- http://127.0.0.1:8765/apply/ — 35개 분야 체크박스·파일 input·라디오
- 임의 페이지(예: /members-edu/)에서 "인재풀 등록" 버튼이 `/apply/`로 가는지 클릭 확인
- 브라우저 콘솔 에러 0건

- [ ] **Step 4: 깨진 링크 재검사**
spec §0 자동 스캔 재실행 → 신규 페이지 포함 깨진 내부 링크 0건.

---

## Task 10: 호스팅 배포 체크리스트 (사용자 수행 안내)

> 로컬에 PHP/MySQL이 없으므로 실제 동작은 호스팅에서 확인. 아래를 README 형태로 `api/README.md`에 작성.

- [ ] **Step 1: `api/README.md` 작성**

내용:
```markdown
# 배포 체크리스트
1. MySQL DB 생성 후 `db/schema.sql` 임포트.
2. `api/config.sample.php` → `api/config.php` 복사 후 DB 접속정보·mail_to 입력.
3. 전체 파일 호스팅 업로드. `api/uploads/` 폴더 쓰기권한(0750~0755) 부여.
4. `api/uploads/.htaccess`(Deny from all) 적용 확인 — 브라우저로 업로드 파일 직접 접근 시 차단되어야 함.
5. /contact/ , /apply/ 실제 제출 테스트:
   - 정상 제출 → DB row 생성 + thewhhylab@naver.com 메일 수신 확인.
   - 멤버 폼 파일 업로드 → uploads에 저장 + 메일에 파일명 안내.
6. 메일 미수신 시: 호스팅 mail() 정책 확인 → 필요 시 api/lib/mailer.php를 PHPMailer(SMTP)로 교체.
7. (선택) `php -l api/submit.php` 등으로 서버에서 문법 확인.
```

- [ ] **Step 2: 커밋**
```bash
git add api/README.md
git commit -m "docs(forms): add deployment checklist"
```

---

## Self-Review 결과 (작성자 점검)

- **Spec 커버리지:** §2 아키텍처→T1~3, §3 데이터모델→T1, §4 폼항목→T6/T7(+forms.js 검증 T5), §5 버튼치환→T8, §6 검증→T5/T9/T10, 개인정보 동의→T6/T7, 파일업로드→T2/T3/T7. 누락 없음.
- **플레이스홀더:** 강의분야 35개 등 긴 목록은 spec §4.2를 단일 출처로 참조(중복 회피) — 구현 시 verbatim 전개 명시. 그 외 코드 전부 실 코드.
- **타입 일관성:** form 필드명(`fields[]`,`styles[]`,`client_types[]`,`privacy_agreed`,`form_type`,`form_ts`,`company_website`)이 submit.php·forms.js·HTML 전반에서 동일. `initForm` 명칭 일관.
- **주의(실행자):** git 미초기화 시 커밋 단계는 건너뛰고 `.bak` 백업으로 안전성 확보.
```
