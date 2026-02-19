<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/lib/cms_schema_sync.php';
require_once __DIR__ . '/includes/boot.php';
cms_require_login();

$toolMeta = [
  'id' => 'tools-migration',
  'version' => 'v1.1.0',
  'updated_at' => '2026-02-18 13:35 UTC',
];

function tm_h(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function tm_load_target_runtime(string $targetConfigPath): array {
  $DB_OK = false;
  $DB_ERROR = null;
  $DB_CONFIG_SOURCE = basename($targetConfigPath);
  $DB_HOST = null;
  $DB_NAME = null;
  $DB_USER = null;
  $DB_PASS = null;

  if (!is_file($targetConfigPath)) {
    throw new RuntimeException("Target DB config not found: {$targetConfigPath}");
  }

  require $targetConfigPath;

  return [
    'DB_OK' => (bool) ($DB_OK ?? false),
    'DB_ERROR' => (string) ($DB_ERROR ?? ''),
    'DB_CONFIG_SOURCE' => (string) ($DB_CONFIG_SOURCE ?? basename($targetConfigPath)),
    'DB_HOST' => (string) ($DB_HOST ?? ''),
    'DB_NAME' => (string) ($DB_NAME ?? ''),
    'DB_USER' => (string) ($DB_USER ?? ''),
    'DB_PASS' => (string) ($DB_PASS ?? ''),
  ];
}

function tm_list_migration_files(string $migrationsDir): array {
  if (!is_dir($migrationsDir)) {
    return [];
  }
  $files = glob(rtrim($migrationsDir, '/') . '/*.sql') ?: [];
  $names = array_map(static fn(string $path): string => basename($path), $files);
  sort($names, SORT_STRING);
  return $names;
}

function tm_ensure_migration_table(PDO $pdo): void {
  $sql = <<<SQL
CREATE TABLE IF NOT EXISTS cms_migrations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  migration_name VARCHAR(255) NOT NULL,
  checksum CHAR(64) NULL,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_cms_migrations_name (migration_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL;
  $pdo->exec($sql);
}

function tm_get_applied_migrations(PDO $pdo): array {
  tm_ensure_migration_table($pdo);
  $stmt = $pdo->query('SELECT migration_name, applied_at, checksum FROM cms_migrations ORDER BY migration_name ASC');
  $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
  $map = [];
  foreach ($rows as $row) {
    $name = (string) ($row['migration_name'] ?? '');
    if ($name === '') {
      continue;
    }
    $map[$name] = $row;
  }
  return $map;
}

function tm_split_sql_statements(string $sql): array {
  $sql = preg_replace("/^\xEF\xBB\xBF/u", '', $sql) ?? $sql;
  $len = strlen($sql);
  $statements = [];
  $buf = '';
  $inSingle = false;
  $inDouble = false;
  $inBacktick = false;
  $inLineComment = false;
  $inBlockComment = false;

  for ($i = 0; $i < $len; $i++) {
    $ch = $sql[$i];
    $next = ($i + 1 < $len) ? $sql[$i + 1] : '';

    if ($inLineComment) {
      if ($ch === "\n") {
        $inLineComment = false;
        $buf .= $ch;
      }
      continue;
    }

    if ($inBlockComment) {
      if ($ch === '*' && $next === '/') {
        $inBlockComment = false;
        $i++;
      }
      continue;
    }

    if (!$inSingle && !$inDouble && !$inBacktick) {
      if ($ch === '-' && $next === '-') {
        $inLineComment = true;
        $i++;
        continue;
      }
      if ($ch === '#') {
        $inLineComment = true;
        continue;
      }
      if ($ch === '/' && $next === '*') {
        $inBlockComment = true;
        $i++;
        continue;
      }
    }

    if (!$inDouble && !$inBacktick && $ch === "'" && ($i === 0 || $sql[$i - 1] !== '\\')) {
      $inSingle = !$inSingle;
      $buf .= $ch;
      continue;
    }
    if (!$inSingle && !$inBacktick && $ch === '"' && ($i === 0 || $sql[$i - 1] !== '\\')) {
      $inDouble = !$inDouble;
      $buf .= $ch;
      continue;
    }
    if (!$inSingle && !$inDouble && $ch === '`') {
      $inBacktick = !$inBacktick;
      $buf .= $ch;
      continue;
    }

    if (!$inSingle && !$inDouble && !$inBacktick && $ch === ';') {
      $stmt = trim($buf);
      if ($stmt !== '') {
        $statements[] = $stmt;
      }
      $buf = '';
      continue;
    }

    $buf .= $ch;
  }

  $tail = trim($buf);
  if ($tail !== '') {
    $statements[] = $tail;
  }

  return $statements;
}

function tm_run_migration(PDO $pdo, string $migrationsDir, string $fileName): array {
  $safeFile = basename($fileName);
  if ($safeFile !== $fileName || !preg_match('/^[A-Za-z0-9._-]+\.sql$/', $safeFile)) {
    throw new RuntimeException('Invalid migration filename.');
  }

  $path = rtrim($migrationsDir, '/') . '/' . $safeFile;
  if (!is_file($path)) {
    throw new RuntimeException("Migration file not found: {$safeFile}");
  }

  $sql = (string) file_get_contents($path);
  $statements = tm_split_sql_statements($sql);
  if (!$statements) {
    throw new RuntimeException("Migration contains no executable SQL: {$safeFile}");
  }

  $pdo->beginTransaction();
  try {
    foreach ($statements as $statement) {
      $pdo->exec($statement);
    }
    $hash = hash('sha256', $sql);
    $stmt = $pdo->prepare('INSERT INTO cms_migrations (migration_name, checksum) VALUES (:name, :checksum)');
    $stmt->execute([
      ':name' => $safeFile,
      ':checksum' => $hash,
    ]);
    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    throw $e;
  }

  return [
    'file' => $safeFile,
    'statements' => count($statements),
    'status' => 'applied',
  ];
}

function tm_run_sync_script(string $scriptPath, string $siteWebRoot, string $branch): array {
  if (!is_file($scriptPath)) {
    throw new RuntimeException('Sync script not found: ' . $scriptPath);
  }
  if ($siteWebRoot === '' || $siteWebRoot[0] !== '/' || !is_dir($siteWebRoot)) {
    throw new RuntimeException('Site web root must be an existing absolute path.');
  }
  if (!preg_match('/^[A-Za-z0-9._\\/-]+$/', $branch)) {
    throw new RuntimeException('Invalid branch name.');
  }

  $cmd = 'bash '
    . escapeshellarg($scriptPath) . ' '
    . escapeshellarg($siteWebRoot) . ' '
    . escapeshellarg($branch)
    . ' 2>&1';

  $output = [];
  $exitCode = 0;
  exec($cmd, $output, $exitCode);

  return [
    'command' => $cmd,
    'output' => $output,
    'exit_code' => $exitCode,
  ];
}

$privateDir = realpath(__DIR__ . '/../../private') ?: (__DIR__ . '/../../private');
$defaultTargetConfig = rtrim($privateDir, '/') . '/dbcon.php';
$targetConfigPath = (string) ($_POST['target_config'] ?? $defaultTargetConfig);
$migrationsDir = __DIR__ . '/sql/migrations';
$syncScriptPath = __DIR__ . '/scripts/wccms-sync.sh';
$defaultSyncSiteWebRoot = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
$syncSiteWebRoot = trim((string) ($_POST['sync_site_web_root'] ?? $defaultSyncSiteWebRoot));
$syncBranch = trim((string) ($_POST['sync_branch'] ?? 'staging'));
$action = (string) ($_POST['action'] ?? '');

$message = '';
$error = '';
$results = [];
$runtime = null;
$migrationFiles = [];
$applied = [];
$syncRan = false;
$syncExitCode = null;
$syncOutput = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'sync_backend') {
  try {
    $syncResult = tm_run_sync_script($syncScriptPath, $syncSiteWebRoot, $syncBranch);
    $syncRan = true;
    $syncExitCode = (int) ($syncResult['exit_code'] ?? 1);
    $syncOutput = array_map('strval', (array) ($syncResult['output'] ?? []));
    if ($syncExitCode === 0) {
      $message = 'Backend sync completed successfully.';
    } else {
      $error = 'Backend sync failed with exit code ' . $syncExitCode . '.';
    }
  } catch (Throwable $e) {
    $error = $e->getMessage();
  }
}

try {
  $runtime = tm_load_target_runtime($targetConfigPath);
  $pdo = cms_schema_sync_connect($runtime);
  $runtime['DB_OK'] = true;
  $runtime['DB_ERROR'] = '';

  $migrationFiles = tm_list_migration_files($migrationsDir);
  $applied = tm_get_applied_migrations($pdo);

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['run_next', 'run_all', 'run_one'], true)) {
    if (!$migrationFiles) {
      throw new RuntimeException('No migration files found.');
    }

    if ($action === 'run_next') {
      $next = null;
      foreach ($migrationFiles as $file) {
        if (!isset($applied[$file])) {
          $next = $file;
          break;
        }
      }
      if ($next === null) {
        $message = 'No pending migrations. Database is up to date.';
      } else {
        $results[] = tm_run_migration($pdo, $migrationsDir, $next);
        $message = 'Ran next pending migration.';
      }
    } elseif ($action === 'run_all') {
      foreach ($migrationFiles as $file) {
        if (isset($applied[$file])) {
          continue;
        }
        $results[] = tm_run_migration($pdo, $migrationsDir, $file);
      }
      $message = $results ? ('GO complete. Applied ' . count($results) . ' migration(s).') : 'No pending migrations. Database is up to date.';
    } elseif ($action === 'run_one') {
      $file = (string) ($_POST['migration'] ?? '');
      if ($file === '') {
        throw new RuntimeException('Choose a migration to run.');
      }
      if (!in_array($file, $migrationFiles, true)) {
        throw new RuntimeException('Selected migration is not available.');
      }
      if (isset($applied[$file])) {
        $message = 'Migration already applied: ' . $file;
      } else {
        $results[] = tm_run_migration($pdo, $migrationsDir, $file);
        $message = 'Migration applied: ' . $file;
      }
    }

    $applied = tm_get_applied_migrations($pdo);
  }
} catch (Throwable $e) {
  $error = $e->getMessage();
  if (!is_array($runtime)) {
    $runtime = [
      'DB_OK' => false,
      'DB_ERROR' => $error,
      'DB_CONFIG_SOURCE' => basename($targetConfigPath),
      'DB_HOST' => '',
      'DB_NAME' => '',
      'DB_USER' => '',
      'DB_PASS' => '',
    ];
  } else {
    $runtime['DB_OK'] = false;
    $runtime['DB_ERROR'] = $error;
  }
}

