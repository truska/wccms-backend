<?php
declare(strict_types=1);

/**
 * Resolve numeric role for current CMS user.
 */
function cms_frontend_user_role(PDO $pdo, array $cmsUser): int {
  $userId = (int) ($cmsUser['id'] ?? 0);
  if ($userId <= 0) {
    return 1;
  }

  try {
    $stmt = $pdo->prepare('SELECT userrole FROM cms_users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $roleValue = $stmt->fetchColumn();
    if ($roleValue !== false && $roleValue !== null) {
      return max(1, (int) $roleValue);
    }
  } catch (Throwable $e) {
    return 1;
  }

  return 1;
}

/**
 * Role required for frontend deploy tools (default 4).
 */
function cms_frontend_min_role(): int {
  $minRole = (int) cms_pref('prefFrontendDeployMinRole', 4, 'cms');
  if ($minRole < 1) {
    return 4;
  }
  return $minRole;
}

/**
 * Canonical site root must be under /var/www and contain a web/ directory.
 */
function cms_frontend_is_allowed_site_root(string $siteRoot): bool {
  $realRoot = realpath($siteRoot);
  if ($realRoot === false || !is_dir($realRoot)) {
    return false;
  }

  if (!str_starts_with($realRoot, '/var/www/')) {
    return false;
  }

  $webPath = realpath($realRoot . '/web');
  if ($webPath === false || !is_dir($webPath)) {
    return false;
  }

  if (dirname($webPath) !== $realRoot) {
    return false;
  }

  $isClientStyle = (bool) preg_match('#^/var/www/clients/[^/]+/[^/]+$#', $realRoot);
  $isSiteStyle = (bool) preg_match('#^/var/www/[^/]+$#', $realRoot);
  return $isClientStyle || $isSiteStyle;
}

/**
 * Detect current site root from runtime hints and filesystem location.
 */
function cms_frontend_detect_site_root(): ?string {
  $candidates = [];

  $documentRoot = trim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
  if ($documentRoot !== '') {
    $candidates[] = $documentRoot;
    $candidates[] = dirname($documentRoot);
  }

  $scriptFilename = trim((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
  if ($scriptFilename !== '') {
    $candidates[] = dirname($scriptFilename);
  }

  $candidates[] = dirname(__DIR__, 4);
  $candidates[] = getcwd() ?: '';

  foreach ($candidates as $candidate) {
    if ($candidate === '') {
      continue;
    }

    $candidate = rtrim($candidate, '/');
    if ($candidate === '') {
      continue;
    }

    $root = $candidate;
    if (basename($candidate) === 'web') {
      $root = dirname($candidate);
    }

    $realRoot = realpath($root);
    if ($realRoot === false) {
      continue;
    }

    if (cms_frontend_is_allowed_site_root($realRoot)) {
      return $realRoot;
    }
  }

  return null;
}

function cms_frontend_release_script_path(string $siteRoot): string {
  return rtrim($siteRoot, '/') . '/web/wccms/deploy_scripts/frontend-release.sh';
}

function cms_frontend_repo_name(): string {
  $name = trim((string) cms_pref('prefFrontendDeployRepoName', 'frontend', 'cms'));
  if ($name === '') {
    return 'frontend';
  }

  $name = basename($name);
  if (!preg_match('/^[A-Za-z0-9._-]+$/', $name)) {
    return 'frontend';
  }

  return $name;
}

function cms_frontend_repo_path(string $siteRoot): string {
  $configured = trim((string) cms_pref('prefFrontendDeployRepoPath', '', 'cms'));
  if ($configured !== '') {
    if (str_starts_with($configured, '/')) {
      return rtrim($configured, '/');
    }
    return rtrim($siteRoot, '/') . '/' . ltrim($configured, '/');
  }

  return rtrim($siteRoot, '/') . '/' . cms_frontend_repo_name();
}

/**
 * Detect whether command execution functions are available.
 */
function cms_frontend_can_exec(): bool {
  if (!function_exists('proc_open')) {
    return false;
  }

  $disabled = (string) ini_get('disable_functions');
  if ($disabled === '') {
    return true;
  }

  $disabledList = array_map('trim', explode(',', $disabled));
  return !in_array('proc_open', $disabledList, true);
}

/**
 * Run frontend release script in check/deploy mode and capture output.
 */
function cms_frontend_run_release(string $scriptPath, string $mode, array $extraEnv = []): array {
  if (!in_array($mode, ['check', 'deploy'], true)) {
    throw new InvalidArgumentException('Invalid release command mode.');
  }

  $scriptReal = realpath($scriptPath);
  if ($scriptReal === false || !is_file($scriptReal)) {
    return [
      'exit_code' => 127,
      'stdout' => '',
      'stderr' => 'Script not found: ' . $scriptPath,
      'output' => 'Script not found: ' . $scriptPath,
    ];
  }

  if (!is_executable($scriptReal)) {
    return [
      'exit_code' => 126,
      'stdout' => '',
      'stderr' => 'Script is not executable: ' . $scriptReal,
      'output' => 'Script is not executable: ' . $scriptReal,
    ];
  }

  if (!cms_frontend_can_exec()) {
    return [
      'exit_code' => 125,
      'stdout' => '',
      'stderr' => 'Command execution disabled in PHP runtime.',
      'output' => 'Command execution disabled in PHP runtime.',
    ];
  }

  $descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
  ];

  $env = null;
  if ($extraEnv) {
    $currentEnv = getenv();
    $env = is_array($currentEnv) ? $currentEnv : [];
    foreach ($extraEnv as $key => $value) {
      if (!is_string($key) || $key === '') {
        continue;
      }
      $env[$key] = (string) $value;
    }
  }

  $process = proc_open([$scriptReal, $mode], $descriptors, $pipes, null, $env);
  if (!is_resource($process)) {
    return [
      'exit_code' => 124,
      'stdout' => '',
      'stderr' => 'Unable to start release process.',
      'output' => 'Unable to start release process.',
    ];
  }

  fclose($pipes[0]);
  $stdout = stream_get_contents($pipes[1]);
  $stderr = stream_get_contents($pipes[2]);
  fclose($pipes[1]);
  fclose($pipes[2]);
  $exitCode = proc_close($process);

  $stdout = trim((string) $stdout);
  $stderr = trim((string) $stderr);
  $output = trim($stdout . ($stderr !== '' ? "\n" . $stderr : ''));

  return [
    'exit_code' => (int) $exitCode,
    'stdout' => $stdout,
    'stderr' => $stderr,
    'output' => $output,
  ];
}

function cms_frontend_jobs_table_exists(PDO $pdo): bool {
  static $exists = null;
  if ($exists !== null) {
    return $exists;
  }

  try {
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'cms_deploy_jobs'");
    $stmt->execute();
    $exists = (bool) $stmt->fetchColumn();
  } catch (Throwable $e) {
    $exists = false;
  }

  return $exists;
}

function cms_frontend_queue_deploy_job(PDO $pdo, string $siteRoot, ?int $requestedBy): int {
  $stmt = $pdo->prepare(
    'INSERT INTO cms_deploy_jobs
      (site_root, job_type, status, requested_by, requested_at, showonweb, archived)
     VALUES
      (:site_root, :job_type, :status, :requested_by, NOW(), :showonweb, :archived)'
  );

  $stmt->execute([
    ':site_root' => $siteRoot,
    ':job_type' => 'frontend_deploy',
    ':status' => 'queued',
    ':requested_by' => $requestedBy,
    ':showonweb' => 'Yes',
    ':archived' => 0,
  ]);

  return (int) $pdo->lastInsertId();
}

function cms_frontend_list_jobs(PDO $pdo, string $siteRoot, int $limit = 20): array {
  $limit = max(1, min(200, $limit));
  $sql = 'SELECT j.id, j.site_root, j.job_type, j.status, j.requested_by, j.requested_at,
                 j.started_at, j.finished_at, j.exit_code, j.output_text, u.username
          FROM cms_deploy_jobs j
          LEFT JOIN cms_users u ON u.id = j.requested_by
          WHERE j.site_root = :site_root
            AND j.job_type = :job_type
            AND j.archived = 0
          ORDER BY j.id DESC
          LIMIT ' . $limit;

  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ':site_root' => $siteRoot,
    ':job_type' => 'frontend_deploy',
  ]);

  return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function cms_frontend_get_latest_job(PDO $pdo, string $siteRoot): ?array {
  $jobs = cms_frontend_list_jobs($pdo, $siteRoot, 1);
  return $jobs[0] ?? null;
}

/**
 * Move stale running jobs to failed to avoid permanent queue stalls.
 */
function cms_frontend_fail_stale_running_jobs(PDO $pdo, int $staleMinutes = 120): int {
  $staleMinutes = max(5, min(1440, $staleMinutes));
  $sql = 'UPDATE cms_deploy_jobs
          SET status = :status,
              finished_at = NOW(),
              exit_code = :exit_code,
              output_text = CONCAT(IFNULL(output_text, ""), "\n[worker] Marked failed after stale running timeout.")
          WHERE status = :running
            AND started_at IS NOT NULL
            AND started_at < (NOW() - INTERVAL ' . $staleMinutes . ' MINUTE)';

  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ':status' => 'failed',
    ':exit_code' => 124,
    ':running' => 'running',
  ]);

  return (int) $stmt->rowCount();
}

