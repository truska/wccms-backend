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
        <h1 class="h3 mb-0">Deploy Tools</h1>
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
  $fallbackRoot = realpath(dirname(__DIR__, 2));
  if (is_string($fallbackRoot) && $fallbackRoot !== '' && cms_frontend_is_allowed_site_root($fallbackRoot)) {
    $siteRoot = $fallbackRoot;
  } else {
    $siteRootError = 'Unable to resolve a valid site root under /var/www with a web/ directory.';
  }
}

$repoName = cms_frontend_repo_name();
$repoPath = $siteRoot ? cms_frontend_repo_path($siteRoot) : '';
$releaseScriptPath = $siteRoot ? cms_frontend_release_script_path($siteRoot) : '';
$backendRepoPath = $siteRoot ? cms_frontend_backend_repo_path($siteRoot) : '';

$message = '';
$error = '';
$outputText = '';
$outputExitCode = null;
$outputTitle = '';
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

    if ($action === 'run_frontend_deploy') {
      try {
        $jobId = cms_frontend_start_job($pdo, $siteRoot, 'frontend_deploy', (int) ($CMS_USER['id'] ?? 0));
        $result = cms_frontend_run_release($releaseScriptPath, 'deploy', [
          'FRONTEND_REPO' => $repoPath,
          'FRONTEND_REPO_NAME' => $repoName,
        ]);
        $outputExitCode = (int) ($result['exit_code'] ?? 1);
        $outputText = (string) ($result['output'] ?? '');
        $outputTitle = 'Frontend Deploy Output';
        $status = ($outputExitCode === 0) ? 'success' : 'failed';
        cms_frontend_finish_job($pdo, $jobId, $status, $outputExitCode, $outputText);
        if ($status === 'success') {
          $message = 'Frontend deploy completed (job #' . $jobId . ').';
        } else {
          $error = 'Frontend deploy failed (job #' . $jobId . ', exit ' . $outputExitCode . ').';
        }
        cms_log_action('frontend_deploy_run', 'cms_deploy_jobs', $jobId, null, 'tools-frontend', 'cms');
      } catch (Throwable $e) {
        $error = 'Unable to run frontend deploy: ' . $e->getMessage();
      }
    } elseif ($action === 'run_backend_deploy') {
      try {
        $jobId = cms_frontend_start_job($pdo, $siteRoot, 'backend_deploy', (int) ($CMS_USER['id'] ?? 0));
        $result = cms_frontend_run_backend_deploy($siteRoot);
        $outputExitCode = (int) ($result['exit_code'] ?? 1);
        $outputText = (string) ($result['output'] ?? '');
        $outputTitle = 'Backend Deploy Output';
        $status = ($outputExitCode === 0) ? 'success' : 'failed';
        cms_frontend_finish_job($pdo, $jobId, $status, $outputExitCode, $outputText);
        if ($status === 'success') {
          $message = 'Backend deploy completed (job #' . $jobId . ').';
        } else {
          $error = 'Backend deploy failed (job #' . $jobId . ', exit ' . $outputExitCode . ').';
        }
        cms_log_action('backend_deploy_run', 'cms_deploy_jobs', $jobId, null, 'tools-frontend', 'cms');
      } catch (Throwable $e) {
        $error = 'Unable to run backend deploy: ' . $e->getMessage();
      }
    } elseif ($action === 'check_status') {
      $result = cms_frontend_run_release($releaseScriptPath, 'check', [
        'FRONTEND_REPO' => $repoPath,
        'FRONTEND_REPO_NAME' => $repoName,
      ]);
      $outputExitCode = (int) ($result['exit_code'] ?? 1);
      $outputText = (string) ($result['output'] ?? '');
      $outputTitle = 'Frontend Status Check Output';

      if ($outputExitCode === 0) {
        $message = 'Frontend status check completed successfully.';
      } else {
        $error = 'Frontend status check failed (exit ' . $outputExitCode . ').';
      }

      cms_log_action('frontend_deploy_check', 'cms_deploy_jobs', null, null, 'tools-frontend', 'cms');
    }
  }
}

if ($hasDb && $jobsTableOk && $siteRoot !== null) {
  try {
    $jobs = cms_frontend_list_jobs($pdo, $siteRoot, 20, null);
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
      <h1 class="h3 mb-0">Deploy Tools</h1>
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
        <dt>Backend repo path</dt>
        <dd><code><?php echo cms_h($backendRepoPath !== '' ? $backendRepoPath : 'unresolved'); ?></code></dd>
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
          <input type="hidden" name="action" value="run_frontend_deploy">
          <button class="btn btn-primary" type="submit"<?php echo $siteRoot === null ? ' disabled' : ''; ?>>Run Frontend Deploy Now</button>
        </form>
        <form method="post" action="">
          <input type="hidden" name="csrf_token" value="<?php echo cms_h($csrfToken); ?>">
          <input type="hidden" name="action" value="run_backend_deploy">
          <button class="btn btn-outline-secondary" type="submit"<?php echo $siteRoot === null ? ' disabled' : ''; ?>>Run Backend Deploy Now</button>
        </form>
      </div>
      <p class="text-muted small mt-3 mb-0">Deploy runs immediately in this request and is logged in `cms_deploy_jobs`.</p>
    </div>

    <?php if ($outputText !== ''): ?>
      <div class="cms-card mb-4">
        <h2 class="h5"><?php echo cms_h($outputTitle !== '' ? $outputTitle : 'Command Output'); ?><?php echo $outputExitCode !== null ? ' (exit ' . cms_h((string) $outputExitCode) . ')' : ''; ?></h2>
        <pre class="mb-0" style="white-space: pre-wrap;"><?php echo cms_h($outputText); ?></pre>
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
                <th>Type</th>
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
                  <td><?php echo cms_h((string) ($job['job_type'] ?? '')); ?></td>
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
