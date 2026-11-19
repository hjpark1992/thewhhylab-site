<?php
/**
 * 더와이랩 관리자 v2 — 교육문의 / AX 이벤트 / 강사지원 조회·관리.
 * 좌측 메뉴 + 대시보드 · 상태별 건수 카드 · 서버 검색/필터/기간/정렬/페이지네이션 · 우측 상세 패널(상태 변경·메모·첨부)
 * · 행 인라인 상태 변경 · 일괄 상태 변경 · 선택/전체 CSV · 첨부 단건/ZIP · 선택 삭제(행+첨부+메모) · 디스크 게이지 · 모바일 카드형.
 * 보안: 아이디+비번 로그인, IP별 5회 실패 10분 잠금, 세션 비활성 타임아웃, CSRF 토큰(모든 POST), PDO prepared, 업로드 경로 검증.
 * 파일: admin.php(로직) + admin.css + admin.js. 접속: /admin 또는 /api/admin.php
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
$assetBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/api/admin.php')), '/'); // /admin 리라이트여도 /api 기준으로 css/js 로드

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
function login_attempts_save($file, $data) { @file_put_contents($file, json_encode($data), LOCK_EX); }
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
  if ($until > 0 && $until <= time()) $e = ['fails' => 0, 'until' => 0];
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
$SESSION_TIMEOUT = (int)($cfg['admin_session_timeout'] ?? 1800);
$sessionTimedOut = false;
if (!empty($_SESSION['twl_admin'])) {
  if (isset($_SESSION['twl_last']) && (time() - (int)$_SESSION['twl_last']) > $SESSION_TIMEOUT) {
    $_SESSION = []; $sessionTimedOut = true;
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
  <link rel="stylesheet" href="<?= h($assetBase) ?>/admin.css?v=<?= @filemtime($base . '/admin.css') ?: 1 ?>">
  </head><body class="login-body">
  <form class="login-box" method="post" autocomplete="off">
    <img class="login-logo" src="/shared/img/logo-blue.png" alt="더와이랩 THE WHHY LAB">
    <h1>관리자 로그인</h1><p class="sub">접수내역 조회·관리</p>
    <label class="login-field"><span>아이디</span><input type="text" name="user" autocomplete="username" autofocus required></label>
    <label class="login-field"><span>비밀번호</span><input type="password" name="pw" autocomplete="current-password" required></label>
    <button type="submit" class="login-btn"<?= $loginLocked ? ' disabled' : '' ?>>로그인</button>
    <div class="login-err"><?php
      if ($adminUser === '' || $adminPass === '') echo 'config.php에 admin_user / admin_pass 를 설정하세요.';
      elseif ($loginLocked) echo h("로그인이 잠겼습니다. 약 {$loginLockLeft}분 후 다시 시도하세요.");
      elseif ($sessionTimedOut) echo '세션이 만료되어 로그아웃되었습니다. 다시 로그인해주세요.';
      else echo h($err);
    ?></div>
  </form></body></html><?php
  exit;
}

/* ---------- CSRF ---------- */
if (empty($_SESSION['twl_csrf'])) $_SESSION['twl_csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['twl_csrf'];
function csrf_ok() {
  $t = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
  return is_string($t) && hash_equals($_SESSION['twl_csrf'] ?? '', $t);
}
function json_out($arr) { header('Content-Type: application/json; charset=UTF-8'); echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

/* ---------- DB 연결 ---------- */
require $base . '/lib/db.php';
require $base . '/lib/schema.php';
try { $pdo = db_connect($cfg['db']); }
catch (Throwable $e) { http_response_code(500); exit('DB 접속 오류: ' . h($e->getMessage())); }

$uploadDir = $cfg['upload_dir'] !== '' ? rtrim($cfg['upload_dir'], '/\\') : ($base . '/uploads');

/* 스키마 자동 생성: 상태 컬럼 + 메모 히스토리 테이블 */
function ensure_schema($pdo) {
  $cols = ['inquiries' => "VARCHAR(20) NOT NULL DEFAULT '신규'", 'member_applications' => "VARCHAR(20) NOT NULL DEFAULT '신규'"];
  foreach ($cols as $tbl => $type) {
    try { $have = $pdo->query("SHOW COLUMNS FROM `{$tbl}`")->fetchAll(PDO::FETCH_COLUMN); } catch (Throwable $e) { continue; }
    if (!in_array('status', $have, true)) { try { $pdo->exec("ALTER TABLE `{$tbl}` ADD COLUMN `status` {$type}"); } catch (Throwable $e) {} }
  }
  try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_memos (
      id INT AUTO_INCREMENT PRIMARY KEY, ref_type VARCHAR(24) NOT NULL, ref_id INT NOT NULL, body TEXT NOT NULL, created_at DATETIME NOT NULL, KEY idx_ref (ref_type, ref_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  } catch (Throwable $e) {}
}
ensure_schema($pdo);
ensure_event_columns($pdo);

/* ---------- 뷰 정의 ---------- */
$VIEWS = [
  'inq'   => ['table' => 'inquiries',           'kind' => 'contact', 'label' => '교육문의',  'unit' => '건', 'who' => '문의자'],
  'event' => ['table' => 'inquiries',           'kind' => 'event',   'label' => 'AX 이벤트', 'unit' => '건', 'who' => '신청자'],
  'app'   => ['table' => 'member_applications', 'kind' => null,      'label' => '강사지원',  'unit' => '건', 'who' => '지원자'],
];
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
function view_key($v) { global $VIEWS; return isset($VIEWS[$v]) ? $v : 'inq'; }
function tbl_of_view($v) { global $VIEWS; return $VIEWS[view_key($v)]['table']; }
/* 뷰의 kind 조건(테이블 공유 시) → [sql, params] */
function kind_where($v) {
  global $VIEWS; $k = $VIEWS[view_key($v)]['kind'];
  if ($k === null) return ['1=1', []];
  if ($k === 'contact') return ["(kind = ? OR kind IS NULL OR kind = '')", ['contact']];
  return ['kind = ?', [$k]];
}
function safe_upload_path($dir, $stored) {
  if (!$stored) return null;
  $path = $dir . '/' . basename($stored);
  $rd = realpath($dir); $rp = realpath($path);
  if ($rp === false || $rd === false || strpos($rp, $rd) !== 0 || !is_file($rp)) return null;
  return $rp;
}
function dir_size($dir) {
  if (!is_dir($dir)) return 0;
  $total = 0;
  try { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY); foreach ($it as $f) { $total += $f->getSize(); } } catch (Throwable $e) {}
  return $total;
}
function fmt_bytes($b) {
  if ($b >= 1073741824) return number_format($b / 1073741824, 2) . ' GB';
  if ($b >= 1048576)    return number_format($b / 1048576, 1) . ' MB';
  if ($b >= 1024)       return number_format($b / 1024, 0) . ' KB';
  return $b . ' B';
}
function rel_time($dt) {
  $t = strtotime((string)$dt); if (!$t) return '-';
  $d = time() - $t;
  if ($d < 60) return '방금 전';
  if ($d < 3600) return floor($d / 60) . '분 전';
  if ($d < 86400) return floor($d / 3600) . '시간 전';
  if ($d < 86400 * 7) return floor($d / 86400) . '일 전';
  if (date('Y', $t) === date('Y')) return date('n월 j일', $t);
  return date('Y.m.d', $t);
}
function memos_for($pdo, $refType, $ids) {
  if (!$ids) return [];
  $in = implode(',', array_fill(0, count($ids), '?'));
  $st = $pdo->prepare("SELECT * FROM admin_memos WHERE ref_type = ? AND ref_id IN ($in) ORDER BY id ASC");
  $st->execute(array_merge([$refType], $ids));
  $out = [];
  foreach ($st->fetchAll() as $m) { $out[$m['ref_id']][] = $m; }
  return $out;
}
function add_memo($pdo, $tbl, $id, $body) {
  $now = date('Y-m-d H:i:s');
  $st = $pdo->prepare("INSERT INTO admin_memos (ref_type, ref_id, body, created_at) VALUES (?,?,?,?)");
  $st->execute([$tbl, (int)$id, $body, $now]);
  return ['id' => (int)$pdo->lastInsertId(), 'body' => $body, 'created_at' => $now];
}
/* 상태 변경 (자동 메모 포함) */
function set_status($pdo, $tbl, $id, $status) {
  global $STATUS;
  if (!in_array($status, $STATUS[$tbl], true)) return [false, '잘못된 상태 값'];
  $st = $pdo->prepare("SELECT status FROM {$tbl} WHERE id = ?"); $st->execute([(int)$id]); $cur = $st->fetchColumn();
  if ($cur === false) return [false, '항목 없음'];
  $cur = $cur ?: '신규';
  if ($cur === $status) return [true, null];
  $pdo->prepare("UPDATE {$tbl} SET status = ? WHERE id = ?")->execute([$status, (int)$id]);
  try { add_memo($pdo, $tbl, $id, "상태 변경 · {$cur} → {$status}"); } catch (Throwable $e) {}
  return [true, null];
}

/* CSV 컬럼 */
$COLS = [
  'inquiries' => ['ID'=>'id','구분'=>'kind','상태'=>'status','접수일시'=>'created_at','이름'=>'name','연락처'=>'phone','이메일'=>'email','소속'=>'affiliation','교육주제'=>'topic','교육목적'=>'purpose','교육대상'=>'audience','교육장소'=>'location','문의내용/RFP'=>'message','회사명'=>'company','부서'=>'dept','직책'=>'job_title','담당업무'=>'role_work','조직규모'=>'org_size','관심분야'=>'interests','AX사연'=>'story','상담희망'=>'consult_wanted','마케팅동의'=>'marketing_agreed','명함원본'=>'card_original','관리메모'=>'__memos__','IP'=>'ip'],
  'member_applications' => ['ID'=>'id','상태'=>'status','접수일시'=>'created_at','성함'=>'name','연락처'=>'phone','성별'=>'gender','출생년도'=>'birth_year','거주지역'=>'region','경력기간'=>'career_years','주요경력'=>'prev_career','강의분야'=>'fields','강의스타일'=>'styles','소개'=>'intro','출강기관'=>'client_types','최소섭외비'=>'min_fee','신청경로'=>'referral','첨부원본파일명'=>'profile_original','관리메모'=>'__memos__','IP'=>'ip'],
];

/* ---------- 첨부 다운로드 ---------- */
function send_file($path, $fn) {
  $fnAscii = preg_replace('/[\r\n"]/', '', $fn);
  header('Content-Type: application/octet-stream');
  header('Content-Length: ' . filesize($path));
  header("Content-Disposition: attachment; filename=\"{$fnAscii}\"; filename*=UTF-8''" . rawurlencode($fn));
  header('X-Content-Type-Options: nosniff');
  readfile($path); exit;
}
if (isset($_GET['dl'])) {
  $st = $pdo->prepare('SELECT profile_file, profile_original FROM member_applications WHERE id = ?'); $st->execute([(int)$_GET['dl']]); $r = $st->fetch();
  $path = $r ? safe_upload_path($uploadDir, $r['profile_file']) : null;
  if (!$path) { http_response_code(404); exit('파일을 찾을 수 없습니다.'); }
  send_file($path, $r['profile_original'] ?: basename($path));
}
if (isset($_GET['cdl'])) {
  $st = $pdo->prepare('SELECT card_file, card_original FROM inquiries WHERE id = ?'); $st->execute([(int)$_GET['cdl']]); $r = $st->fetch();
  $path = $r ? safe_upload_path($uploadDir, $r['card_file']) : null;
  if (!$path) { http_response_code(404); exit('파일을 찾을 수 없습니다.'); }
  send_file($path, $r['card_original'] ?: basename($path));
}

/* ---------- 상세 JSON (우측 패널) ---------- */
function detail_sections($view, $r) {
  $yn = function ($v) { return !empty($v) ? '예' : '아니오'; };
  $file = null;
  if ($view === 'app') {
    if (!empty($r['profile_file'])) $file = ['label' => $r['profile_original'] ?: '첨부파일', 'url' => '?dl=' . (int)$r['id']];
    return [[ 'title' => '인적사항', 'items' => [
        ['성함', $r['name']], ['연락처', $r['phone']], ['성별', $r['gender']], ['출생년도', $r['birth_year']], ['거주지역', $r['region']] ]],
      [ 'title' => '경력 · 강의', 'items' => [
        ['경력기간', $r['career_years'], 'wide'], ['강의분야', $r['fields'], 'wide'], ['강의스타일', $r['styles'], 'wide'], ['출강기관', $r['client_types'], 'wide'], ['최소섭외비', $r['min_fee'], 'wide'], ['신청경로', $r['referral'], 'wide'],
        ['주요경력', $r['prev_career'], true], ['소개', $r['intro'], true], ['첨부파일', $file, 'file'] ]],
    ];
  }
  if ($view === 'event') {
    if (!empty($r['card_file'])) $file = ['label' => $r['card_original'] ?: '명함', 'url' => '?cdl=' . (int)$r['id']];
    return [[ 'title' => '신청자', 'items' => [
        ['이름', $r['name']], ['회사/기관', $r['company']], ['부서', $r['dept']], ['직책', $r['job_title']], ['연락처', $r['phone']], ['이메일', $r['email']], ['조직규모', $r['org_size']] ]],
      [ 'title' => '담당 · 관심 · 사연', 'items' => [
        ['담당업무', $r['role_work']], ['관심분야', $r['interests']], ['상담희망', $yn($r['consult_wanted'] ?? 0)], ['마케팅동의', $yn($r['marketing_agreed'] ?? 0)],
        ['AX 고민 사연', $r['story'], true], ['명함', $file, 'file'] ]],
    ];
  }
  return [[ 'title' => '문의자', 'items' => [
      ['이름', $r['name']], ['연락처', $r['phone']], ['이메일', $r['email']], ['소속', $r['affiliation']] ]],
    [ 'title' => '교육 요청', 'items' => [
      ['교육주제', $r['topic']], ['교육대상', $r['audience']], ['교육장소', $r['location']], ['교육목적', $r['purpose'], true], ['문의내용 / RFP', $r['message'], true] ]],
  ];
}
if (($_GET['action'] ?? '') === 'detail') {
  $view = view_key($_GET['view'] ?? ''); $tbl = tbl_of_view($view); $id = (int)($_GET['id'] ?? 0);
  $st = $pdo->prepare("SELECT * FROM {$tbl} WHERE id = ?"); $st->execute([$id]); $r = $st->fetch();
  if (!$r) json_out(['ok' => false, 'message' => '항목이 없습니다.']);
  $memos = memos_for($pdo, $tbl, [$id]);
  $secs = [];
  foreach (detail_sections($view, $r) as $s) {
    $items = [];
    foreach ($s['items'] as $it) {
      $items[] = ['label' => $it[0], 'value' => $it[1], 'long' => (($it[2] ?? false) === true), 'wide' => (($it[2] ?? '') === 'wide'), 'file' => (($it[2] ?? '') === 'file')];
    }
    $secs[] = ['title' => $s['title'], 'items' => $items];
  }
  json_out(['ok' => true, 'id' => $id, 'view' => $view, 'status' => $r['status'] ?: '신규', 'statusClass' => status_class($r['status'] ?: '신규'),
    'statusOpts' => $STATUS[$tbl], 'name' => $r['name'], 'created_at' => $r['created_at'], 'rel' => rel_time($r['created_at']), 'ip' => $r['ip'],
    'sections' => $secs, 'memos' => array_values($memos[$id] ?? [])]);
}

/* ---------- JSON 액션 (POST) ---------- */
$act = $_POST['action'] ?? '';
if ($act !== '') {
  if (!csrf_ok()) json_out(['ok' => false, 'message' => '세션이 만료되었습니다. 새로고침 후 다시 시도하세요.']);
  if ($act === 'status_save') {
    $tbl = tbl_of_view($_POST['view'] ?? ''); $id = (int)($_POST['id'] ?? 0); $status = (string)($_POST['status'] ?? '');
    if ($id <= 0) json_out(['ok' => false, 'message' => '잘못된 값']);
    try { list($ok, $msg) = set_status($pdo, $tbl, $id, $status); json_out(['ok' => $ok, 'message' => $msg, 'cls' => status_class($status), 'status' => $status]); }
    catch (Throwable $e) { json_out(['ok' => false, 'message' => $e->getMessage()]); }
  }
  if ($act === 'bulk_status') {
    $tbl = tbl_of_view($_POST['view'] ?? ''); $status = (string)($_POST['status'] ?? '');
    $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])), function ($v) { return $v > 0; }));
    if (!$ids) json_out(['ok' => false, 'message' => '선택된 항목이 없습니다.']);
    $n = 0; foreach ($ids as $id) { try { list($ok) = set_status($pdo, $tbl, $id, $status); if ($ok) $n++; } catch (Throwable $e) {} }
    json_out(['ok' => true, 'count' => $n]);
  }
  if ($act === 'memo_add') {
    $tbl = tbl_of_view($_POST['view'] ?? ''); $id = (int)($_POST['id'] ?? 0); $body = trim((string)($_POST['body'] ?? ''));
    if ($id <= 0 || $body === '') json_out(['ok' => false, 'message' => '내용이 비어 있습니다.']);
    try { json_out(['ok' => true, 'memo' => add_memo($pdo, $tbl, $id, $body)]); } catch (Throwable $e) { json_out(['ok' => false, 'message' => $e->getMessage()]); }
  }
  if ($act === 'memo_del') {
    $mid = (int)($_POST['memoId'] ?? 0);
    if ($mid <= 0) json_out(['ok' => false]);
    try { $pdo->prepare("DELETE FROM admin_memos WHERE id = ?")->execute([$mid]); json_out(['ok' => true]); } catch (Throwable $e) { json_out(['ok' => false, 'message' => $e->getMessage()]); }
  }
  json_out(['ok' => false, 'message' => '알 수 없는 요청']);
}

