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
    // 서버측 필수 재검증 (클라이언트 우회 방지) — 스펙 §4.3/§4.4: 1~14번 필수
    foreach (['referral'=>'신청 경로','gender'=>'성별','region'=>'거주 지역','career_years'=>'경력 기간','prev_career'=>'주요 경력','intro'=>'소개','min_fee'=>'최소 섭외 비용'] as $k => $lbl) {
      if (!v_required($_POST[$k] ?? '')) fail($lbl . ' 항목을 입력해주세요.');
    }
    if (!v_required($birth) || !v_year($birth)) fail('출생년도는 4자리 숫자로 입력해주세요.');
    if (empty($_POST['fields'])) fail('가능한 강의 분야를 1개 이상 선택해주세요.');
    if (empty($_POST['client_types'])) fail('주요 출강 기관을 1개 이상 선택해주세요.');

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
