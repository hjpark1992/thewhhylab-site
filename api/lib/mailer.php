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
