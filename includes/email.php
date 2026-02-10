<?php
// Optional per-server email credentials (kept private/out of repo).
if (file_exists(__DIR__ . '/../../../private/email.php')) {
  require_once __DIR__ . '/../../../private/email.php';
}
require_once __DIR__ . '/emailsig.php';

/**
 * Send a CMS email using configured SMTP (or PHP mail as a fallback).
 */
function cms_send_mail(string $to, string $subject, string $htmlBody, string $textBody = '', string $scope = 'cms', bool $debug = false): bool {
  global $MAIL_FROM, $MAIL_FROM_NAME, $MAIL_REPLY_TO, $MAIL_BCC;
  global $MAIL_SMTP_HOST, $MAIL_SMTP_PORT, $MAIL_SMTP_USER, $MAIL_SMTP_PASS, $MAIL_SMTP_SECURE;

  // Resolve sender and SMTP settings from prefs, with env fallbacks.
  $fromName = cms_pref('prefEmailSendFrom', $MAIL_FROM_NAME ?? 'wITeCanvas CMS', $scope);
  $from = cms_pref('prefEmailSendFrom', $MAIL_FROM ?? 'no-reply@localhost', $scope);
  $replyTo = cms_pref('prefEmailSendFrom', $MAIL_REPLY_TO ?? $from, $scope);
  $smtpHost = cms_pref('prefEmailSMTP', $MAIL_SMTP_HOST ?? '', $scope);
  $smtpPort = (int) cms_pref('prefEmailSendPort', $MAIL_SMTP_PORT ?? 587, $scope);
  $smtpUser = cms_pref('prefEmailSendFrom', $MAIL_SMTP_USER ?? '', $scope);
  $smtpPass = cms_pref('prefEmailSendPassword', $MAIL_SMTP_PASS ?? '', $scope);
  $smtpSecurity = cms_pref('prefEmailSendSecurity', $MAIL_SMTP_SECURE ?? 'tls', $scope);
  $bccRaw = cms_pref('prefEmailBCC', $MAIL_BCC ?? '', $scope);

  // Build multipart headers/body for HTML + plain text.
  $boundary = 'cms_' . bin2hex(random_bytes(12));
  $headers = [];
  $headers[] = 'MIME-Version: 1.0';
  $headers[] = 'To: ' . $to;
  $headers[] = 'From: ' . $fromName . ' <' . $from . '>';
  $headers[] = 'Reply-To: ' . $replyTo;
  if (!empty($bccRaw)) {
    $headers[] = 'Bcc: ' . $bccRaw;
  }
  $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

  $htmlBody .= cms_email_signature();
  $text = $textBody ?: strip_tags($htmlBody);

  $message = "--{$boundary}\r\n";
  $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
  $message .= $text . "\r\n";
  $message .= "--{$boundary}\r\n";
  $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
  $message .= $htmlBody . "\r\n";
  $message .= "--{$boundary}--";

  if (!empty($smtpHost)) {
    return cms_smtp_send($to, $subject, $message, implode("\r\n", $headers), $smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpSecurity, $debug);
  }

  // Fallback to PHP mail when SMTP is not configured.
  return mail($to, $subject, $message, implode("\r\n", $headers));
}

/**
 * Basic SMTP client for sending a single email message.
 */