/* ---------- 일괄 CSV / ZIP ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['export'])) {
  if (!csrf_ok()) { http_response_code(403); exit('세션이 만료되었습니다. 새로고침 후 다시 시도하세요.'); }
  $view = view_key($_POST['view'] ?? ''); $which = tbl_of_view($view);
  $mode = $_POST['export'];
  $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])), function ($v) { return $v > 0; }));
  $stamp = date('Ymd_His');
  if ($mode === 'csv_all') {
    list($kw, $kp) = kind_where($view);
    $st = $pdo->prepare("SELECT * FROM {$which} WHERE {$kw} ORDER BY id DESC"); $st->execute($kp); $rowsX = $st->fetchAll();
  } else {
    if (!$ids) { http_response_code(400); exit('선택된 항목이 없습니다.'); }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT * FROM {$which} WHERE id IN ($in) ORDER BY id DESC"); $st->execute($ids); $rowsX = $st->fetchAll();
  }
  if ($mode === 'csv_all' || $mode === 'csv_sel') {
    $memoMap = memos_for($pdo, $which, array_column($rowsX, 'id'));
    $fname = "더와이랩_{$VIEWS[$view]['label']}_{$stamp}.csv";
    header('Content-Type: text/csv; charset=UTF-8');
    header("Content-Disposition: attachment; filename=\"export.csv\"; filename*=UTF-8''" . rawurlencode($fname));
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w'); $map = $COLS[$which];
    fputcsv($out, array_keys($map));
    foreach ($rowsX as $r) {
      $line = [];
      foreach ($map as $col) {
        if ($col === '__memos__') { $parts = []; foreach ($memoMap[$r['id']] ?? [] as $m) { $parts[] = '[' . substr($m['created_at'], 0, 16) . '] ' . $m['body']; } $line[] = implode("\n", $parts); }
        else { $line[] = $r[$col] ?? ''; }
      }
      fputcsv($out, $line);
    }
    fclose($out); exit;
  }
  if ($mode === 'zip_sel') {
    if ($which !== 'member_applications') { http_response_code(400); exit('첨부 ZIP은 강사지원만 가능합니다.'); }
    if (!class_exists('ZipArchive')) { http_response_code(500); exit('서버에 ZipArchive 모듈이 없어 ZIP을 만들 수 없습니다. 첨부는 단건으로 받아주세요.'); }
    $tmp = tempnam(sys_get_temp_dir(), 'twlzip'); $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) { http_response_code(500); exit('ZIP 생성 실패.'); }
    $added = 0; $seq = 0;
    foreach ($rowsX as $r) {
      $path = safe_upload_path($uploadDir, $r['profile_file']); if (!$path) continue;
      $orig = $r['profile_original'] ?: basename($path);
      $zip->addFile($path, $r['id'] . '_' . (++$seq) . '_' . preg_replace('/[\/\\\\:*?"<>|]/', '_', $orig)); $added++;
    }
    $zip->close();
    if ($added === 0) { @unlink($tmp); http_response_code(404); exit('선택한 항목에 첨부파일이 없습니다.'); }
    $fname = "더와이랩_강사지원_첨부_{$stamp}.zip";
    header('Content-Type: application/zip'); header('Content-Length: ' . filesize($tmp));
    header("Content-Disposition: attachment; filename=\"attachments.zip\"; filename*=UTF-8''" . rawurlencode($fname));
    readfile($tmp); @unlink($tmp); exit;
  }
}

/* ---------- 선택 삭제 (행 + 첨부파일 + 메모) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['bulk_delete'])) {
  if (!csrf_ok()) { http_response_code(403); exit('세션이 만료되었습니다. 새로고침 후 다시 시도하세요.'); }
  $view = view_key($_POST['view'] ?? ''); $which = tbl_of_view($view);
  $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])), function ($v) { return $v > 0; }));
  if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $fileCol = $which === 'member_applications' ? 'profile_file' : 'card_file';
    try { $st = $pdo->prepare("SELECT {$fileCol} FROM {$which} WHERE id IN ($in)"); $st->execute($ids);
      foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $pf) { $p = safe_upload_path($uploadDir, $pf); if ($p) @unlink($p); } } catch (Throwable $e) {}
    try { $pdo->prepare("DELETE FROM admin_memos WHERE ref_type = ? AND ref_id IN ($in)")->execute(array_merge([$which], $ids)); } catch (Throwable $e) {}
    $pdo->prepare("DELETE FROM {$which} WHERE id IN ($in)")->execute($ids);
  }
  header('Location: ' . $self . '?view=' . $view . '&deleted=' . count($ids)); exit;
}

/* ---------- 집계 ---------- */
function view_counts($pdo, $view) {
  global $VIEWS, $STATUS;
  $tbl = tbl_of_view($view); list($kw, $kp) = kind_where($view);
  $out = ['total' => 0, 'week' => 0, 'today' => 0, 'by' => array_fill_keys($STATUS[$tbl], 0)];
  try {
    $st = $pdo->prepare("SELECT status, COUNT(*) c FROM {$tbl} WHERE {$kw} GROUP BY status"); $st->execute($kp);
    foreach ($st->fetchAll() as $r) { $s = $r['status'] ?: '신규'; if (!isset($out['by'][$s])) $out['by'][$s] = 0; $out['by'][$s] += (int)$r['c']; $out['total'] += (int)$r['c']; }
    $monday = date('Y-m-d 00:00:00', strtotime('monday this week'));
    $st = $pdo->prepare("SELECT COUNT(*) FROM {$tbl} WHERE {$kw} AND created_at >= ?"); $st->execute(array_merge($kp, [$monday])); $out['week'] = (int)$st->fetchColumn();
    $st = $pdo->prepare("SELECT COUNT(*) FROM {$tbl} WHERE {$kw} AND created_at >= ?"); $st->execute(array_merge($kp, [date('Y-m-d 00:00:00')])); $out['today'] = (int)$st->fetchColumn();
  } catch (Throwable $e) {}
  return $out;
}
$counts = []; foreach (array_keys($VIEWS) as $v) $counts[$v] = view_counts($pdo, $v);

