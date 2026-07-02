<?php
/**
 * 더와이랩 관리자 — 교육문의 / 강사지원 조회·관리.
 * 비번 로그인 · 핵심컬럼 목록 + 행클릭 상세 · 상태 저장 · 메모 히스토리(시간기록·누적·삭제) ·
 * 미확인 강조 · 검색/필터 · 선택/전체 다운로드(CSV) · 첨부 단건/ZIP · 디스크 사용량 게이지 · 선택 삭제(행+첨부+메모) · 모바일.
 * 스키마(상태컬럼·메모테이블) 자동생성. 접속: /admin 또는 /api/admin.php (config.php의 admin_pass)
 */
if (isset($_GET['debug'])) { ini_set('display_errors', '1'); error_reporting(E_ALL); }
session_start();

$base = __DIR__;
$cfgFile = $base . '/config.php';
if (!is_file($cfgFile)) { http_response_code(500); exit('config.php 가 필요합니다.'); }
$cfg = require $cfgFile;
$adminUser = (string)($cfg['admin_user'] ?? '');
$adminPass = (string)($cfg['admin_pass'] ?? '');
$self = strtok($_SERVER['REQUEST_URI'] ?? 'admin.php', '?'); if ($self === '') $self = 'admin.php';

if (isset($_GET['logout'])) { $_SESSION = []; session_destroy(); header('Location: ' . $self); exit; }

/* ---------- 로그인 보안: 로그인 시도 잠금 (IP별, 파일 기반) ---------- */
$LOGIN_MAX = 5;        // 허용 실패 횟수
$LOGIN_LOCK_MIN = 10;  // 초과 시 잠금 시간(분)
$loginLockFile = sys_get_temp_dir() . '/twl_admin_login.json';
$loginKey = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'x');

function login_attempts_load($file) {
  if (!is_file($file)) return [];
  $j = json_decode((string)@file_get_contents($file), true);
  return is_array($j) ? $j : [];
}
function login_attempts_save($file, $data) {
  @file_put_contents($file, json_encode($data), LOCK_EX);
}
/* 반환: [잠김여부(bool), 남은분(int), 누적실패(int)] */
function login_lock_state($file, $key) {
  $d = login_attempts_load($file);
  $e = isset($d[$key]) ? $d[$key] : null;
  if (!$e) return [false, 0, 0];
  $until = isset($e['until']) ? (int)$e['until'] : 0;
  if ($until > time()) return [true, (int)ceil(($until - time()) / 60), (int)($e['fails'] ?? 0)];
  return [false, 0, (int)($e['fails'] ?? 0)];
}
function login_lock_fail($file, $key, $max, $lockMin) {
  $d = login_attempts_load($file);
  $e = isset($d[$key]) ? $d[$key] : ['fails' => 0, 'until' => 0];
  $until = isset($e['until']) ? (int)$e['until'] : 0;
  if ($until > 0 && $until <= time()) $e = ['fails' => 0, 'until' => 0]; // 잠금 만료 → 카운터 리셋
  $e['fails'] = (int)($e['fails'] ?? 0) + 1;
  if ($e['fails'] >= $max) $e['until'] = time() + $lockMin * 60;
  $d[$key] = $e;
  login_attempts_save($file, $d);
  return (int)$e['fails'];
}
function login_lock_clear($file, $key) {
  $d = login_attempts_load($file);
  if (isset($d[$key])) { unset($d[$key]); login_attempts_save($file, $d); }
}

list($loginLocked, $loginLockLeft, $loginFails) = login_lock_state($loginLockFile, $loginKey);

$err = '';
if (isset($_POST['pw'])) {
  if ($loginLocked) {
    $err = "시도가 많아 잠겼습니다. 약 {$loginLockLeft}분 후 다시 시도하세요.";
    usleep(600000);
  } elseif ($adminUser !== '' && $adminPass !== ''
            && hash_equals($adminUser, (string)($_POST['user'] ?? ''))
            && hash_equals($adminPass, (string)$_POST['pw'])) {
    login_lock_clear($loginLockFile, $loginKey);
    session_regenerate_id(true);
    $_SESSION['twl_admin'] = true;
    $_SESSION['twl_last'] = time();
    header('Location: ' . $self); exit;
  } else {
    $fails = login_lock_fail($loginLockFile, $loginKey, $LOGIN_MAX, $LOGIN_LOCK_MIN);
    $left = $LOGIN_MAX - $fails;
    $err = $left > 0
      ? "아이디 또는 비밀번호가 올바르지 않습니다. (남은 시도 {$left}회)"
      : "시도 횟수를 초과하여 {$LOGIN_LOCK_MIN}분간 잠겼습니다.";
    usleep(600000);
    list($loginLocked, $loginLockLeft, $loginFails) = login_lock_state($loginLockFile, $loginKey);
  }
}

