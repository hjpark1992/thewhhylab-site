<?php
/**
 * inquiries 테이블에 AX 이벤트 신청용 컬럼을 보장(없으면 추가).
 * submit.php(event 접수)와 admin.php(조회)에서 호출 — 수동 ALTER 누락 시 안전망.
 * SHOW COLUMNS로 이미 있으면 건너뛰므로 반복 호출해도 안전.
 */
function ensure_event_columns(PDO $pdo): void {
  static $done = false;
  if ($done) return;
  $done = true;
  try { $have = $pdo->query('SHOW COLUMNS FROM `inquiries`')->fetchAll(PDO::FETCH_COLUMN); }
  catch (Throwable $e) { return; } // 테이블 자체가 없으면(신규 설치 전) 아무것도 안 함
  $cols = [
    'kind'             => "VARCHAR(20) NOT NULL DEFAULT 'contact'",
    'company'          => 'VARCHAR(255) NULL',
    'dept'             => 'VARCHAR(255) NULL',
    'job_title'        => 'VARCHAR(120) NULL',
    'role_work'        => 'VARCHAR(500) NULL',
    'org_size'         => 'VARCHAR(60) NULL',
    'interests'        => 'VARCHAR(500) NULL',
    'story'            => 'TEXT NULL',
    'card_file'        => 'VARCHAR(255) NULL',
    'card_original'    => 'VARCHAR(255) NULL',
    'consult_wanted'   => 'TINYINT(1) NOT NULL DEFAULT 0',
    'marketing_agreed' => 'TINYINT(1) NOT NULL DEFAULT 0',
  ];
  foreach ($cols as $name => $type) {
    if (!in_array($name, $have, true)) {
      try { $pdo->exec("ALTER TABLE `inquiries` ADD COLUMN `{$name}` {$type}"); } catch (Throwable $e) {}
    }
  }
}
