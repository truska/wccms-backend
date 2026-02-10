<?php
require_once __DIR__ . '/includes/boot.php';

$token = $_GET['token'] ?? '';
$error = null;
$message = null;
$tokenValid = false;
$userId = null;
$redirect = false;

// Validate token before showing the reset form.
if ($token && $DB_OK && ($pdo instanceof PDO)) {
  $tokenHash = hash('sha256', $token);
  $stmt = $pdo->prepare("SELECT user_id FROM cms_password_resets WHERE token_hash = :token_hash AND expires_at > NOW() AND used_at IS NULL AND archived = 0 AND showonweb = 'Yes' ORDER BY id DESC LIMIT 1");
  $stmt->execute([':token_hash' => $tokenHash]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($row) {
    $tokenValid = true;
    $userId = (int) $row['user_id'];
  }
}

// Handle password reset submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['token'] ?? '';
  $password = $_POST['password'] ?? '';
  $confirm = $_POST['confirm_password'] ?? '';

  if ($password === '' || $confirm === '') {
    $error = 'Please enter a new password.';
  } elseif ($password !== $confirm) {
    $error = 'Passwords do not match.';
  } elseif (!$DB_OK || !($pdo instanceof PDO)) {
    $error = 'Database unavailable.';
  } else {
    $tokenHash = hash('sha256', $token);
    $stmt = $pdo->prepare("SELECT user_id FROM cms_password_resets WHERE token_hash = :token_hash AND expires_at > NOW() AND used_at IS NULL AND archived = 0 AND showonweb = 'Yes' ORDER BY id DESC LIMIT 1");
    $stmt->execute([':token_hash' => $tokenHash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
      $error = 'Reset link is invalid or expired.';
    } else {
      // Update user password and mark reset token as used.
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $sqlUser = 'UPDATE cms_users SET password = :hash WHERE id = :id';
      $pdo->prepare($sqlUser)->execute([
        ':hash' => $hash,
        ':id' => $row['user_id'],
      ]);
      cms_log_action('password_reset_update', 'cms_users', (int) $row['user_id'], $sqlUser, 'reset-password', 'cms');

      $sqlReset = "UPDATE cms_password_resets SET used_at = NOW(), modified = NOW(), showonweb = 'No' WHERE token_hash = :token_hash";
      $pdo->prepare($sqlReset)->execute([
        ':token_hash' => $tokenHash,
      ]);
      cms_log_action('password_reset_complete', 'cms_password_resets', null, $sqlReset, 'reset-password', 'cms');
      $message = 'Password updated. Redirecting to login...';
      $tokenValid = false;
      $redirect = true;
    }
  }
}

include __DIR__ . '/includes/header-code.php';
?>
<div class="cms-login">
  <div class="cms-login-card">
    <img src="<?php echo $baseURL; ?>/filestore/images/logos/witecanvas-logo-l.png" alt="wITeCanvas" class="mb-3" style="height:32px;">
    <h1>Set new password</h1>

    <?php if ($error): ?>
      <div class="alert alert-danger" role="alert"><?php echo cms_h($error); ?></div>
    <?php endif; ?>

    <?php if ($message): ?>
      <div class="alert alert-success" role="alert"><?php echo cms_h($message); ?></div>
    <?php endif; ?>

    <?php if ($message): ?>
      <div class="alert alert-success" role="alert"><?php echo cms_h($message); ?></div>
    <?php endif; ?>

    <?php if ($redirect): ?>
      <meta http-equiv="refresh" content="5;url=<?php echo $CMS_BASE_URL; ?>/login.php?reset=1">
      <script>
        setTimeout(function () {
          window.location.href = "<?php echo $CMS_BASE_URL; ?>/login.php?reset=1";
        }, 5000);
      </script>
    <?php endif; ?>

    <?php if ($tokenValid && !$redirect): ?>
      <form method="post" novalidate>
        <input type="hidden" name="token" value="<?php echo cms_h($token); ?>">
        <div class="mb-3">
          <label class="form-label" for="password">New Password</label>
          <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <div class="mb-3">
          <label class="form-label" for="confirm_password">Confirm Password</label>
          <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
        </div>
        <button class="btn btn-primary w-100" type="submit">Update password</button>
      </form>
    <?php elseif (!$redirect): ?>
      <p class="text-muted">The reset link is invalid or has expired.</p>
      <a class="btn btn-outline-secondary w-100" href="<?php echo $CMS_BASE_URL; ?>/forgot-password.php">Request new link</a>
    <?php endif; ?>

    <div class="cms-footer-links mt-3">
      <a href="<?php echo $CMS_BASE_URL; ?>/login.php">Back to login</a>
      <a href="<?php echo $baseURL; ?>/index.php" target="_blank" rel="noopener">Go to site</a>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer-code.php'; ?>