/* ---------- 세션 비활성 타임아웃 (자동 로그아웃) ---------- */
$SESSION_TIMEOUT = (int)($cfg['admin_session_timeout'] ?? 1800); // 기본 30분
$sessionTimedOut = false;
if (!empty($_SESSION['twl_admin'])) {
  if (isset($_SESSION['twl_last']) && (time() - (int)$_SESSION['twl_last']) > $SESSION_TIMEOUT) {
    $_SESSION = []; $sessionTimedOut = true; // 만료 → 로그아웃 처리
  } else {
    $_SESSION['twl_last'] = time();
  }
}
$authed = !empty($_SESSION['twl_admin']);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ---------- 로그인 화면 ---------- */
if (!$authed) {
  header('Content-Type: text/html; charset=UTF-8');
  ?><!doctype html><html lang="ko"><head><meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
  <title>더와이랩 관리자</title>
  <style>
    *{box-sizing:border-box}
    body{font-family:system-ui,'Pretendard',-apple-system,sans-serif;background:linear-gradient(160deg,#10121c,#191c2e);color:#e7e9f3;display:grid;place-items:center;min-height:100vh;margin:0}
    .box{background:rgba(255,255,255,.04);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,.08);padding:34px 30px;border-radius:18px;width:min(92vw,350px);box-shadow:0 20px 60px rgba(0,0,0,.45)}
    .logo-img{display:block;width:100%;max-width:200px;height:auto;margin:0 0 18px}
    h1{font-size:18px;margin:0 0 4px;letter-spacing:-.02em}p.sub{margin:0 0 20px;color:#9aa0bf;font-size:13px}
    input{width:100%;padding:13px 14px;border-radius:11px;border:1px solid rgba(255,255,255,.12);background:rgba(0,0,0,.25);color:#fff;font-size:15px;outline:none}
    input:focus{border-color:#6366f1}
    input + input{margin-top:10px}
    button{width:100%;margin-top:12px;padding:13px;border:0;border-radius:11px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;font-size:15px;font-weight:600;cursor:pointer}
    button:disabled{opacity:.5;cursor:not-allowed}
    .err{color:#ff9a9a;font-size:13px;margin-top:11px;min-height:16px}
  </style></head><body>
  <form class="box" method="post" autocomplete="off">
    <img class="logo-img" src="/shared/img/logo-blue.png" alt="더와이랩 THE WHHY LAB">
    <h1>관리자 로그인</h1><p class="sub">접수내역 조회·관리</p>
    <input type="text" name="user" placeholder="관리자 아이디" autocomplete="username" autofocus required>
    <input type="password" name="pw" placeholder="관리자 비밀번호" autocomplete="current-password" required>
    <button type="submit"<?= $loginLocked ? ' disabled' : '' ?>>로그인</button>
    <div class="err"><?php
      if ($adminUser === '' || $adminPass === '') echo 'config.php에 admin_user / admin_pass 를 설정하세요.';
      elseif ($loginLocked) echo h("로그인이 잠겼습니다. 약 {$loginLockLeft}분 후 다시 시도하세요.");
      elseif ($sessionTimedOut) echo '세션이 만료되어 로그아웃되었습니다. 다시 로그인해주세요.';
      else echo h($err);
    ?></div>
  </form></body></html><?php
  exit;
}

/* ---------- DB 연결 ---------- */
require $base . '/lib/db.php';
require $base . '/lib/schema.php';
try { $pdo = db_connect($cfg['db']); }
catch (Throwable $e) { http_response_code(500); exit('DB 접속 오류: ' . h($e->getMessage())); }

$uploadDir = $cfg['upload_dir'] !== '' ? rtrim($cfg['upload_dir'], '/\\') : ($base . '/uploads');

/* 스키마 자동 생성: 상태 컬럼 + 메모 히스토리 테이블 */
function ensure_schema($pdo) {
  $cols = [
    'inquiries' => "VARCHAR(20) NOT NULL DEFAULT '신규'",
    'member_applications' => "VARCHAR(20) NOT NULL DEFAULT '신규'",
  ];
  foreach ($cols as $tbl => $type) {
    try { $have = $pdo->query("SHOW COLUMNS FROM `{$tbl}`")->fetchAll(PDO::FETCH_COLUMN); }
    catch (Throwable $e) { continue; }
    if (!in_array('status', $have, true)) {
      try { $pdo->exec("ALTER TABLE `{$tbl}` ADD COLUMN `status` {$type}"); } catch (Throwable $e) {}
    }
  }
  try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_memos (
      id INT AUTO_INCREMENT PRIMARY KEY,
      ref_type VARCHAR(24) NOT NULL,
      ref_id INT NOT NULL,
      body TEXT NOT NULL,
      created_at DATETIME NOT NULL,
      KEY idx_ref (ref_type, ref_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  } catch (Throwable $e) {}
}
ensure_schema($pdo);
ensure_event_columns($pdo); // inquiries에 AX 이벤트(kind='event') 컬럼 보장

$STATUS = [
  'inquiries' => ['신규', '진행중', '답변완료', '보류'],
  'member_applications' => ['신규', '검토중', '완료', '보류'],
];
function status_class($s) {
  if (in_array($s, ['답변완료', '완료', '검토완료'], true)) return 'done';
  if (in_array($s, ['진행중', '검토중'], true)) return 'prog';
  if ($s === '보류') return 'hold';
  return 'new';
}
function tbl_of($t) { return $t === 'app' ? 'member_applications' : 'inquiries'; }
function safe_upload_path($dir, $stored) {
  if (!$stored) return null;
  $path = $dir . '/' . basename($stored);
  $rd = realpath($dir); $rp = realpath($path);
  if ($rp === false || $rd === false || strpos($rp, $rd) !== 0 || !is_file($rp)) return null;
  return $rp;
}

/* 폴더 용량 합산(재귀) / 바이트 표기 — 디스크 게이지용 */
function dir_size($dir) {
  if (!is_dir($dir)) return 0;
  $total = 0;
  try {
    $it = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
      RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($it as $f) { $total += $f->getSize(); }
  } catch (Throwable $e) {}
  return $total;
}
function fmt_bytes($b) {
  if ($b >= 1073741824) return number_format($b / 1073741824, 2) . ' GB';
  if ($b >= 1048576)    return number_format($b / 1048576, 1) . ' MB';
  if ($b >= 1024)       return number_format($b / 1024, 0) . ' KB';
  return $b . ' B';
}

/* ---------- 상태 저장 (AJAX) ---------- */
if (($_POST['action'] ?? '') === 'status_save') {
  header('Content-Type: application/json; charset=UTF-8');
  $tbl = tbl_of($_POST['table'] ?? ''); $id = (int)($_POST['id'] ?? 0); $status = (string)($_POST['status'] ?? '');
  if ($id <= 0 || !in_array($status, $STATUS[$tbl], true)) { echo json_encode(['ok' => false, 'message' => '잘못된 값']); exit; }
  try { $st = $pdo->prepare("UPDATE {$tbl} SET status = ? WHERE id = ?"); $st->execute([$status, $id]);
    echo json_encode(['ok' => true, 'cls' => status_class($status)]);
  } catch (Throwable $e) { echo json_encode(['ok' => false, 'message' => $e->getMessage()]); }
  exit;
}

/* ---------- 메모 추가 (AJAX) ---------- */
if (($_POST['action'] ?? '') === 'memo_add') {
  header('Content-Type: application/json; charset=UTF-8');
  $tbl = tbl_of($_POST['table'] ?? ''); $id = (int)($_POST['id'] ?? 0); $body = trim((string)($_POST['body'] ?? ''));
  if ($id <= 0 || $body === '') { echo json_encode(['ok' => false, 'message' => '내용이 비어 있습니다.']); exit; }
  $now = date('Y-m-d H:i:s');
  try {
    $st = $pdo->prepare("INSERT INTO admin_memos (ref_type, ref_id, body, created_at) VALUES (?,?,?,?)");
    $st->execute([$tbl, $id, $body, $now]);
    echo json_encode(['ok' => true, 'memo' => ['id' => (int)$pdo->lastInsertId(), 'body' => $body, 'created_at' => $now]]);
  } catch (Throwable $e) { echo json_encode(['ok' => false, 'message' => $e->getMessage()]); }
  exit;
}

/* ---------- 메모 삭제 (AJAX) ---------- */
if (($_POST['action'] ?? '') === 'memo_del') {
  header('Content-Type: application/json; charset=UTF-8');
  $mid = (int)($_POST['memoId'] ?? 0);
  if ($mid <= 0) { echo json_encode(['ok' => false]); exit; }
  try { $pdo->prepare("DELETE FROM admin_memos WHERE id = ?")->execute([$mid]); echo json_encode(['ok' => true]); }
  catch (Throwable $e) { echo json_encode(['ok' => false, 'message' => $e->getMessage()]); }
  exit;
}

/* 메모 모음 헬퍼: 주어진 ref_type+ids 의 메모를 ref_id별로 그룹화 */
function memos_for($pdo, $refType, $ids) {
  if (!$ids) return [];
  $in = implode(',', array_fill(0, count($ids), '?'));
  $st = $pdo->prepare("SELECT * FROM admin_memos WHERE ref_type = ? AND ref_id IN ($in) ORDER BY id ASC");
  $st->execute(array_merge([$refType], $ids));
  $out = [];
  foreach ($st->fetchAll() as $m) { $out[$m['ref_id']][] = $m; }
  return $out;
}

/* CSV 컬럼 (관리메모는 누적메모를 합쳐서) */
$COLS = [
  'inquiries' => ['ID'=>'id','구분'=>'kind','상태'=>'status','접수일시'=>'created_at','이름'=>'name','연락처'=>'phone','이메일'=>'email','소속'=>'affiliation','교육주제'=>'topic','교육목적'=>'purpose','교육대상'=>'audience','교육장소'=>'location','문의내용/RFP'=>'message','회사명'=>'company','부서'=>'dept','직책'=>'job_title','담당업무'=>'role_work','조직규모'=>'org_size','관심분야'=>'interests','AX사연'=>'story','상담희망'=>'consult_wanted','마케팅동의'=>'marketing_agreed','명함원본'=>'card_original','관리메모'=>'__memos__','IP'=>'ip'],
  'member_applications' => ['ID'=>'id','상태'=>'status','접수일시'=>'created_at','성함'=>'name','연락처'=>'phone','성별'=>'gender','출생년도'=>'birth_year','거주지역'=>'region','경력기간'=>'career_years','주요경력'=>'prev_career','강의분야'=>'fields','강의스타일'=>'styles','소개'=>'intro','출강기관'=>'client_types','최소섭외비'=>'min_fee','신청경로'=>'referral','첨부원본파일명'=>'profile_original','관리메모'=>'__memos__','IP'=>'ip'],
];

/* ---------- 단건 첨부 다운로드 ---------- */
if (isset($_GET['dl'])) {
  $id = (int)$_GET['dl'];
  $st = $pdo->prepare('SELECT profile_file, profile_original FROM member_applications WHERE id = ?');
  $st->execute([$id]); $r = $st->fetch();
  $path = $r ? safe_upload_path($uploadDir, $r['profile_file']) : null;
  if (!$path) { http_response_code(404); exit('파일을 찾을 수 없습니다.'); }
  $fn = $r['profile_original'] ?: basename($path);
  $fnAscii = preg_replace('/[\r\n"]/', '', $fn);
  header('Content-Type: application/octet-stream');
  header('Content-Length: ' . filesize($path));
  header("Content-Disposition: attachment; filename=\"{$fnAscii}\"; filename*=UTF-8''" . rawurlencode($fn));
  header('X-Content-Type-Options: nosniff');
  readfile($path); exit;
}

/* ---------- 명함 다운로드 (AX 이벤트, inquiries.card_file) ---------- */
if (isset($_GET['cdl'])) {
  $id = (int)$_GET['cdl'];
  $st = $pdo->prepare('SELECT card_file, card_original FROM inquiries WHERE id = ?');
  $st->execute([$id]); $r = $st->fetch();
  $path = $r ? safe_upload_path($uploadDir, $r['card_file']) : null;
  if (!$path) { http_response_code(404); exit('파일을 찾을 수 없습니다.'); }
  $fn = $r['card_original'] ?: basename($path);
  $fnAscii = preg_replace('/[\r\n"]/', '', $fn);
  header('Content-Type: application/octet-stream');
  header('Content-Length: ' . filesize($path));
  header("Content-Disposition: attachment; filename=\"{$fnAscii}\"; filename*=UTF-8''" . rawurlencode($fn));
  header('X-Content-Type-Options: nosniff');
  readfile($path); exit;
}

/* ---------- 일괄 CSV / ZIP ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['export'])) {
  $which = tbl_of($_POST['tab'] ?? '');
  $mode = $_POST['export'];
  $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])), function ($v) { return $v > 0; }));
  $stamp = date('Ymd_His');

  if ($mode === 'csv_all') { $rowsX = $pdo->query("SELECT * FROM {$which} ORDER BY id DESC")->fetchAll(); }
  else {
    if (!$ids) { http_response_code(400); exit('선택된 항목이 없습니다.'); }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT * FROM {$which} WHERE id IN ($in) ORDER BY id DESC");
    $st->execute($ids); $rowsX = $st->fetchAll();
  }

  if ($mode === 'csv_all' || $mode === 'csv_sel') {
    $memoMap = memos_for($pdo, $which, array_column($rowsX, 'id'));
    $label = $which === 'member_applications' ? '강사지원' : '교육문의';
    $fname = "더와이랩_{$label}_{$stamp}.csv";
    header('Content-Type: text/csv; charset=UTF-8');
    header("Content-Disposition: attachment; filename=\"export.csv\"; filename*=UTF-8''" . rawurlencode($fname));
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    $map = $COLS[$which];
    fputcsv($out, array_keys($map));
    foreach ($rowsX as $r) {
      $line = [];
      foreach ($map as $col) {
        if ($col === '__memos__') {
          $parts = [];
          foreach ($memoMap[$r['id']] ?? [] as $m) { $parts[] = '[' . substr($m['created_at'], 0, 16) . '] ' . $m['body']; }
          $line[] = implode("\n", $parts);
        } else { $line[] = $r[$col] ?? ''; }
      }
      fputcsv($out, $line);
    }
    fclose($out); exit;
  }

  if ($mode === 'zip_sel') {
    if ($which !== 'member_applications') { http_response_code(400); exit('첨부는 강사지원만 가능합니다.'); }
    if (!class_exists('ZipArchive')) { http_response_code(500); exit('서버에 ZipArchive 모듈이 없어 ZIP을 만들 수 없습니다. 첨부는 단건으로 받아주세요.'); }
    $tmp = tempnam(sys_get_temp_dir(), 'twlzip');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) { http_response_code(500); exit('ZIP 생성 실패.'); }
    $added = 0; $seq = 0;
    foreach ($rowsX as $r) {
      $path = safe_upload_path($uploadDir, $r['profile_file']); if (!$path) continue;
      $orig = $r['profile_original'] ?: basename($path);
      $entry = $r['id'] . '_' . (++$seq) . '_' . preg_replace('/[\/\\\\:*?"<>|]/', '_', $orig);
      $zip->addFile($path, $entry); $added++;
    }
    $zip->close();
    if ($added === 0) { @unlink($tmp); http_response_code(404); exit('선택한 항목에 첨부파일이 없습니다.'); }
    $fname = "더와이랩_강사지원_첨부_{$stamp}.zip";
    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize($tmp));
    header("Content-Disposition: attachment; filename=\"attachments.zip\"; filename*=UTF-8''" . rawurlencode($fname));
    readfile($tmp); @unlink($tmp); exit;
  }
}

/* ---------- 선택 삭제 (행 + 첨부파일 + 메모) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['bulk_delete'])) {
  $which = tbl_of($_POST['tab'] ?? '');
  $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])), function ($v) { return $v > 0; }));
  if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    if ($which === 'member_applications') {
      $st = $pdo->prepare("SELECT profile_file FROM member_applications WHERE id IN ($in)");
      $st->execute($ids);
      foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $pf) {
        $p = safe_upload_path($uploadDir, $pf);
        if ($p) @unlink($p);
      }
    } else { // inquiries — AX 이벤트 명함 파일 정리
      try {
        $st = $pdo->prepare("SELECT card_file FROM inquiries WHERE id IN ($in)");
        $st->execute($ids);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $pf) {
          $p = safe_upload_path($uploadDir, $pf);
          if ($p) @unlink($p);
        }
      } catch (Throwable $e) {}
    }
    try { $pdo->prepare("DELETE FROM admin_memos WHERE ref_type = ? AND ref_id IN ($in)")->execute(array_merge([$which], $ids)); } catch (Throwable $e) {}
    $pdo->prepare("DELETE FROM {$which} WHERE id IN ($in)")->execute($ids);
  }
  $tabParam = ($_POST['tab'] ?? '') === 'app' ? 'app' : 'inq';
  header('Location: ' . $self . '?tab=' . $tabParam);
  exit;
}

/* ---------- 목록 조회 ---------- */
$inq = $pdo->query('SELECT * FROM inquiries ORDER BY id DESC LIMIT 300')->fetchAll();
$app = $pdo->query('SELECT * FROM member_applications ORDER BY id DESC LIMIT 300')->fetchAll();
$tab = ($_GET['tab'] ?? 'inq') === 'app' ? 'app' : 'inq';
$rows = $tab === 'app' ? $app : $inq;
$isApp = $tab === 'app';
$tblKey = tbl_of($tab);
$statusOpts = $STATUS[$tblKey];
$memosByRow = memos_for($pdo, $tblKey, array_column($rows, 'id'));
$newOf = function ($list) { return count(array_filter($list, function ($r) { return ($r['status'] ?: '신규') === '신규'; })); };
$newInq = $newOf($inq); $newApp = $newOf($app); $newCount = $isApp ? $newApp : $newInq;

/* 디스크 사용량 (문서 루트 합산 ÷ 10GB 제한) — 10GB는 아래 상수만 바꾸면 됨 */
$DISK_LIMIT = 10 * 1073741824; // 10 GB
$diskUsed = dir_size(dirname($base));
$uploadsUsed = dir_size($uploadDir);
$diskPct = $DISK_LIMIT > 0 ? min(100, $diskUsed / $DISK_LIMIT * 100) : 0;
$diskLevel = $diskPct >= 90 ? 'crit' : ($diskPct >= 70 ? 'warn' : 'ok');

function fld($label, $val) { $val = ($val === '' || $val === null) ? '-' : $val; return '<div class="d"><dt>' . h($label) . '</dt><dd>' . h($val) . '</dd></div>'; }
function lng($label, $val) { $val = ($val === '' || $val === null) ? '-' : $val; return '<div class="d long"><dt>' . h($label) . '</dt><dd>' . h($val) . '</dd></div>'; }

$ICON_DL = '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14"/></svg>';
$ICON_MEMO = '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';

header('Content-Type: text/html; charset=UTF-8');
?><!doctype html><html lang="ko"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>더와이랩 접수내역</title>
<style>
  :root{--bg:#f6f7f9;--card:#fff;--bd:#e9ebf0;--bd2:#f0f1f5;--tx:#1c2230;--mut:#8b91a3;--mut2:#aeb3c2;--pri:#4f46e5;--pri-soft:#eef0fe}
  *{box-sizing:border-box}
  html,body{max-width:100%;overflow-x:hidden}
  body{font-family:system-ui,'Pretendard',-apple-system,sans-serif;margin:0;background:var(--bg);color:var(--tx);-webkit-font-smoothing:antialiased;letter-spacing:-.01em}
  .ic{width:13px;height:13px;display:inline-block;vertical-align:-2px;flex:0 0 auto}
  header{background:rgba(255,255,255,.85);backdrop-filter:blur(10px);border-bottom:1px solid var(--bd);padding:13px 22px;display:flex;align-items:center;gap:14px;position:sticky;top:0;z-index:20;flex-wrap:wrap}
  .brand{display:flex;align-items:center;gap:9px;font-weight:700;font-size:15px;white-space:nowrap}
  .brand .lg{width:26px;height:26px;border-radius:8px;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:grid;place-items:center;color:#fff;font-size:13px;font-weight:800}
  .seg{display:flex;gap:3px;background:#eef0f4;padding:3px;border-radius:11px}
  .seg a{padding:7px 14px;border-radius:8px;text-decoration:none;color:#5b6172;font-size:13.5px;font-weight:600;display:flex;align-items:center;gap:6px;transition:.15s}
  .seg a.on{background:#fff;color:var(--tx);box-shadow:0 1px 3px rgba(16,24,40,.08)}
  .seg .nd{min-width:17px;height:17px;padding:0 4px;border-radius:9px;background:#f43f5e;color:#fff;font-size:11px;font-weight:700;display:grid;place-items:center}
  .sp{flex:1}
  .out{color:var(--mut);text-decoration:none;font-size:13px;padding:7px 13px;border-radius:9px;border:1px solid var(--bd);transition:.15s}.out:hover{background:#fff;color:var(--tx)}
  .diskbar{margin:14px 22px 0;padding:13px 16px;background:var(--card);border:1px solid var(--bd);border-radius:13px}
  .disk-row{display:flex;align-items:center;gap:10px;font-size:13px;margin-bottom:9px;flex-wrap:wrap}
  .disk-label{font-weight:700;color:var(--tx)}
  .disk-num{color:var(--mut)}.disk-num b{color:var(--tx);font-variant-numeric:tabular-nums}
  .disk-pct{font-weight:700;font-variant-numeric:tabular-nums}
  .disk-up{margin-left:auto;color:var(--mut);font-size:12.5px}
  .disk-track{height:8px;border-radius:6px;background:#eef0f4;overflow:hidden}
  .disk-fill{height:100%;border-radius:6px;transition:width .3s}
  .disk-ok{color:#047857}.disk-fill.disk-ok{background:#10b981}
  .disk-warn{color:#c2410c}.disk-fill.disk-warn{background:#fb923c}
  .disk-crit{color:#be123c}.disk-fill.disk-crit{background:#f43f5e}
  .alert{display:flex;align-items:center;gap:10px;margin:14px 22px 0;padding:13px 16px;background:linear-gradient(90deg,#fff5f6,#fff);border:1px solid #ffdfe3;border-radius:13px;color:#be123c;font-size:13.5px;font-weight:600}
  .alert .dot{width:8px;height:8px;border-radius:50%;background:#f43f5e;box-shadow:0 0 0 4px #fde2e5}
  .alert button{margin-left:auto;border:0;background:#f43f5e;color:#fff;border-radius:9px;padding:7px 14px;font-size:13px;font-weight:600;cursor:pointer}
  .bar{display:flex;align-items:center;gap:10px;padding:16px 22px 0;flex-wrap:wrap}
  .chips{display:flex;gap:6px;flex-wrap:wrap}
  .chips button{border:1px solid var(--bd);background:#fff;border-radius:9px;padding:7px 13px;font-size:13px;cursor:pointer;color:#5b6172;font-weight:500;transition:.15s}
  .chips button:hover{border-color:#d3d6e0}.chips button.on{background:var(--tx);color:#fff;border-color:var(--tx)}
  .search{flex:1;min-width:170px;max-width:340px;padding:9px 13px 9px 36px;border:1px solid var(--bd);border-radius:10px;font-size:13.5px;background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='15' height='15' fill='none' stroke='%238b91a3' stroke-width='2' viewBox='0 0 24 24'%3E%3Ccircle cx='11' cy='11' r='7'/%3E%3Cpath d='m21 21-4.3-4.3'/%3E%3C/svg%3E") no-repeat 12px center;outline:none}
  .search:focus{border-color:var(--pri)}
  .actions{display:flex;align-items:center;gap:9px;flex-wrap:wrap;padding:14px 22px 0}
  .meta-sel{font-size:12.5px;color:var(--mut)}
  .btn{border:0;border-radius:10px;padding:9px 15px;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:.15s}
  .btn-ghost{background:#fff;color:#4b5163;border:1px solid var(--bd)}.btn-ghost:hover{border-color:#cfd3de;background:#fafbfc}
  .btn-pri{background:var(--pri);color:#fff}.btn-pri:hover{background:#4338ca}
  .btn-danger{background:#fff;color:#be123c;border:1px solid #f3c2c8}.btn-danger:hover{background:#fff1f2;border-color:#f43f5e}
  .wrap{padding:14px 22px 36px}
  .tcard{background:var(--card);border:1px solid var(--bd);border-radius:15px;overflow:hidden;box-shadow:0 1px 2px rgba(16,24,40,.03)}
  table{border-collapse:collapse;width:100%;font-size:13.5px;table-layout:fixed}
  thead th{background:#fbfbfd;font-weight:600;color:#6b7180;font-size:12px;padding:11px 13px;text-align:left;white-space:nowrap;border-bottom:1px solid var(--bd)}
  tbody td{padding:12px 13px;border-bottom:1px solid var(--bd2);text-align:left;vertical-align:middle}
  tbody tr:last-child td{border-bottom:0}
  td.tx{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  col.chk{width:42px}col.cid{width:58px}col.cst{width:116px}col.cdt{width:128px}col.catt{width:84px}
  td.chk{text-align:center}td.cid{color:var(--mut2);font-variant-numeric:tabular-nums}td.cdt{color:var(--mut);font-size:12.5px;font-variant-numeric:tabular-nums}
  tr.main{cursor:pointer;transition:background .12s}tr.main:hover>td{background:#fafbff}tr.main.open>td{background:var(--pri-soft)}
  tr.main.isnew>td.cid{box-shadow:inset 3px 0 0 #f43f5e}
  input[type=checkbox]{width:16px;height:16px;cursor:pointer;accent-color:var(--pri)}
  .caret{display:inline-block;width:0;height:0;margin-right:8px;vertical-align:middle;border-left:5px solid var(--mut2);border-top:4px solid transparent;border-bottom:4px solid transparent;transition:transform .15s}
  .pill{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;white-space:nowrap}
  .pill i{width:6px;height:6px;border-radius:50%}
  .p-new{background:#fff1f2;color:#be123c}.p-new i{background:#f43f5e}
  .p-prog{background:#fff7ed;color:#c2410c}.p-prog i{background:#fb923c}
  .p-done{background:#ecfdf5;color:#047857}.p-done i{background:#10b981}
  .p-hold{background:#f1f5f9;color:#64748b}.p-hold i{background:#94a3b8}
  .p-event{background:#f3e8ff;color:#7c3aed;margin-left:5px}
  tr.main.evrow>td.cid{box-shadow:inset 3px 0 0 #a855f7}
  .chips button.chip-event{color:#7c3aed}.chips button.chip-event.on{background:#7c3aed;color:#fff;border-color:#7c3aed}
  .memoicon{margin-left:6px;color:var(--mut2);vertical-align:middle}
  .filelink{display:inline-flex;align-items:center;gap:5px;background:#f0fdf4;color:#15803d;text-decoration:none;padding:5px 10px;border-radius:8px;font-size:12px;font-weight:600;border:1px solid #c7f0d4;white-space:normal;word-break:break-all}
  .filelink:hover{background:#dcfce7}
  td.catt{overflow:visible}
  .none{color:var(--mut2);font-size:12.5px}
  .empty{padding:56px;text-align:center;color:var(--mut)}
  tr.detail>td{padding:0;background:#fbfcfe;border-bottom:1px solid var(--bd)}
  tr.detail .panel{display:none;padding:20px 22px 22px}
  tr.detail.show .panel{display:block;animation:fade .2s ease}
  @keyframes fade{from{opacity:0;transform:translateY(-3px)}to{opacity:1;transform:none}}
  .sec{margin-bottom:18px}
  .sec-t{font-size:11.5px;font-weight:700;color:var(--mut);letter-spacing:.04em;margin:0 0 10px;display:flex;align-items:center;gap:8px}
  .sec-t::after{content:"";flex:1;height:1px;background:var(--bd)}
  .dl{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:13px 26px}
  .d{display:flex;flex-direction:column;gap:3px;min-width:0}.d.long{grid-column:1/-1}
  .d dt{font-size:11.5px;color:var(--mut);font-weight:600}
  .d dd{margin:0;font-size:13.5px;color:var(--tx);word-break:break-word;white-space:pre-wrap;line-height:1.55}
  .metaline{font-size:12px;color:var(--mut2);margin-top:2px;font-variant-numeric:tabular-nums}
  .statusbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:8px;padding:13px 14px;background:#fff;border:1px solid var(--bd);border-radius:13px}
  .statusbar label{font-size:12px;color:var(--mut);font-weight:600}
  .statusbar select{border:1px solid var(--bd);border-radius:9px;padding:8px 11px;font-size:13px;font-family:inherit;background:#fff;outline:none;cursor:pointer}
  .statusbar select:focus{border-color:var(--pri)}
  .statusbar .save{background:var(--pri);color:#fff;border:0;border-radius:9px;padding:9px 16px;font-weight:600;cursor:pointer}.statusbar .save:hover{background:#4338ca}
  .statusbar .ok{font-size:12.5px;font-weight:600}
  .memos{margin-top:12px;padding:14px;background:#fff;border:1px solid var(--bd);border-radius:13px}
  .memos .sec-t{margin-bottom:12px}
  .memo-list{display:flex;flex-direction:column;gap:8px;margin-bottom:12px}
  .memo-empty{color:var(--mut2);font-size:12.5px;padding:2px}
  .memo-item{padding:10px 12px;background:#fbfcfe;border:1px solid var(--bd);border-radius:11px}
  .memo-head{display:flex;align-items:center;gap:8px;margin-bottom:4px}
  .memo-time{color:var(--mut);font-size:11.5px;font-variant-numeric:tabular-nums}
  .memo-del{margin-left:auto;border:0;background:transparent;color:var(--mut2);font-size:12px;cursor:pointer;padding:3px 8px;border-radius:7px}
  .memo-del:hover{background:#fee2e2;color:#be123c}
  .memo-body{font-size:13px;white-space:pre-wrap;word-break:break-word;line-height:1.55}
  .memo-add{display:flex;gap:8px;align-items:flex-start}
  .memo-input{flex:1;min-height:38px;border:1px solid var(--bd);border-radius:9px;padding:9px 11px;font-size:13px;font-family:inherit;resize:vertical;outline:none}
  .memo-input:focus{border-color:var(--pri)}
  .memo-addbtn{background:var(--tx);color:#fff;border:0;border-radius:9px;padding:9px 16px;font-weight:600;cursor:pointer;white-space:nowrap}
  @media(max-width:760px){
    header{padding:11px 15px}.wrap{padding:12px 14px 30px}.bar,.actions{padding-left:15px;padding-right:15px}.alert{margin-left:15px;margin-right:15px}.diskbar{margin-left:15px;margin-right:15px}
    col.cdt{width:0}.cdt{display:none}.hide-sm{display:none}.dl{grid-template-columns:1fr}.flbl{display:none}
    col.catt{width:46px}td.catt{overflow:hidden}
  }
</style></head><body>
<header>
  <div class="brand"><span class="lg">W</span>더와이랩 접수관리</div>
  <nav class="seg">
    <a href="?tab=inq" class="<?= !$isApp?'on':'' ?>">교육문의<?php if($newInq):?><span class="nd"><?= $newInq ?></span><?php endif;?></a>
    <a href="?tab=app" class="<?= $isApp?'on':'' ?>">강사지원<?php if($newApp):?><span class="nd"><?= $newApp ?></span><?php endif;?></a>
  </nav>
  <span class="sp"></span>
  <a class="out" href="?logout=1">로그아웃</a>
</header>

<div class="diskbar">
  <div class="disk-row">
    <span class="disk-label">웹 용량</span>
    <span class="disk-num"><b><?= fmt_bytes($diskUsed) ?></b> / 10 GB</span>
    <span class="disk-pct disk-<?= $diskLevel ?>"><?= round($diskPct, 1) ?>%</span>
    <span class="disk-up">첨부파일 <?= fmt_bytes($uploadsUsed) ?></span>
  </div>
  <div class="disk-track"><div class="disk-fill disk-<?= $diskLevel ?>" style="width:<?= round($diskPct, 1) ?>%"></div></div>
</div>

<?php if ($newCount > 0): ?>
<div class="alert"><span class="dot"></span>확인하지 않은 신규 <b><?= $newCount ?></b>건이 있습니다.
  <button type="button" id="onlyNew">신규만 보기</button>
</div>
<?php endif; ?>

<div class="bar">
  <div class="chips" id="filters">
    <button data-f="all" class="on">전체</button>
    <?php foreach ($statusOpts as $s): ?><button data-f="<?= h($s) ?>"><?= h($s) ?></button><?php endforeach; ?>
    <?php if (!$isApp): ?><button data-kind="event" class="chip-event">AX이벤트</button><?php endif; ?>
  </div>
  <input class="search" id="search" type="search" placeholder="이름·소속·연락처·내용 검색">
</div>

<form method="post" id="bulk">
<input type="hidden" name="tab" value="<?= $tab ?>">
<div class="actions">
  <span class="meta-sel"><b id="selcount">0</b>개 선택 · <b id="viewcount"><?= count($rows) ?></b>건 표시</span>
  <button class="btn btn-ghost" type="submit" name="export" value="csv_sel">선택 항목 다운로드</button>
  <?php if ($isApp): ?><button class="btn btn-ghost" type="submit" name="export" value="zip_sel">선택 첨부 다운로드</button><?php endif; ?>
  <button class="btn btn-danger" type="submit" name="bulk_delete" value="1">선택 삭제</button>
  <span class="sp"></span>
  <button class="btn btn-pri" type="submit" name="export" value="csv_all">전체 다운로드</button>
</div>

<div class="wrap">
<?php if (!$rows): ?>
  <div class="tcard"><div class="empty">접수된 <?= $isApp?'강사지원':'교육문의' ?>이(가) 없습니다.</div></div>
<?php else: $colspan = 8; ?>
  <div class="tcard"><table>
    <colgroup><col class="chk"><col class="cid"><col class="cst"><col class="cdt"><col><col>
      <?php if(!$isApp): ?><col><col><?php else: ?><col class="hide-sm-col"><col class="catt"><?php endif; ?></colgroup>
    <thead><tr>
      <th class="chk"><input type="checkbox" id="chkall" title="전체 선택"></th>
      <th>#</th><th>상태</th><th class="cdt">접수일시</th>
      <?php if (!$isApp): ?><th>이름</th><th>연락처</th><th class="hide-sm">소속</th><th>교육주제</th>
      <?php else: ?><th>성함</th><th>연락처</th><th class="hide-sm">강의분야</th><th>첨부</th><?php endif; ?>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $st = $r['status'] ?: $statusOpts[0]; $cls = status_class($st); $new = ($st === '신규'); $mc = count($memosByRow[$r['id']] ?? []);
      $rk = $r['kind'] ?? 'contact'; $isEvent = ($rk === 'event'); ?>
    <tr class="main<?= $new?' isnew':'' ?><?= $isEvent?' evrow':'' ?>" data-id="<?= (int)$r['id'] ?>" data-status="<?= h($st) ?>" data-kind="<?= h($rk) ?>">
      <td class="chk"><input type="checkbox" class="rowchk" name="ids[]" value="<?= (int)$r['id'] ?>" onclick="event.stopPropagation()"></td>
      <td class="cid"><span class="caret"></span><?= (int)$r['id'] ?></td>
      <td><span class="pill p-<?= $cls ?>"><i></i><?= h($st) ?></span><?php if ($isEvent): ?><span class="pill p-event">AX이벤트</span><?php endif; ?><span class="memoicon" title="메모 있음" style="<?= $mc?'':'display:none' ?>"><?= $ICON_MEMO ?></span></td>
      <td class="cdt"><?= h(substr($r['created_at'],0,16)) ?></td>
      <?php if (!$isApp): ?>
        <?php if ($isEvent): ?>
          <td class="tx"><?= h($r['name']) ?></td><td class="tx"><?= h($r['phone']) ?></td><td class="tx hide-sm"><?= h($r['company']) ?></td><td class="tx">AX 도서 이벤트<?= $r['org_size'] ? ' · '.h($r['org_size']) : '' ?></td>
        <?php else: ?>
          <td class="tx"><?= h($r['name']) ?></td><td class="tx"><?= h($r['phone']) ?></td><td class="tx hide-sm"><?= h($r['affiliation']) ?></td><td class="tx"><?= h($r['topic']) ?></td>
        <?php endif; ?>
      <?php else: ?>
        <td class="tx"><?= h($r['name']) ?></td><td class="tx"><?= h($r['phone']) ?></td><td class="tx hide-sm"><?= h($r['fields']) ?></td>
        <td class="catt"><?php if ($r['profile_file']): ?><a class="filelink" href="?dl=<?= (int)$r['id'] ?>" onclick="event.stopPropagation();return confirm('첨부파일을 다운로드할까요?');"><?= $ICON_DL ?><span class="flbl">파일</span></a><?php else: ?><span class="none">없음</span><?php endif; ?></td>
      <?php endif; ?>
    </tr>
    <tr class="detail"><td colspan="<?= $colspan ?>">
      <div class="panel">
        <?php if (!$isApp && $isEvent): ?>
          <div class="sec"><div class="sec-t">AX 고민 사연 이벤트 신청</div><div class="dl">
            <?= fld('이름',$r['name']) ?><?= fld('회사/기관',$r['company']) ?><?= fld('부서',$r['dept']) ?><?= fld('직책',$r['job_title']) ?>
            <?= fld('연락처',$r['phone']) ?><?= fld('이메일',$r['email']) ?><?= fld('조직규모',$r['org_size']) ?>
          </div></div>
          <div class="sec"><div class="sec-t">담당·관심·사연</div><div class="dl">
            <?= fld('담당업무',$r['role_work']) ?><?= fld('관심분야',$r['interests']) ?>
            <?= fld('상담희망',!empty($r['consult_wanted'])?'예':'아니오') ?><?= fld('마케팅동의',!empty($r['marketing_agreed'])?'예':'아니오') ?>
            <?= lng('AX 고민 사연',$r['story']) ?>
            <div class="d long"><dt>명함</dt><dd><?php if (!empty($r['card_file'])): ?><a class="filelink" href="?cdl=<?= (int)$r['id'] ?>" onclick="return confirm('명함 파일을 다운로드할까요?');"><?= $ICON_DL ?><?= h($r['card_original'] ?: '다운로드') ?></a><?php else: ?>없음<?php endif; ?></dd></div>
          </div></div>
          <div class="metaline">접수 <?= h($r['created_at']) ?> · IP <?= h($r['ip']) ?> · #<?= (int)$r['id'] ?></div>
        <?php elseif (!$isApp): ?>
          <div class="sec"><div class="sec-t">문의자</div><div class="dl">
            <?= fld('이름',$r['name']) ?><?= fld('연락처',$r['phone']) ?><?= fld('이메일',$r['email']) ?><?= fld('소속',$r['affiliation']) ?>
          </div></div>
          <div class="sec"><div class="sec-t">교육 요청</div><div class="dl">
            <?= fld('교육주제',$r['topic']) ?><?= fld('교육대상',$r['audience']) ?><?= fld('교육장소',$r['location']) ?>
            <?= lng('교육목적',$r['purpose']) ?><?= lng('문의내용 / RFP',$r['message']) ?>
          </div></div>
          <div class="metaline">접수 <?= h($r['created_at']) ?> · IP <?= h($r['ip']) ?> · #<?= (int)$r['id'] ?></div>
        <?php else: ?>
          <div class="sec"><div class="sec-t">인적사항</div><div class="dl">
            <?= fld('성함',$r['name']) ?><?= fld('연락처',$r['phone']) ?><?= fld('성별',$r['gender']) ?><?= fld('출생년도',$r['birth_year']) ?><?= fld('거주지역',$r['region']) ?>
          </div></div>
          <div class="sec"><div class="sec-t">경력 · 강의</div><div class="dl">
            <?= fld('경력기간',$r['career_years']) ?><?= fld('강의분야',$r['fields']) ?><?= fld('강의스타일',$r['styles']) ?><?= fld('출강기관',$r['client_types']) ?><?= fld('최소섭외비',$r['min_fee']) ?><?= fld('신청경로',$r['referral']) ?>
            <?= lng('주요경력',$r['prev_career']) ?><?= lng('소개',$r['intro']) ?>
            <div class="d long"><dt>첨부파일</dt><dd><?php if ($r['profile_file']): ?><a class="filelink" href="?dl=<?= (int)$r['id'] ?>" onclick="return confirm('첨부파일을 다운로드할까요?');"><?= $ICON_DL ?><?= h($r['profile_original'] ?: '다운로드') ?></a><?php else: ?>없음<?php endif; ?></dd></div>
          </div></div>
          <div class="metaline">접수 <?= h($r['created_at']) ?> · IP <?= h($r['ip']) ?> · #<?= (int)$r['id'] ?></div>
        <?php endif; ?>

        <div class="statusbar">
          <label>상태</label>
          <select class="st-sel"><?php foreach ($statusOpts as $s): ?><option<?= $s===$st?' selected':'' ?>><?= h($s) ?></option><?php endforeach; ?></select>
          <button type="button" class="save st-save">상태 저장</button>
          <span class="ok"></span>
        </div>

        <div class="memos" data-id="<?= (int)$r['id'] ?>">
          <div class="sec-t">처리 메모 · 히스토리</div>
          <div class="memo-list">
            <?php $ms = $memosByRow[$r['id']] ?? []; if (!$ms): ?><div class="memo-empty">아직 메모가 없습니다.</div><?php endif; ?>
            <?php foreach ($ms as $m): ?>
            <div class="memo-item" data-mid="<?= (int)$m['id'] ?>">
              <div class="memo-head"><span class="memo-time"><?= h(substr($m['created_at'],0,16)) ?></span><button type="button" class="memo-del" data-mid="<?= (int)$m['id'] ?>">삭제</button></div>
              <div class="memo-body"><?= h($m['body']) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="memo-add">
            <textarea class="memo-input" placeholder="새 메모 입력 (저장 시 시간이 기록됩니다)"></textarea>
            <button type="button" class="memo-addbtn">메모 추가</button>
          </div>
        </div>
      </div>
    </td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>
</div>
</form>

<script>
(function(){
  var TAB='<?= $tab ?>', TOTAL=<?= count($rows) ?>;
  function esc(s){ var d=document.createElement('div'); d.textContent=s==null?'':s; return d.innerHTML; }

  document.querySelectorAll('tr.main').forEach(function(row){
    row.addEventListener('click',function(e){
      if(e.target.closest('input,select,textarea,a,button,label')) return;
      var d=row.nextElementSibling;
      row.classList.toggle('open'); d.classList.toggle('show');
      row.querySelector('.caret').style.transform=d.classList.contains('show')?'rotate(90deg)':'';
    });
  });

  var all=document.getElementById('chkall');
  var boxes=Array.prototype.slice.call(document.querySelectorAll('.rowchk'));
  var count=document.getElementById('selcount');
  function visRows(){ return Array.prototype.slice.call(document.querySelectorAll('tr.main')).filter(function(r){return r.style.display!=='none';}); }
  function selCount(){ return boxes.filter(function(b){return b.checked;}).length; }
  function refresh(){ var n=selCount(); if(count)count.textContent=n; if(all)all.checked=n>0&&n===boxes.length; }
  if(all)all.addEventListener('change',function(){ visRows().forEach(function(r){ r.querySelector('.rowchk').checked=all.checked; }); refresh(); });
  boxes.forEach(function(b){b.addEventListener('change',refresh);});

  document.querySelectorAll('button[name=export]').forEach(function(btn){
    btn.addEventListener('click',function(e){
      var mode=btn.value, sel=selCount(), n=(mode==='csv_all')?TOTAL:sel;
      if(mode!=='csv_all' && sel===0){ e.preventDefault(); alert('먼저 왼쪽 체크박스로 항목을 선택하세요.'); return; }
      var what=(mode==='zip_sel')?'첨부파일':'데이터';
      if(!confirm(n+'건 '+what+'을(를) 다운로드할까요?')){ e.preventDefault(); }
    });
  });

  // 선택 삭제 (되돌릴 수 없음)
  var delBtn=document.querySelector('button[name=bulk_delete]');
  if(delBtn)delBtn.addEventListener('click',function(e){
    var sel=selCount();
    if(sel===0){ e.preventDefault(); alert('먼저 삭제할 항목을 체크하세요.'); return; }
    if(!confirm(sel+'건을 영구 삭제합니다.\n첨부파일·메모도 함께 삭제되며 되돌릴 수 없습니다.\n계속할까요?')){ e.preventDefault(); }
  });

  var curF='all', curKind='', q='';
  var fbtns=document.querySelectorAll('#filters button');
  var search=document.getElementById('search');
  var viewcount=document.getElementById('viewcount');
  function applyFilter(){
    var shown=0;
    document.querySelectorAll('tr.main').forEach(function(row){
      var d=row.nextElementSibling;
      var okF=curKind ? (row.dataset.kind===curKind) : (curF==='all'||row.dataset.status===curF);
      var okQ=q===''||(row.textContent+' '+d.textContent).toLowerCase().indexOf(q)>=0;
      var show=okF&&okQ; row.style.display=show?'':'none';
      if(!show){ d.style.display='none'; d.classList.remove('show'); row.classList.remove('open'); var c=row.querySelector('.caret'); if(c)c.style.transform=''; } else { d.style.display=''; shown++; }
    });
    if(viewcount)viewcount.textContent=shown; refresh();
  }
  fbtns.forEach(function(b){ b.addEventListener('click',function(){ fbtns.forEach(function(x){x.classList.remove('on');}); b.classList.add('on'); if(b.dataset.kind){ curKind=b.dataset.kind; curF='all'; } else { curKind=''; curF=b.dataset.f; } applyFilter(); }); });
  if(search)search.addEventListener('input',function(){ q=search.value.trim().toLowerCase(); applyFilter(); });
  var onlyNew=document.getElementById('onlyNew');
  if(onlyNew)onlyNew.addEventListener('click',function(){ var t=Array.prototype.slice.call(fbtns).filter(function(b){return b.dataset.f==='신규';})[0]; if(t)t.click(); window.scrollTo({top:0,behavior:'smooth'}); });

  function post(body){ return fetch(location.pathname,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body}).then(function(r){return r.json();}); }

  // 상태 저장
  document.querySelectorAll('.st-save').forEach(function(btn){
    btn.addEventListener('click',function(){
      var bar=btn.closest('.statusbar');
      var row=btn.closest('tr.detail').previousElementSibling, rid=row.dataset.id;
      var status=bar.querySelector('.st-sel').value;
      var ok=bar.querySelector('.ok'); ok.textContent='저장 중…'; ok.style.color='#8b91a3';
      post('action=status_save&table='+encodeURIComponent(TAB)+'&id='+encodeURIComponent(rid)+'&status='+encodeURIComponent(status))
        .then(function(j){
          if(j.ok){ ok.textContent='✓ 저장됨'; ok.style.color='#047857';
            row.dataset.status=status; row.classList.toggle('isnew',status==='신규');
            var bd=row.querySelector('.pill'); bd.className='pill p-'+j.cls; bd.innerHTML='<i></i>'+esc(status);
            applyFilter(); setTimeout(function(){ok.textContent='';},2200);
          } else { ok.textContent='✗ '+(j.message||'실패'); ok.style.color='#be123c'; alert('상태 저장 실패: '+(j.message||'오류')); }
        }).catch(function(){ ok.textContent='✗ 통신오류'; ok.style.color='#be123c'; });
    });
  });

  // 메모 추가
  function memoItemHTML(m){
    return '<div class="memo-item" data-mid="'+m.id+'"><div class="memo-head"><span class="memo-time">'+esc(m.created_at.substring(0,16))+'</span>'+
           '<button type="button" class="memo-del" data-mid="'+m.id+'">삭제</button></div><div class="memo-body">'+esc(m.body)+'</div></div>';
  }
  function bindDel(btn){
    btn.addEventListener('click',function(){
      if(!confirm('이 메모를 삭제할까요?')) return;
      var mid=btn.dataset.mid, item=btn.closest('.memo-item'), memos=btn.closest('.memos');
      post('action=memo_del&memoId='+encodeURIComponent(mid)).then(function(j){
        if(j.ok){ item.remove(); afterMemoChange(memos); } else { alert('삭제 실패'); }
      });
    });
  }
  function afterMemoChange(memos){
    var list=memos.querySelector('.memo-list');
    var items=list.querySelectorAll('.memo-item');
    var empty=list.querySelector('.memo-empty');
    if(items.length===0){ if(!empty){ var e=document.createElement('div'); e.className='memo-empty'; e.textContent='아직 메모가 없습니다.'; list.appendChild(e);} }
    else if(empty){ empty.remove(); }
    // 행의 메모 아이콘 토글
    var rid=memos.dataset.id;
    var row=document.querySelector('tr.main[data-id="'+rid+'"]');
    var mi=row?row.querySelector('.memoicon'):null;
    if(mi)mi.style.display=items.length>0?'':'none';
  }
  document.querySelectorAll('.memo-del').forEach(bindDel);
  document.querySelectorAll('.memo-addbtn').forEach(function(btn){
    btn.addEventListener('click',function(){
      var memos=btn.closest('.memos'), id=memos.dataset.id, ta=memos.querySelector('.memo-input');
      var body=ta.value.trim(); if(body===''){ ta.focus(); return; }
      btn.disabled=true;
      post('action=memo_add&table='+encodeURIComponent(TAB)+'&id='+encodeURIComponent(id)+'&body='+encodeURIComponent(body))
        .then(function(j){
          btn.disabled=false;
          if(j.ok){
            var list=memos.querySelector('.memo-list');
            list.insertAdjacentHTML('beforeend', memoItemHTML(j.memo));
            bindDel(list.querySelector('.memo-item:last-child .memo-del'));
            ta.value=''; afterMemoChange(memos);
          } else { alert('메모 저장 실패: '+(j.message||'오류')); }
        }).catch(function(){ btn.disabled=false; alert('통신 오류'); });
    });
  });

  applyFilter();
})();
</script>
</body></html>