/* ---------- 페이지 상태 ---------- */
$view = $_GET['view'] ?? 'dash';
if ($view !== 'dash') $view = view_key($view);
$q = trim((string)($_GET['q'] ?? ''));
$fStatus = (string)($_GET['status'] ?? '');
$period = (string)($_GET['period'] ?? 'all');
$sort = (string)($_GET['sort'] ?? 'new');
$page = max(1, (int)($_GET['page'] ?? 1));
$PER = 20;

$rows = []; $total = 0; $pages = 1;
if ($view !== 'dash') {
  $tbl = tbl_of_view($view); list($kw, $kp) = kind_where($view);
  $where = [$kw]; $params = $kp;
  if ($fStatus !== '' && in_array($fStatus, $STATUS[$tbl], true)) {
    if ($fStatus === '신규') { $where[] = "(status = ? OR status IS NULL OR status = '')"; $params[] = '신규'; }
    else { $where[] = 'status = ?'; $params[] = $fStatus; }
  }
  if (in_array($period, ['7', '30', '90'], true)) { $where[] = 'created_at >= ?'; $params[] = date('Y-m-d 00:00:00', strtotime("-{$period} days")); }
  if ($q !== '') {
    $cols = $tbl === 'member_applications' ? ['name', 'phone', 'email', 'region', 'fields', 'prev_career', 'intro', 'referral'] : ['name', 'phone', 'email', 'affiliation', 'topic', 'purpose', 'message', 'company', 'dept', 'story'];
    $like = '%' . $q . '%'; $or = [];
    foreach ($cols as $c) { $or[] = "{$c} LIKE ?"; $params[] = $like; }
    if (ctype_digit($q)) { $or[] = 'id = ?'; $params[] = (int)$q; }
    $where[] = '(' . implode(' OR ', $or) . ')';
  }
  $wsql = implode(' AND ', $where);
  $order = $sort === 'old' ? 'id ASC' : 'id DESC';
  try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM {$tbl} WHERE {$wsql}"); $st->execute($params); $total = (int)$st->fetchColumn();
    $pages = max(1, (int)ceil($total / $PER)); if ($page > $pages) $page = $pages;
    $off = ($page - 1) * $PER;
    $st = $pdo->prepare("SELECT * FROM {$tbl} WHERE {$wsql} ORDER BY {$order} LIMIT {$PER} OFFSET {$off}"); $st->execute($params); $rows = $st->fetchAll();
  } catch (Throwable $e) { $rows = []; $total = 0; }
  $memoCount = [];
  if ($rows) { foreach (memos_for($pdo, $tbl, array_column($rows, 'id')) as $rid => $ms) $memoCount[$rid] = count($ms); }
}

