<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/boot.php';
require_once __DIR__ . '/includes/lib/cms_frontend_deploy.php';
cms_require_login();

$hasDb = $DB_OK && ($pdo instanceof PDO);
$userRole = $hasDb ? cms_frontend_user_role($pdo, $CMS_USER) : 1;
$minRole = cms_frontend_min_role();
$authorized = $userRole >= $minRole;

if (!$authorized) {
  http_response_code(403);
  include __DIR__ . '/includes/header-code.php';
  include __DIR__ . '/includes/header.php';
  ?>
  <div class="cms-shell">
    <?php include __DIR__ . '/includes/menu.php'; ?>
    <main class="cms-content">
      <div class="cms-content-header">
        <h1 class="h3 mb-0">Frontend Deploy</h1>
      </div>
      <div class="alert alert-danger" role="alert">
        Access denied. This tool requires role <?php echo cms_h((string) $minRole); ?> or higher.
      </div>
    </main>
  </div>
  <?php
  include __DIR__ . '/includes/footer-code.php';
  exit;
}

if (empty($_SESSION['cms_frontend_csrf'])) {
  $_SESSION['cms_frontend_csrf'] = bin2hex(random_bytes(16));
}
$csrfToken = (string) $_SESSION['cms_frontend_csrf'];

$siteRoot = cms_frontend_detect_site_root();
$siteRootError = '';
if ($siteRoot === null) {
  $siteRootError = 'Unable to resolve a valid site root under /var/www with a web/ directory.';
}

$repoName = cms_frontend_repo_name();
$repoPath = $siteRoot ? cms_frontend_repo_path($siteRoot) : '';
$releaseScriptPath = $siteRoot ? cms_frontend_release_script_path($siteRoot) : '';

$message = '';
$error = '';
$checkOutput = '';
$checkExitCode = null;
$jobs = [];

$jobsTableOk = $hasDb && cms_frontend_jobs_table_exists($pdo);
if ($hasDb && !$jobsTableOk) {
  $error = 'Missing `cms_deploy_jobs` table. Run migrations first.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $postedToken = (string) ($_POST['csrf_token'] ?? '');
  if (!hash_equals($csrfToken, $postedToken)) {
    $error = 'Invalid request token. Refresh the page and try again.';
  } elseif (!$hasDb) {
    $error = 'Database unavailable.';
  } elseif (!$jobsTableOk) {
    $error = 'Deploy jobs table is not available.';
  } elseif ($siteRoot === null) {
    $error = $siteRootError;
  } else {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'queue_deploy') {
      try {
        $jobId = cms_frontend_queue_deploy_job($pdo, $siteRoot, (int) ($CMS_USER['id'] ?? 0));
        $message = 'Deploy job queued: #' . $jobId;
        cms_log_action('frontend_deploy_queue', 'cms_deploy_jobs', $jobId, null, 'tools-frontend', 'cms');
      } catch (Throwable $e) {
        $error = 'Unable to queue deploy job: ' . $e->getMessage();
      }
    } elseif ($action === 'check_status') {
      $result = cms_frontend_run_release($releaseScriptPath, 'check', [
        'FRONTEND_REPO' => $repoPath,
        'FRONTEND_REPO_NAME' => $repoName,
      ]);
      $checkExitCode = (int) ($result['exit_code'] ?? 1);
      $checkOutput = (string) ($result['output'] ?? '');

      if ($checkExitCode === 125) {
        $latest = cms_frontend_get_latest_job($pdo, $siteRoot);
        if ($latest) {
          $message = 'Runtime execution is disabled; showing latest worker job status instead.';
          $checkOutput = (string) ($latest['output_text'] ?? '');
          $checkExitCode = isset($latest['exit_code']) ? (int) $latest['exit_code'] : null;
        } else {
          $error = 'Runtime execution is disabled and no previous jobs were found.';
        }
      } elseif ($checkExitCode === 0) {
        $message = 'Frontend status check completed successfully.';
      } else {
        $error = 'Frontend status check failed (exit ' . $checkExitCode . ').';
      }

      cms_log_action('frontend_deploy_check', 'cms_deploy_jobs', null, null, 'tools-frontend', 'cms');
    }
  }
}

if ($hasDb && $jobsTableOk && $siteRoot !== null) {
  try {
    $jobs = cms_frontend_list_jobs($pdo, $siteRoot, 20);
  } catch (Throwable $e) {
    if ($error === '') {
      $error = 'Failed to load job history: ' . $e->getMessage();
    }
  }
}