$pendingCount = 0;
foreach ($migrationFiles as $file) {
  if (!isset($applied[$file])) {
    $pendingCount++;
  }
}

include __DIR__ . '/includes/header-code.php';
include __DIR__ . '/includes/header.php';
?>
<div class="cms-shell">
  <?php include __DIR__ . '/includes/menu.php'; ?>
  <main class="cms-content">
    <div class="cms-content-header">
      <div>
        <h1 class="h3 mb-1">Migration Tools</h1>
        <p class="text-muted mb-0">Run database migrations from <code>/wccms/sql/migrations</code>.</p>
        <p class="text-muted small mb-0">
          Tool: <code><?php echo tm_h($toolMeta['id']); ?></code>
          | Version: <code><?php echo tm_h($toolMeta['version']); ?></code>
          | Updated: <code><?php echo tm_h($toolMeta['updated_at']); ?></code>
        </p>
      </div>
    </div>

    <div class="cms-card">
      <h2 class="h5 mb-3">Backend Git Sync</h2>
      <p class="text-muted mb-3">Pull latest backend code via <code>/wccms/scripts/wccms-sync.sh</code>.</p>
      <form method="post" action="<?php echo $CMS_BASE_URL; ?>/tools-migration.php" class="mb-3">
        <div class="row g-3 align-items-end">
          <div class="col-lg-6">
            <label class="form-label" for="sync_site_web_root">Site Web Root</label>
            <input class="form-control" type="text" id="sync_site_web_root" name="sync_site_web_root" value="<?php echo tm_h($syncSiteWebRoot); ?>" required>
          </div>
          <div class="col-lg-3">
            <label class="form-label" for="sync_branch">Branch</label>
            <input class="form-control" type="text" id="sync_branch" name="sync_branch" value="<?php echo tm_h($syncBranch); ?>" required>
          </div>
          <div class="col-lg-3">
            <button class="btn btn-outline-primary w-100" type="submit" name="action" value="sync_backend">Pull Backend Now</button>
          </div>
        </div>
      </form>
      <?php if ($syncRan): ?>
        <div class="alert <?php echo ($syncExitCode === 0) ? 'alert-success' : 'alert-danger'; ?> mb-0">
          Exit code: <strong><?php echo (int) $syncExitCode; ?></strong>
        </div>
      <?php endif; ?>
      <?php if (!empty($syncOutput)): ?>
        <div class="mt-2">
          <pre class="small mb-0 p-2 bg-light border rounded"><?php echo tm_h(implode("\n", $syncOutput)); ?></pre>
        </div>
      <?php endif; ?>
    </div>

    <div class="cms-card">
      <?php if ($message !== ''): ?>
        <div class="alert alert-success"><?php echo tm_h($message); ?></div>
      <?php endif; ?>
      <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo tm_h($error); ?></div>
      <?php endif; ?>
      <?php if (!empty($results)): ?>
        <div class="alert alert-info">
          <?php foreach ($results as $result): ?>
            <div><?php echo tm_h((string) $result['file']); ?>: <?php echo tm_h((string) $result['status']); ?> (<?php echo (int) ($result['statements'] ?? 0); ?> statement(s))</div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" action="<?php echo $CMS_BASE_URL; ?>/tools-migration.php" class="mb-3">
        <div class="row g-3 align-items-end">
          <div class="col-lg-8">
            <label class="form-label" for="target_config">Target DB Config</label>
            <input class="form-control" type="text" id="target_config" name="target_config" value="<?php echo tm_h($targetConfigPath); ?>" required>
          </div>
          <div class="col-lg-4">
            <button class="btn btn-outline-primary w-100" type="submit" name="action" value="refresh">Refresh Status</button>
          </div>
        </div>
      </form>

      <div class="d-flex gap-2 flex-wrap mb-2">
        <form method="post" action="<?php echo $CMS_BASE_URL; ?>/tools-migration.php" class="d-inline">
          <input type="hidden" name="target_config" value="<?php echo tm_h($targetConfigPath); ?>">
          <button class="btn btn-primary" type="submit" name="action" value="run_next">Run Next Pending (First Action)</button>
        </form>
        <form method="post" action="<?php echo $CMS_BASE_URL; ?>/tools-migration.php" class="d-inline">
          <input type="hidden" name="target_config" value="<?php echo tm_h($targetConfigPath); ?>">
          <button class="btn btn-danger" type="submit" name="action" value="run_all" <?php echo $pendingCount === 0 ? 'disabled' : ''; ?>>GO: Run All Pending</button>
        </form>
      </div>
      <p class="text-muted small mb-0">Pending: <strong><?php echo (int) $pendingCount; ?></strong> of <?php echo (int) count($migrationFiles); ?> migration file(s).</p>
    </div>

    <div class="cms-card">
      <h2 class="h5 mb-3">Migration List</h2>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>Migration</th>
              <th>Status</th>
              <th>Applied At</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$migrationFiles): ?>
              <tr><td colspan="4" class="text-muted">No migration files found.</td></tr>
            <?php else: ?>
              <?php foreach ($migrationFiles as $file): ?>
                <?php $isApplied = isset($applied[$file]); ?>
                <tr>
                  <td><code><?php echo tm_h($file); ?></code></td>
                  <td><?php echo $isApplied ? '<span class="text-success">Applied</span>' : '<span class="text-warning">Pending</span>'; ?></td>
                  <td><?php echo tm_h((string) ($applied[$file]['applied_at'] ?? '')); ?></td>
                  <td class="text-end">
                    <form method="post" action="<?php echo $CMS_BASE_URL; ?>/tools-migration.php" class="d-inline">
                      <input type="hidden" name="target_config" value="<?php echo tm_h($targetConfigPath); ?>">
                      <input type="hidden" name="migration" value="<?php echo tm_h($file); ?>">
                      <button class="btn btn-sm btn-outline-primary" type="submit" name="action" value="run_one" <?php echo $isApplied ? 'disabled' : ''; ?>>Run</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
<?php include __DIR__ . '/includes/footer-code.php'; ?>