/* 대시보드: 최근 접수 통합 */
$recent = [];
if ($view === 'dash') {
  foreach ($VIEWS as $vk => $vd) {
    list($kw, $kp) = kind_where($vk);
    try { $st = $pdo->prepare("SELECT * FROM {$vd['table']} WHERE {$kw} ORDER BY id DESC LIMIT 8"); $st->execute($kp);
      foreach ($st->fetchAll() as $r) { $r['_view'] = $vk; $recent[] = $r; } } catch (Throwable $e) {}
  }
  usort($recent, function ($a, $b) { return strcmp($b['created_at'], $a['created_at']); });
  $recent = array_slice($recent, 0, 10);
}

/* 디스크 */
$DISK_LIMIT = 10 * 1073741824;
$diskUsed = dir_size(dirname($base)); $uploadsUsed = dir_size($uploadDir);
$diskPct = $DISK_LIMIT > 0 ? min(100, $diskUsed / $DISK_LIMIT * 100) : 0;
$diskLevel = $diskPct >= 90 ? 'crit' : ($diskPct >= 70 ? 'warn' : 'ok');

/* URL 헬퍼 */
function url_with($over = []) {
  $p = array_merge(['view' => $_GET['view'] ?? 'dash', 'q' => $_GET['q'] ?? '', 'status' => $_GET['status'] ?? '', 'period' => $_GET['period'] ?? '', 'sort' => $_GET['sort'] ?? '', 'page' => $_GET['page'] ?? ''], $over);
  $p = array_filter($p, function ($v) { return $v !== '' && $v !== null && $v !== 'all' && $v !== 'new' && $v !== '1' && $v !== 1; });
  return '?' . http_build_query($p);
}
function row_summary($view, $r) {
  if ($view === 'app') return [$r['fields'] ?: '강의분야 미기재', trim(($r['career_years'] ?: '') . ($r['region'] ? ' · ' . $r['region'] : ''))];
  if ($view === 'event') return ['AX 고민 사연', trim(($r['interests'] ?: '') . ($r['org_size'] ? ' · ' . $r['org_size'] : ''))];
  return [$r['topic'] ?: '교육 문의', $r['audience'] ?: ''];
}
function row_org($view, $r) { return $view === 'app' ? ($r['phone'] ?: '') : ($view === 'event' ? ($r['company'] ?: '') : ($r['affiliation'] ?: '')); }