/**
 * Atomically claim oldest queued job.
 */
function cms_frontend_claim_next_job(PDO $pdo, ?string $siteRoot = null): ?array {
  $whereSite = '';
  $params = [
    ':queued' => 'queued',
    ':job_type' => 'frontend_deploy',
  ];
  if ($siteRoot !== null && $siteRoot !== '') {
    $whereSite = ' AND site_root = :site_root ';
    $params[':site_root'] = $siteRoot;
  }

  $pdo->beginTransaction();
  try {
    $selectSql = 'SELECT id
                  FROM cms_deploy_jobs
                  WHERE status = :queued
                    AND job_type = :job_type
                    AND archived = 0
                    ' . $whereSite . '
                  ORDER BY requested_at ASC, id ASC
                  LIMIT 1
                  FOR UPDATE';
    $select = $pdo->prepare($selectSql);
    $select->execute($params);
    $id = $select->fetchColumn();

    if ($id === false) {
      $pdo->rollBack();
      return null;
    }

    $update = $pdo->prepare('UPDATE cms_deploy_jobs
                             SET status = :running,
                                 started_at = NOW(),
                                 finished_at = NULL,
                                 exit_code = NULL,
                                 output_text = NULL
                             WHERE id = :id
                               AND status = :queued');
    $update->execute([
      ':running' => 'running',
      ':id' => (int) $id,
      ':queued' => 'queued',
    ]);

    if ((int) $update->rowCount() !== 1) {
      $pdo->rollBack();
      return null;
    }

    $jobStmt = $pdo->prepare('SELECT * FROM cms_deploy_jobs WHERE id = :id LIMIT 1');
    $jobStmt->execute([':id' => (int) $id]);
    $job = $jobStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $pdo->commit();
    return $job;
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    throw $e;
  }
}

function cms_frontend_finish_job(PDO $pdo, int $jobId, string $status, ?int $exitCode, string $outputText): void {
  if (!in_array($status, ['success', 'failed'], true)) {
    $status = 'failed';
  }

  if (strlen($outputText) > 1000000) {
    $outputText = substr($outputText, 0, 1000000) . "\n[worker] Output truncated to 1,000,000 bytes.";
  }

  $stmt = $pdo->prepare('UPDATE cms_deploy_jobs
                         SET status = :status,
                             finished_at = NOW(),
                             exit_code = :exit_code,
                             output_text = :output_text
                         WHERE id = :id');
  $stmt->execute([
    ':status' => $status,
    ':exit_code' => $exitCode,
    ':output_text' => $outputText,
    ':id' => $jobId,
  ]);
}
