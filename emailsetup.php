<?php
require_once __DIR__ . '/includes/boot.php';
// Protect email configuration page.
cms_require_login();
include __DIR__ . '/includes/header-code.php';
include __DIR__ . '/includes/header.php';
?>
<div class="cms-shell">
  <?php include __DIR__ . '/includes/menu.php'; ?>
  <main class="cms-content">
    <div class="cms-content-header">
      <h1 class="h3 mb-0">Email Setup & Test</h1>
    </div>

    <?php
    $result = null;
    $testEmail = '';
    // Send a test email if requested.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $testEmail = trim($_POST['test_email'] ?? '');
      if ($testEmail !== '') {
        $subject = 'wITeCanvas CMS Test Email';
        $html = '<p>This is a test email from wITeCanvas CMS.</p>';
        $text = 'This is a test email from wITeCanvas CMS.';
        $sent = cms_send_mail($testEmail, $subject, $html, $text, 'cms', true);
        $result = $sent ? 'success' : 'fail';
      }
    }

    // Current SMTP configuration (from CMS prefs).
    $smtpHost = cms_pref('prefEmailSMTP', '', 'cms');
    $smtpPort = cms_pref('prefEmailSendPort', '', 'cms');
    $smtpUser = cms_pref('prefEmailSendFrom', '', 'cms');
    $smtpPass = cms_pref('prefEmailSendPassword', '', 'cms');
    $smtpSec = cms_pref('prefEmailSendSecurity', '', 'cms');
    $smtpBcc = cms_pref('prefEmailBCC', '', 'cms');
    ?>

    <?php if ($result === 'success'): ?>
      <div class="alert alert-success">Test email sent.</div>
    <?php elseif ($result === 'fail'): ?>
      <div class="alert alert-danger">Test email failed to send. Check SMTP settings and debug log below.</div>
    <?php endif; ?>

    <div class="cms-card mb-4">
      <h2 class="h5 mb-3">Current Parameters</h2>
      <dl class="cms-kv">
        <dt>SMTP Host</dt>
        <dd><?php echo cms_h($smtpHost); ?></dd>
        <dt>SMTP Port</dt>
        <dd><?php echo cms_h((string) $smtpPort); ?></dd>
        <dt>SMTP User</dt>
        <dd><?php echo cms_h($smtpUser); ?></dd>
        <dt>SMTP Password</dt>
        <dd><?php echo $smtpPass !== '' ? '••••••••' : ''; ?></dd>
        <dt>Security</dt>
        <dd><?php echo cms_h($smtpSec); ?></dd>
        <dt>BCC</dt>
        <dd><?php echo cms_h($smtpBcc); ?></dd>
      </dl>
    </div>

    <div class="cms-card mb-4">
      <h2 class="h5 mb-3">Send Test Email</h2>
      <form method="post">
        <div class="mb-3">
          <label class="form-label" for="test_email">Recipient</label>
          <input type="email" class="form-control" id="test_email" name="test_email" value="<?php echo cms_h($testEmail); ?>" required>
        </div>
        <button class="btn btn-primary" type="submit">Send Test</button>
      </form>
    </div>

    <div class="cms-card">
      <h2 class="h5 mb-3">SMTP Debug Log</h2>
      <?php
      $log = cms_smtp_last_log();
      if (empty($log)) {
        echo '<p class="mb-0 text-muted">No debug data yet.</p>';
      } else {
        echo '<pre class="mb-0" style="white-space:pre-wrap;">' . cms_h(implode("\n", $log)) . '</pre>';
      }
      ?>
    </div>
  </main>
</div>
<?php include __DIR__ . '/includes/footer-code.php'; ?>
