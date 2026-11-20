<?php
// 자리표시자. 실제 값은 서버의 api/config.php 에만 있고 저장소에 넣지 않는다.
return [
  'db' => [
    'host' => '',
    'name' => '',
    'user' => '',
    'pass' => '',
    'charset' => 'utf8mb4',
  ],
  'mail_to'   => '',       // 접수 알림 받을 주소
  'mail_from' => '',       // 발신 표기(호스팅 도메인 권장)
  'upload_dir' => '',                           // 비우면 api/uploads/ 사용
  'max_upload_bytes' => 10 * 1024 * 1024,       // 10MB
  'allowed_ext' => ['pdf','ppt','pptx','doc','docx','jpg','jpeg','png'],
  'admin_user' => '',
  'admin_pass' => '',
];