include __DIR__ . '/includes/header-code.php';
include __DIR__ . '/includes/header.php';
?>
<div class="cms-shell">
  <?php include __DIR__ . '/includes/menu.php'; ?>
  <main class="cms-content">
    <div class="cms-content-header">
      <h1 class="h3 mb-0">Frontend Deploy</h1>
    </div>

    <?php if ($message !== ''): ?>
      <div class="alert alert-success" role="alert"><?php echo cms_h($message); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
      <div class="alert alert-danger" role="alert"><?php echo cms_h($error); ?></div>
    <?php endif; ?>

    <div class="cms-card mb-4">
      <h2 class="h5">Site Context</h2>
      <dl class="cms-kv">
        <dt>Current site root</dt>
        <dd><code><?php echo cms_h($siteRoot ?? 'unresolved'); ?></code></dd>
        <dt>Detected frontend repo path</dt>
        <dd><code><?php echo cms_h($repoPath !== '' ? $repoPath : 'unresolved'); ?></code></dd>
        <dt>Frontend repo name</dt>
        <dd><code><?php echo cms_h($repoName); ?></code></dd>
        <dt>Release script</dt>
        <dd><code><?php echo cms_h($releaseScriptPath !== '' ? $releaseScriptPath : 'unresolved'); ?></code></dd>
        <dt>Required role</dt>
        <dd><?php echo cms_h((string) $minRole); ?>+</dd>
      </dl>
    </div>

    <div class="cms-card mb-4">
      <h2 class="h5">Actions</h2>
      <div class="d-flex gap-2 flex-wrap">
        <form method="post" action="">
          <input type="hidden" name="csrf_token" value="<?php echo cms_h($csrfToken); ?>">
          <input type="hidden" name="action" value="check_status">
          <button class="btn btn-outline-primary" type="submit"<?php echo $siteRoot === null ? ' disabled' : ''; ?>>Check Status</button>
        </form>
        <form method="post" action="">
          <input type="hidden" name="csrf_token" value="<?php echo cms_h($csrfToken); ?>">
          <input type="hidden" name="action" value="queue_deploy">
          <button class="btn btn-primary" type="submit"<?php echo $siteRoot === null ? ' disabled' : ''; ?>>Queue Deploy</button>
        </form>
      </div>
      <p class="text-muted small mt-3 mb-0">Deploys are queued only. Worker/cron executes queued jobs outside web requests.</p>
    </div>

    <?php if ($checkOutput !== ''): ?>
      <div class="cms-card mb-4">
        <h2 class="h5">Latest Check Output<?php echo $checkExitCode !== null ? ' (exit ' . cms_h((string) $checkExitCode) . ')' : ''; ?></h2>
        <pre class="mb-0" style="white-space: pre-wrap;"><?php echo cms_h($checkOutput); ?></pre>
      </div>
    <?php endif; ?>

    <div class="cms-card">
      <h2 class="h5">Recent Jobs (Last 20)</h2>
      <?php if (!$jobs): ?>
        <p class="mb-0 text-muted">No jobs recorded yet.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th>ID</th>
                <th>Status</th>
                <th>Requested By</th>
                <th>Requested At</th>
                <th>Started</th>
                <th>Finished</th>
                <th>Exit</th>
                <th>Output</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($jobs as $job): ?>
                <?php
                  $status = (string) ($job['status'] ?? 'unknown');
                  $badgeClass = 'secondary';
                  if ($status === 'queued') {
                    $badgeClass = 'warning text-dark';
                  } elseif ($status === 'running') {
                    $badgeClass = 'info text-dark';
                  } elseif ($status === 'success') {
                    $badgeClass = 'success';
                  } elseif ($status === 'failed') {
                    $badgeClass = 'danger';
                  }
                  $requestedBy = (string) ($job['username'] ?? 'user#' . (string) ($job['requested_by'] ?? '')); 
                  $output = trim((string) ($job['output_text'] ?? ''));
                ?>
                <tr>
                  <td><?php echo cms_h((string) $job['id']); ?></td>
                  <td><span class="badge bg-<?php echo cms_h($badgeClass); ?>"><?php echo cms_h($status); ?></span></td>
                  <td><?php echo cms_h($requestedBy); ?></td>
                  <td><?php echo cms_h((string) ($job['requested_at'] ?? '')); ?></td>
                  <td><?php echo cms_h((string) ($job['started_at'] ?? '')); ?></td>
                  <td><?php echo cms_h((string) ($job['finished_at'] ?? '')); ?></td>
                  <td><?php echo isset($job['exit_code']) ? cms_h((string) $job['exit_code']) : '-'; ?></td>
                  <td>
                    <?php if ($output !== ''): ?>
                      <details>
                        <summary>View</summary>
                        <pre style="white-space: pre-wrap;"><?php echo cms_h($output); ?></pre>
                      </details>
                    <?php else: ?>
                      -
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>
<?php include __DIR__ . '/includes/footer-code.php'; ?>
