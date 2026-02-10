<?php
require_once __DIR__ . '/includes/boot.php';

$message = null;
$error = null;
$link = null;

// Handle reset request form submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  if ($email === '') {
    $error = 'Please enter your email.';
  } elseif (!$DB_OK || !($pdo instanceof PDO)) {
    $error = 'Database unavailable.';
  } else {
    // Confirm the user exists and is active.
    $stmt = $pdo->prepare("SELECT id FROM cms_users WHERE username = :email AND archived = 0 AND showonweb = 'Yes' LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
      // Create a one-time token that expires after 1 hour.
      $token = bin2hex(random_bytes(32));
      $tokenHash = hash('sha256', $token);
      $expires = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

      $sql = 'INSERT INTO cms_password_resets (name, user_id, token_hash, expires_at, request_ip) VALUES (:name, :user_id, :token_hash, :expires_at, :request_ip)';
      $pdo->prepare($sql)
        ->execute([
          ':name' => 'reset',
          ':user_id' => $user['id'],
          ':token_hash' => $tokenHash,
          ':expires_at' => $expires,
          ':request_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
      cms_log_action('password_reset_request', 'cms_password_resets', null, $sql, 'forgot-password', 'cms');

      // Build reset link and send email.
      $link = $CMS_BASE_URL . '/reset-password.php?token=' . urlencode($token);
      $message = 'Password reset link generated.';

      $subject = 'Reset your wITeCanvas CMS password';
      $safeLink = cms_h($link);
      $html = '<p>Hi,</p>'
        . '<p>Use the link below to reset your password:</p>'
        . '<p><a href="' . $safeLink . '">' . $safeLink . '</a></p>'
        . '<p>This link expires in 1 hour.</p>';
      $text = "Use the link below to reset your password:\n" . $link . "\n\nThis link expires in 1 hour.";
      cms_send_mail($email, $subject, $html, $text, 'cms');
    } else {
      $message = 'If that email exists, a reset link has been generated.';
    }
  }
}

include __DIR__ . '/includes/header-code.php';
?>
<div class="cms-login">
  <div class="cms-login-card">
    <img src="<?php echo $baseURL; ?>/filestore/images/logos/witecanvas-logo-l.png" alt="wITeCanvas" class="mb-3" style="height:32px;">
    <h1>Password Reset</h1>
    <p class="text-muted">Enter your email to reset your password.</p>

    <?php if ($error): ?>
      <div class="alert alert-danger" role="alert"><?php echo cms_h($error); ?></div>
    <?php endif; ?>

    <?php if ($message): ?>
      <div class="alert alert-success" role="alert"><?php echo cms_h($message); ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <div class="mb-3">
        <label class="form-label" for="email">Email</label>
        <input type="email" class="form-control" id="email" name="email" autocomplete="email" required>
      </div>
      <button class="btn btn-primary w-100" type="submit">Generate reset link</button>
    </form>
    <div class="cms-footer-links mt-3">
      <a href="<?php echo $CMS_BASE_URL; ?>/login.php">Back to login</a>
      <a href="<?php echo $baseURL; ?>/index.php" target="_blank" rel="noopener">Go to site</a>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer-code.php'; ?>
