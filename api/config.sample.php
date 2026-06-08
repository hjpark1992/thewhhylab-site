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