function cms_smtp_send(string $to, string $subject, string $message, string $headers, string $host, int $port, string $user, string $pass, string $security, bool $debug = false): bool {
  $log = [];
  $security = strtolower(trim($security));
  if ($security === 'ssl' && $port !== 465) {
    $security = 'tls';
  }
  $transport = ($security === 'ssl') ? 'ssl://' : '';

  $socket = stream_socket_client(
    $transport . $host . ':' . (int) $port,
    $errno,
    $errstr,
    15,
    STREAM_CLIENT_CONNECT
  );

  if (!$socket) {
    if ($debug) {
      $log[] = 'CONNECT FAIL: ' . $errstr;
      $GLOBALS['CMS_SMTP_LAST_LOG'] = $log;
    }
    return false;
  }

  // Local helper to read a single SMTP response (multi-line safe).
  $read = function () use ($socket): string {
    $data = '';
    while (!feof($socket)) {
      $line = fgets($socket, 512);
      if ($line === false) {
        break;
      }
      $data .= $line;
      if (preg_match('/^\d{3} /', $line)) {
        break;
      }
    }
    return $data;
  };

  // Local helper to write a command and check for 2xx/3xx response.
  $write = function (string $cmd) use ($socket, $read, &$log, $debug): bool {
    fwrite($socket, $cmd . "\r\n");
    $resp = $read();
    if ($debug) {
      $log[] = 'C: ' . $cmd;
      $log[] = 'S: ' . trim($resp);
    }
    return (bool) preg_match('/^(2|3)\d{2}/', $resp);
  };

  $greeting = $read();
  if ($debug) {
    $log[] = 'S: ' . trim($greeting);
  }
  if (!$write('EHLO ' . ($host ?: 'localhost'))) {
    fclose($socket);
    if ($debug) {
      $GLOBALS['CMS_SMTP_LAST_LOG'] = $log;
    }
    return false;
  }

  if ($security === 'tls' || $security === 'starttls') {
    if (!$write('STARTTLS')) {
      fclose($socket);
      if ($debug) {
        $GLOBALS['CMS_SMTP_LAST_LOG'] = $log;
      }
      return false;
    }
    // Upgrade the socket to TLS before continuing SMTP conversation.
    stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    if (!$write('EHLO ' . ($host ?: 'localhost'))) {
      fclose($socket);
      if ($debug) {
        $GLOBALS['CMS_SMTP_LAST_LOG'] = $log;
      }
      return false;
    }
  }

  if (!empty($user)) {
    if (!$write('AUTH LOGIN')) {
      fclose($socket);
      if ($debug) {
        $GLOBALS['CMS_SMTP_LAST_LOG'] = $log;
      }
      return false;
    }
    if (!$write(base64_encode($user))) {
      fclose($socket);
      if ($debug) {
        $GLOBALS['CMS_SMTP_LAST_LOG'] = $log;
      }
      return false;
    }
    if (!$write(base64_encode($pass))) {
      fclose($socket);
      if ($debug) {
        $GLOBALS['CMS_SMTP_LAST_LOG'] = $log;
      }
      return false;
    }
  }

  // Use the From header value if provided; otherwise fall back to SMTP user.
  $fromMatch = '';
  if (preg_match('/From:\\s*[^<]*<([^>]+)>/i', $headers, $matches)) {
    $fromMatch = $matches[1];
  }
  $fromAddr = $fromMatch ?: $user;

  if (!$write('MAIL FROM:<' . $fromAddr . '>')) {
    fclose($socket);
    if ($debug) {
      $GLOBALS['CMS_SMTP_LAST_LOG'] = $log;
    }
    return false;
  }

  $recipients = [$to];
  if (preg_match('/Bcc:\\s*(.+)/i', $headers, $matches)) {
    $bccList = preg_split('/[;,]+/', $matches[1]);
    $bcc = array_filter(array_map('trim', $bccList));
    $recipients = array_merge($recipients, $bcc);
  }

  foreach ($recipients as $recipient) {
    if (!$write('RCPT TO:<' . $recipient . '>')) {
      fclose($socket);
      if ($debug) {
        $GLOBALS['CMS_SMTP_LAST_LOG'] = $log;
      }
      return false;
    }
  }

  if (!$write('DATA')) {
    fclose($socket);
    if ($debug) {
      $GLOBALS['CMS_SMTP_LAST_LOG'] = $log;
    }
    return false;
  }

  $data = "Subject: " . $subject . "\r\n" . $headers . "\r\nDate: " . gmdate('D, d M Y H:i:s O') . "\r\n\r\n" . $message . "\r\n.";
  fwrite($socket, $data . "\r\n");
  $dataResp = $read();
  if ($debug) {
    $log[] = 'S: ' . trim($dataResp);
  }
  if (!preg_match('/^2\\d{2}/', $dataResp)) {
    fclose($socket);
    if ($debug) {
      $GLOBALS['CMS_SMTP_LAST_LOG'] = $log;
    }
    return false;
  }
  $write('QUIT');
  fclose($socket);

  if ($debug) {
    $GLOBALS['CMS_SMTP_LAST_LOG'] = $log;
  }
  return true;
}

/**
 * Return the last SMTP debug log (if enabled during send).
 */
function cms_smtp_last_log(): array {
  return $GLOBALS['CMS_SMTP_LAST_LOG'] ?? [];
}
