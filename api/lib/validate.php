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
