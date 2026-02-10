<?php
require_once __DIR__ . '/includes/boot.php';

// Redirect already-authenticated users.
if (cms_is_logged_in()) {
  header('Location: ' . $CMS_BASE_URL . '/dashboard.php');
  exit;
}

// Handle login submission.
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $identifier = trim($_POST['identifier'] ?? '');
  $password = $_POST['password'] ?? '';

  if ($identifier === '' || $password === '') {
    $error = 'Please enter your login and password.';
  } elseif (cms_login($identifier, $password, $error)) {
    header('Location: ' . $CMS_BASE_URL . '/dashboard.php');
    exit;
  }
}

// One-time flash message (e.g., password reset confirmation).
$flash = $_SESSION['cms_flash'] ?? null;
if ($flash) {
  unset($_SESSION['cms_flash']);
}

include __DIR__ . '/includes/header-code.php';
?>
<div class="cms-login">
  <div class="cms-login-card">
    <img src="<?php echo $baseURL; ?>/filestore/images/logos/witecanvas-logo-l.png" alt="wITeCanvas" class="mb-3" style="height:32px;">
    <h1>Sign in</h1>
    <p class="text-muted">Access the wITeCanvas CMS.</p>
    <?php if ($error): ?>
      <div class="alert alert-danger" role="alert">
        <?php echo cms_h($error); ?>
      </div>
    <?php endif; ?>
    <?php if ($flash): ?>
      <div class="alert alert-success" role="alert">
        <?php echo cms_h($flash); ?>
      </div>
    <?php endif; ?>
    <form method="post" novalidate>
      <div class="mb-3">
        <label class="form-label" for="identifier">Email or Username</label>
        <input type="text" class="form-control" id="identifier" name="identifier" autocomplete="username" required>
      </div>
      <div class="mb-3">
        <label class="form-label" for="password">Password</label>
        <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
      </div>
      <button class="btn btn-primary w-100" type="submit">Login</button>
    </form>
    <div class="cms-footer-links mt-3">
      <a href="<?php echo $CMS_BASE_URL; ?>/forgot-password.php">Forgot password?</a>
      <a href="<?php echo $baseURL; ?>/index.php" target="_blank" rel="noopener">Go to site</a>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer-code.php'; ?>
