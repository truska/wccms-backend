#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../private/dbcon.php';
require_once __DIR__ . '/../includes/lib/cms_prefs.php';
require_once __DIR__ . '/../includes/lib/cms_frontend_deploy.php';

if (!$DB_OK || !($pdo instanceof PDO)) {
  fwrite(STDERR, "[worker] Database unavailable.\n");
  exit(2);
}

if (!cms_frontend_jobs_table_exists($pdo)) {
  fwrite(STDERR, "[worker] Missing cms_deploy_jobs table. Run migrations first.\n");
  exit(2);
}

$options = getopt('', ['max-jobs::', 'stale-minutes::', 'site-root::']);
$maxJobs = isset($options['max-jobs']) ? (int) $options['max-jobs'] : 5;
$maxJobs = max(1, min(100, $maxJobs));
$staleMinutes = isset($options['stale-minutes']) ? (int) $options['stale-minutes'] : 120;
$staleMinutes = max(5, min(1440, $staleMinutes));
$siteRootFilter = isset($options['site-root']) ? trim((string) $options['site-root']) : null;

if ($siteRootFilter !== null && $siteRootFilter !== '' && !cms_frontend_is_allowed_site_root($siteRootFilter)) {
  fwrite(STDERR, "[worker] Refusing invalid site root filter: {$siteRootFilter}\n");
  exit(2);
}

$staleCount = cms_frontend_fail_stale_running_jobs($pdo, $staleMinutes);
if ($staleCount > 0) {
  fwrite(STDOUT, "[worker] Marked stale running jobs failed: {$staleCount}\n");
}

$processed = 0;
while ($processed < $maxJobs) {
  $job = cms_frontend_claim_next_job($pdo, $siteRootFilter);
  if (!$job) {
    break;
  }

  $jobId = (int) ($job['id'] ?? 0);
  $siteRoot = trim((string) ($job['site_root'] ?? ''));
  fwrite(STDOUT, "[worker] Running job #{$jobId} for {$siteRoot}\n");

  if (!cms_frontend_is_allowed_site_root($siteRoot)) {
    cms_frontend_finish_job(
      $pdo,
      $jobId,
      'failed',
      2,
      '[worker] Invalid or disallowed site_root: ' . $siteRoot . "\nAllowed roots must be canonical /var/www/* with a web/ directory."
    );
    $processed++;
    continue;
  }

  $scriptPath = cms_frontend_release_script_path($siteRoot);
  $repoName = cms_frontend_repo_name();
  $repoPath = cms_frontend_repo_path($siteRoot);
  $result = cms_frontend_run_release($scriptPath, 'deploy', [
    'FRONTEND_REPO' => $repoPath,
    'FRONTEND_REPO_NAME' => $repoName,
  ]);
  $exitCode = (int) ($result['exit_code'] ?? 1);
  $output = trim((string) ($result['output'] ?? ''));
  $status = ($exitCode === 0) ? 'success' : 'failed';

  cms_frontend_finish_job($pdo, $jobId, $status, $exitCode, $output);
  fwrite(STDOUT, "[worker] Job #{$jobId} finished with status={$status} exit={$exitCode}\n");

  $processed++;
}

fwrite(STDOUT, "[worker] Done. Jobs processed: {$processed}\n");
exit(0);