$pageTitle = $view === 'dash' ? '대시보드' : $VIEWS[$view]['label'];
$newTotal = $counts['inq']['by']['신규'] + $counts['event']['by']['신규'] + $counts['app']['by']['신규'];
header('Content-Type: text/html; charset=UTF-8');
?><!doctype html><html lang="ko"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title><?= h($pageTitle) ?> · 더와이랩 관리자</title>
<link rel="stylesheet" href="<?= h($assetBase) ?>/admin.css?v=<?= @filemtime($base . '/admin.css') ?: 1 ?>">
</head><body data-view="<?= h($view) ?>" data-csrf="<?= h($CSRF) ?>">
<div class="app">
  <aside class="side" id="side">
    <div class="side-brand"><img class="side-wordmark" src="/shared/img/logo-blue.png" alt="THE WHHY LAB"><div class="side-brand-row"><img class="side-symbol" src="/shared/img/favicon.png" alt=""><span>더와이랩 관리자</span></div></div>
    <nav class="side-nav" aria-label="운영자 메뉴">
      <div class="side-group">운영자 메뉴</div>
      <a href="?view=dash" class="<?= $view === 'dash' ? 'on' : '' ?>"><i class="ico ico-dash"></i>대시보드<?php if ($newTotal): ?><b class="nd"><?= $newTotal ?></b><?php endif; ?></a>
      <?php foreach ($VIEWS as $vk => $vd): $n = $counts[$vk]['by']['신규']; ?>
      <a href="?view=<?= $vk ?>" class="<?= $view === $vk ? 'on' : '' ?>"><i class="ico ico-<?= $vk ?>"></i><?= h($vd['label']) ?><?php if ($n): ?><b class="nd"><?= $n ?></b><?php endif; ?></a>
      <?php endforeach; ?>
      <div class="side-group">바로가기</div>
      <a href="/" target="_blank" rel="noopener"><i class="ico ico-site"></i>홈페이지 보기</a>
      <a href="?logout=1"><i class="ico ico-out"></i>로그아웃</a>
    </nav>
    <div class="side-disk">
      <div class="disk-row"><span>웹 용량</span><b class="disk-<?= $diskLevel ?>"><?= round($diskPct, 1) ?>%</b></div>
      <div class="disk-track"><div class="disk-fill disk-<?= $diskLevel ?>" style="width:<?= round($diskPct, 1) ?>%"></div></div>
      <div class="disk-sub"><?= fmt_bytes($diskUsed) ?> / 10 GB · 첨부 <?= fmt_bytes($uploadsUsed) ?></div>
    </div>
  </aside>
  <div class="side-backdrop" id="sideBackdrop"></div>

  <main class="main">
    <header class="topbar">
      <button class="menu-btn" id="menuBtn" type="button" aria-label="메뉴 열기" aria-controls="side" aria-expanded="false"><span></span><span></span><span></span></button>
      <div class="topbar-title"><?= h($pageTitle) ?></div>
      <span class="sp"></span>
      <?php if ($view !== 'dash'): ?>
      <form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="view" value="<?= h($view) ?>">
        <button class="btn btn-primary" type="submit" name="export" value="csv_all" data-confirm="<?= h($VIEWS[$view]['label']) ?> 전체 <?= (int)$counts[$view]['total'] ?>건을 CSV로 내려받을까요?">+ 전체 CSV 다운로드</button></form>
      <?php endif; ?>
    </header>

    <?php if (isset($_GET['deleted'])): ?><div class="flash" role="status"><?= (int)$_GET['deleted'] ?>건을 삭제했습니다.</div><?php endif; ?>

    <?php if ($view === 'dash'): ?>
    <!-- ================= 대시보드 ================= -->
    <section class="page-head">
      <p class="eyebrow">운영자 페이지</p>
      <h1>대시보드</h1>
      <p class="desc">오늘 처리할 접수를 한눈에 확인합니다. 숫자를 누르면 해당 목록으로 이동합니다.</p>
    </section>
    <section class="kpis kpis-3">
      <?php foreach ($VIEWS as $vk => $vd): $c = $counts[$vk]; ?>
      <a class="kpi kpi-link" href="?view=<?= $vk ?>&status=신규">
        <span class="kpi-label"><?= h($vd['label']) ?> · 신규</span>
        <b class="kpi-num <?= $c['by']['신규'] ? 'is-new' : '' ?>"><?= $c['by']['신규'] ?><small><?= $vd['unit'] ?></small></b>
        <span class="kpi-sub">전체 <?= number_format($c['total']) ?>건 · 이번 주 <?= $c['week'] ?>건 · 오늘 <?= $c['today'] ?>건</span>
      </a>
      <?php endforeach; ?>
    </section>
    <section class="dash-grid">
      <div class="card">
        <div class="card-head"><h2>최근 접수 <span class="muted">· 모든 채널</span></h2></div>
        <?php if (!$recent): ?><div class="empty">아직 접수된 내역이 없습니다.</div><?php else: ?>
        <div class="recent">
          <?php foreach ($recent as $r): $st = $r['status'] ?: '신규'; list($t1, $t2) = row_summary($r['_view'], $r); ?>
          <a class="recent-row" href="?view=<?= $r['_view'] ?>&open=<?= (int)$r['id'] ?>">
            <span class="tag tag-<?= $r['_view'] ?>"><?= h($VIEWS[$r['_view']]['label']) ?></span>
            <span class="recent-main"><b><?= h($r['name']) ?></b><?php $org = row_org($r['_view'], $r); if ($org): ?><span class="muted"> · <?= h($org) ?></span><?php endif; ?><small><?= h($t1) ?></small></span>
            <span class="pill p-<?= status_class($st) ?>"><i></i><?= h($st) ?></span>
            <span class="recent-time" title="<?= h($r['created_at']) ?>"><?= h(rel_time($r['created_at'])) ?></span>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="card">
        <div class="card-head"><h2>상태 분포</h2></div>
        <div class="dist">
          <?php foreach ($VIEWS as $vk => $vd): $c = $counts[$vk]; $tot = max(1, $c['total']); ?>
          <div class="dist-row">
            <div class="dist-head"><span><?= h($vd['label']) ?></span><span class="muted"><?= number_format($c['total']) ?>건</span></div>
            <div class="dist-bar"><?php foreach ($c['by'] as $s => $n): if ($n): ?><i class="seg-<?= status_class($s) ?>" style="width:<?= round($n / $tot * 100, 2) ?>%" title="<?= h($s) ?> <?= $n ?>건"></i><?php endif; endforeach; ?></div>
            <div class="dist-legend"><?php foreach ($c['by'] as $s => $n): ?><a href="?view=<?= $vk ?>&status=<?= urlencode($s) ?>"><i class="dot-<?= status_class($s) ?>"></i><?= h($s) ?> <b><?= $n ?></b></a><?php endforeach; ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <?php else: $vd = $VIEWS[$view]; $tbl = tbl_of_view($view); $opts = $STATUS[$tbl]; $c = $counts[$view]; ?>
    <!-- ================= 목록 ================= -->
    <section class="page-head">
      <p class="eyebrow">운영자 페이지</p>
      <h1><?= h($vd['label']) ?> 관리</h1>
      <p class="desc"><?= $view === 'app' ? '강사 지원서를 검토하고 상태·메모를 기록합니다.' : ($view === 'event' ? 'AX 고민 사연 이벤트 신청을 확인하고 선정·연락 이력을 기록합니다.' : '홈페이지로 들어온 교육 문의를 확인하고 답변 진행 상태를 관리합니다.') ?></p>
    </section>
    <section class="kpis">
      <?php foreach ($opts as $s): $n = $c['by'][$s] ?? 0; $on = $fStatus === $s; ?>
      <a class="kpi kpi-link <?= $on ? 'on' : '' ?>" href="<?= h(url_with(['status' => $on ? '' : $s, 'page' => ''])) ?>">
        <span class="kpi-label"><i class="dot-<?= status_class($s) ?>"></i><?= h($s) ?></span>
        <b class="kpi-num <?= ($s === '신규' && $n) ? 'is-new' : '' ?>"><?= $n ?><small>건</small></b>
      </a>
      <?php endforeach; ?>
      <div class="kpi"><span class="kpi-label">이번 주 접수</span><b class="kpi-num"><?= $c['week'] ?><small>건</small></b><span class="kpi-sub">오늘 <?= $c['today'] ?>건 · 전체 <?= number_format($c['total']) ?>건</span></div>
    </section>

    <section class="card list-card">
      <div class="card-head">
        <h2>전체 목록 <span class="muted">· <?= number_format($total) ?>건<?= ($q !== '' || $fStatus !== '' || in_array($period, ['7','30','90'], true)) ? ' (필터 적용)' : '' ?></span></h2>
        <form class="toolbar" method="get" id="filterForm">
          <input type="hidden" name="view" value="<?= h($view) ?>">
          <label class="search"><input type="search" name="q" value="<?= h($q) ?>" placeholder="이름, 연락처, 소속, 내용 검색" autocomplete="off"></label>
          <select name="status" class="sel" aria-label="상태"><option value="">모든 상태</option><?php foreach ($opts as $s): ?><option value="<?= h($s) ?>"<?= $fStatus === $s ? ' selected' : '' ?>><?= h($s) ?></option><?php endforeach; ?></select>
          <select name="period" class="sel" aria-label="기간"><option value="all">전체 기간</option><option value="7"<?= $period === '7' ? ' selected' : '' ?>>최근 7일</option><option value="30"<?= $period === '30' ? ' selected' : '' ?>>최근 30일</option><option value="90"<?= $period === '90' ? ' selected' : '' ?>>최근 90일</option></select>
          <select name="sort" class="sel" aria-label="정렬"><option value="new">최신순</option><option value="old"<?= $sort === 'old' ? ' selected' : '' ?>>오래된순</option></select>
          <?php if ($q !== '' || $fStatus !== '' || $period !== 'all' || $sort !== 'new'): ?><a class="btn btn-ghost btn-sm" href="?view=<?= h($view) ?>">초기화</a><?php endif; ?>
        </form>
      </div>

      <form method="post" id="bulkForm">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="view" value="<?= h($view) ?>">
        <?php if (!$rows): ?>
          <div class="empty"><?= $q !== '' || $fStatus !== '' ? '조건에 맞는 항목이 없습니다.' : '접수된 ' . h($vd['label']) . '이(가) 없습니다.' ?></div>
        <?php else: ?>
        <div class="tbl-wrap"><table class="tbl">
          <thead><tr>
            <th class="c-chk"><input type="checkbox" id="chkAll" aria-label="전체 선택"></th>
            <th class="c-who">접수번호 · <?= h($vd['who']) ?></th>
            <th class="c-sum">내용</th>
            <th class="c-st">진행 상태</th>
            <th class="c-time">접수</th>
            <th class="c-act">수정</th>
          </tr></thead>
          <tbody>
          <?php foreach ($rows as $r): $st = $r['status'] ?: '신규'; $cls = status_class($st); list($t1, $t2) = row_summary($view, $r); $org = row_org($view, $r); $mc = $memoCount[$r['id']] ?? 0;
            $hasFile = $view === 'app' ? !empty($r['profile_file']) : ($view === 'event' ? !empty($r['card_file']) : false); ?>
          <tr class="row <?= $st === '신규' ? 'is-new' : '' ?>" data-id="<?= (int)$r['id'] ?>" data-status="<?= h($st) ?>">
            <td class="c-chk" data-label=""><input type="checkbox" class="rowChk" name="ids[]" value="<?= (int)$r['id'] ?>" aria-label="선택"></td>
            <td class="c-who" data-label="접수번호"><a class="who" href="<?= h(url_with(['open' => (int)$r['id']])) ?>" data-open="<?= (int)$r['id'] ?>"><b>#<?= (int)$r['id'] ?> <?= h($r['name']) ?></b><small><?= h($org ?: '-') ?></small></a></td>
            <td class="c-sum" data-label="내용"><span class="sum"><b><?= h($t1) ?></b><small><?= h($t2) ?><?php if ($mc): ?> · 메모 <?= $mc ?><?php endif; ?><?php if ($hasFile): ?> · 첨부<?php endif; ?></small></span></td>
            <td class="c-st" data-label="상태">
              <span class="pill p-<?= $cls ?>"><i></i><?= h($st) ?></span>
              <select class="st-inline" aria-label="상태 변경" data-id="<?= (int)$r['id'] ?>"><?php foreach ($opts as $s): ?><option<?= $s === $st ? ' selected' : '' ?>><?= h($s) ?></option><?php endforeach; ?></select>
            </td>
            <td class="c-time" data-label="접수"><b><?= h(rel_time($r['created_at'])) ?></b><small><?= h(substr($r['created_at'], 0, 16)) ?></small></td>
            <td class="c-act"><button type="button" class="btn btn-ghost btn-sm" data-open="<?= (int)$r['id'] ?>">수정</button></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>

        <div class="list-foot">
          <div class="bulk" id="bulkBar" hidden>
            <b><span id="selCount">0</span>개 선택</b>
            <select id="bulkStatus" class="sel"><option value="">상태 일괄 변경…</option><?php foreach ($opts as $s): ?><option><?= h($s) ?></option><?php endforeach; ?></select>
            <button type="button" class="btn btn-primary btn-sm" id="bulkApply">적용</button>
            <button type="submit" class="btn btn-ghost btn-sm" name="export" value="csv_sel" data-confirm-sel="선택 항목을 CSV로 내려받을까요?">CSV</button>
            <?php if ($view === 'app'): ?><button type="submit" class="btn btn-ghost btn-sm" name="export" value="zip_sel" data-confirm-sel="선택 항목의 첨부파일을 ZIP으로 내려받을까요?">첨부 ZIP</button><?php endif; ?>
            <button type="submit" class="btn btn-danger btn-sm" name="bulk_delete" value="1" data-confirm-sel="선택한 항목을 영구 삭제합니다.&#10;첨부파일·메모도 함께 삭제되며 되돌릴 수 없습니다. 계속할까요?">삭제</button>
          </div>
          <div class="pager">
            <span class="muted"><?= number_format(($page - 1) * $PER + 1) ?>–<?= number_format(min($total, $page * $PER)) ?> / <?= number_format($total) ?></span>
            <a class="pg <?= $page <= 1 ? 'dis' : '' ?>" href="<?= h(url_with(['page' => max(1, $page - 1)])) ?>" aria-label="이전">‹</a>
            <?php $s0 = max(1, $page - 2); $s1 = min($pages, $s0 + 4); for ($i = $s0; $i <= $s1; $i++): ?><a class="pg <?= $i === $page ? 'on' : '' ?>" href="<?= h(url_with(['page' => $i])) ?>"><?= $i ?></a><?php endfor; ?>
            <a class="pg <?= $page >= $pages ? 'dis' : '' ?>" href="<?= h(url_with(['page' => min($pages, $page + 1)])) ?>" aria-label="다음">›</a>
          </div>
        </div>
        <?php endif; ?>
      </form>
    </section>
    <?php endif; ?>
  </main>
</div>

<!-- 우측 상세 패널 -->
<div class="drawer-backdrop" id="drawerBackdrop" hidden></div>
<aside class="drawer" id="drawer" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="drawerTitle" hidden>
  <header class="drawer-head">
    <div><p class="drawer-eyebrow" id="drawerEyebrow"></p><h2 id="drawerTitle">상세</h2></div>
    <button type="button" class="drawer-close" id="drawerClose" aria-label="닫기">✕</button>
  </header>
  <div class="drawer-body" id="drawerBody"><div class="empty">불러오는 중…</div></div>
</aside>
<div class="toast" id="toast" role="status" aria-live="polite" hidden></div>
<script>window.TWL = {view: <?= json_encode($view) ?>, csrf: <?= json_encode($CSRF) ?>, open: <?= (int)($_GET['open'] ?? 0) ?>};</script>
<script src="<?= h($assetBase) ?>/admin.js?v=<?= @filemtime($base . '/admin.js') ?: 1 ?>" defer></script>
</body></html>
