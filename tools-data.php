<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/lib/cms_schema_sync.php';

$toolMeta = [
  'id' => 'tools-data',
  'version' => 'v1.4.0',
  'updated_at' => '2026-02-12 11:55 UTC',
];

$embedParam = strtolower((string) ($_GET['embed'] ?? ''));
$embeddedMode = in_array($embedParam, ['1', 'yes', 'true', 'on'], true);

if ($embeddedMode) {
  require_once __DIR__ . '/includes/boot.php';
  cms_require_login();
}

function td_h(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function td_load_target_runtime(string $targetConfigPath): array {
  $DB_OK = false;
  $DB_ERROR = null;
  $DB_CONFIG_SOURCE = basename($targetConfigPath);
  // Use null so dbcon-local.php "??" defaults still apply.
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

$privateDir = realpath(__DIR__ . '/../../private') ?: (__DIR__ . '/../../private');
$defaultTargetConfig = rtrim($privateDir, '/') . '/dbcon.php';
$defaultMasterConfig = rtrim($privateDir, '/') . '/dbcon-master.php';

function td_load_master_runtime(string $masterConfigPath): array {
  $cfg = cms_schema_sync_load_master_config($masterConfigPath);
  return [
    'DB_CONFIG_SOURCE' => basename($masterConfigPath),
    'DB_HOST' => (string) ($cfg['DB_HOST'] ?? ''),
    'DB_NAME' => (string) ($cfg['DB_NAME'] ?? ''),
    'DB_USER' => (string) ($cfg['DB_USER'] ?? ''),
    'DB_PASS' => (string) ($cfg['DB_PASS'] ?? ''),
  ];
}

$targetConfigPath = (string) ($_POST['target_config'] ?? $defaultTargetConfig);
$masterConfigPath = (string) ($_POST['master_config'] ?? $defaultMasterConfig);
$tablePrefix = trim((string) ($_POST['table_prefix'] ?? 'cms_'));
$action = (string) ($_POST['action'] ?? 'check');

$message = '';
$error = '';
$targetRuntime = null;
$masterRuntime = null;
$tableCoverage = null;
$plan = null;
$applied = [];
$seeded = [];
$formAction = $embeddedMode ? '?embed=1' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $targetRuntime = td_load_target_runtime($targetConfigPath);
    $targetPdo = null;
    try {
      $targetPdo = cms_schema_sync_connect($targetRuntime);
      $targetRuntime['DB_OK'] = true;
      $targetRuntime['DB_ERROR'] = '';
    } catch (Throwable $e) {
      $targetRuntime['DB_OK'] = false;
      $targetRuntime['DB_ERROR'] = $e->getMessage();
    }

    $masterRuntime = td_load_master_runtime($masterConfigPath);
    $sourcePdo = null;
    try {
      $sourcePdo = cms_schema_sync_connect($masterRuntime);
      $masterRuntime['DB_OK'] = true;
      $masterRuntime['DB_ERROR'] = '';
    } catch (Throwable $e) {
      $masterRuntime['DB_OK'] = false;
      $masterRuntime['DB_ERROR'] = $e->getMessage();
    }

    if ($sourcePdo instanceof PDO && $targetPdo instanceof PDO) {
      $tableCoverage = cms_schema_sync_table_coverage($sourcePdo, $targetPdo, $tablePrefix);
    }

    if ($action === 'preview' || $action === 'apply') {
      if (!($targetPdo instanceof PDO)) {
        throw new RuntimeException('Target DB unavailable: ' . (string) ($targetRuntime['DB_ERROR'] ?? 'unknown'));
      }
      if (!($sourcePdo instanceof PDO)) {
        throw new RuntimeException('Master DB unavailable: ' . (string) ($masterRuntime['DB_ERROR'] ?? 'unknown'));
      }

      $plan = cms_schema_sync_plan($sourcePdo, $targetPdo, [
        'table_prefix' => $tablePrefix,
      ]);
      $opsCount = (int) (($plan['summary']['operations'] ?? 0));

      if ($action === 'apply') {
        $confirm = (string) ($_POST['confirm_apply'] ?? '');
        if ($confirm !== 'yes') {
          throw new RuntimeException('Tick "Apply changes now" before running apply.');
        }
        if ($opsCount > 0) {
          $applied = cms_schema_sync_apply($targetPdo, $plan['operations'] ?? []);
        } else {
          $applied = [];
        }

        $seeded = cms_schema_sync_seed_bootstrap_data($sourcePdo, $targetPdo);
        $seededTables = 0;
        $seededRows = 0;
        foreach ($seeded as $seedRow) {
          if (($seedRow['status'] ?? '') !== 'seeded') {
            continue;
          }
          $seededTables++;
          $seededRows += (int) ($seedRow['inserted'] ?? 0);
        }

        $message = ($opsCount > 0)
          ? ('Applied ' . count($applied) . ' schema change(s).')
          : 'No schema changes required.';
        $message .= ' Bootstrap seed: ' . $seededRows . ' row(s) across ' . $seededTables . ' table(s).';
        $tableCoverage = cms_schema_sync_table_coverage($sourcePdo, $targetPdo, $tablePrefix);
      } else {
        $message = ($opsCount === 0)
          ? 'No schema changes required.'
          : 'Preview generated. Review changes before apply.';
      }
    } else {
      $targetOk = !empty($targetRuntime['DB_OK']);
      $masterOk = !empty($masterRuntime['DB_OK']);
      if ($targetOk && $masterOk) {
        $message = 'Target and master databases are reachable.';
      } elseif ($targetOk) {
        $message = 'Target DB is reachable, master DB check failed.';
      } elseif ($masterOk) {
        $message = 'Master DB is reachable, target DB check failed.';
      } else {
        $message = 'Target and master DB checks failed.';
      }
    }
  } catch (Throwable $e) {
    $error = $e->getMessage();
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>WCCMS Data Tools</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 0; background: #f5f7fb; color: #111; }
    .wrap { max-width: 1100px; margin: 24px auto; padding: 0 16px; }
    .card { background: #fff; border: 1px solid #d7dfea; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
    h1 { margin: 0 0 8px; font-size: 24px; }
    p { margin: 6px 0; }
    .row { display: flex; gap: 10px; flex-wrap: wrap; }
    .col { flex: 1 1 320px; min-width: 280px; }
    label { display: block; font-weight: 700; margin-bottom: 6px; }
    input[type="text"] { width: 100%; box-sizing: border-box; padding: 8px; border: 1px solid #b8c5d9; border-radius: 6px; }
    .buttons { margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap; }
    button { border: 1px solid #274b88; background: #2c5aa0; color: #fff; border-radius: 6px; padding: 8px 12px; cursor: pointer; }
    button.apply { background: #8a1f1f; border-color: #741818; }
    .ok { color: #0c6b2c; font-weight: 700; }
    .bad { color: #9b1c1c; font-weight: 700; }
    .alert-ok { background: #e8f6eb; border: 1px solid #9ed9ab; border-radius: 8px; padding: 10px; }
    .alert-bad { background: #fde8e8; border: 1px solid #efb0b0; border-radius: 8px; padding: 10px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #d7dfea; padding: 8px; text-align: left; vertical-align: top; }
    th { background: #eef3fb; }
    code { white-space: pre-wrap; word-break: break-word; }
    .small { font-size: 12px; color: #445; }
  </style>
</head>
<body>
  <?php if ($embeddedMode): ?>
    <?php include __DIR__ . '/includes/header-code.php'; ?>
    <?php include __DIR__ . '/includes/header.php'; ?>
    <div class="cms-shell">
      <?php include __DIR__ . '/includes/menu.php'; ?>
      <main class="cms-content">
  <?php endif; ?>

  <div class="wrap">
    <div class="card">
      <h1>WCCMS Data Tools</h1>
      <p>Direct-access tool for database checks and additive schema sync. Use when CMS menu/auth is unavailable.</p>
      <p class="small">
        Tool: <code><?php echo td_h($toolMeta['id']); ?></code>
        | Version: <code><?php echo td_h($toolMeta['version']); ?></code>
        | Updated: <code><?php echo td_h($toolMeta['updated_at']); ?></code>
      </p>
      <p class="small">URL: <code>/wccms/tools-data.php</code></p>
    </div>

    <div class="card">
      <?php if ($message !== ''): ?>
        <div class="alert-ok"><?php echo td_h($message); ?></div>
      <?php endif; ?>
      <?php if ($error !== ''): ?>
        <div class="alert-bad"><?php echo td_h($error); ?></div>
      <?php endif; ?>

      <form method="post" action="<?php echo td_h($formAction); ?>">
        <div class="row">
          <div class="col">
            <label for="target_config">Target DB Config</label>
            <input id="target_config" type="text" name="target_config" value="<?php echo td_h($targetConfigPath); ?>" required>
          </div>
          <div class="col">
            <label for="master_config">Master DB Config</label>
            <input id="master_config" type="text" name="master_config" value="<?php echo td_h($masterConfigPath); ?>" required>
          </div>
          <div class="col">
            <label for="table_prefix">Table Prefix Scope</label>
            <input id="table_prefix" type="text" name="table_prefix" value="<?php echo td_h($tablePrefix); ?>" required>
          </div>
        </div>
        <div class="buttons">
          <button type="submit" name="action" value="check">Check Target DB</button>
          <button type="submit" name="action" value="preview">Preview Missing Schema</button>
          <button class="apply" type="submit" name="action" value="apply">Apply Missing Schema</button>
        </div>
        <p class="small">
          <label><input type="checkbox" name="confirm_apply" value="yes"> Apply changes now (required for Apply)</label>
        </p>
      </form>
    </div>

    <?php if (is_array($targetRuntime) || is_array($masterRuntime)): ?>
      <div class="row">
        <?php if (is_array($targetRuntime)): ?>
          <div class="card col">
            <h2>Target DB Runtime</h2>
            <p><strong>Config Source:</strong> <code><?php echo td_h((string) ($targetRuntime['DB_CONFIG_SOURCE'] ?? 'unknown')); ?></code></p>
            <p><strong>Host:</strong> <code><?php echo td_h((string) ($targetRuntime['DB_HOST'] ?? '')); ?></code></p>
            <p><strong>Name:</strong> <code><?php echo td_h((string) ($targetRuntime['DB_NAME'] ?? '')); ?></code></p>
            <p><strong>User:</strong> <code><?php echo td_h((string) ($targetRuntime['DB_USER'] ?? '')); ?></code></p>
            <p><strong>Status:</strong>
              <?php if (!empty($targetRuntime['DB_OK'])): ?>
                <span class="ok">DB OK</span>
              <?php else: ?>
                <span class="bad">DB FAIL</span>
              <?php endif; ?>
            </p>
            <?php if (!empty($targetRuntime['DB_ERROR'])): ?>
              <p class="bad"><strong>Error:</strong> <?php echo td_h((string) $targetRuntime['DB_ERROR']); ?></p>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if (is_array($masterRuntime)): ?>
          <div class="card col">
            <h2>Master DB Runtime</h2>
            <p><strong>Config Source:</strong> <code><?php echo td_h((string) ($masterRuntime['DB_CONFIG_SOURCE'] ?? 'unknown')); ?></code></p>
            <p><strong>Host:</strong> <code><?php echo td_h((string) ($masterRuntime['DB_HOST'] ?? '')); ?></code></p>
            <p><strong>Name:</strong> <code><?php echo td_h((string) ($masterRuntime['DB_NAME'] ?? '')); ?></code></p>
            <p><strong>User:</strong> <code><?php echo td_h((string) ($masterRuntime['DB_USER'] ?? '')); ?></code></p>
            <p><strong>Status:</strong>
              <?php if (!empty($masterRuntime['DB_OK'])): ?>
                <span class="ok">DB OK</span>
              <?php else: ?>
                <span class="bad">DB FAIL</span>
              <?php endif; ?>
            </p>
            <?php if (!empty($masterRuntime['DB_ERROR'])): ?>
              <p class="bad"><strong>Error:</strong> <?php echo td_h((string) $masterRuntime['DB_ERROR']); ?></p>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if (is_array($tableCoverage)): ?>
      <div class="card">
        <h2>Master vs Target Table Coverage</h2>
        <p><strong>Prefix scope:</strong> <code><?php echo td_h((string) ($tableCoverage['summary']['table_prefix'] ?? '')); ?></code></p>
        <p><strong>Master tables in scope:</strong> <?php echo (int) ($tableCoverage['summary']['source_tables_in_scope'] ?? 0); ?></p>
        <p><strong>Missing in target:</strong> <?php echo (int) ($tableCoverage['summary']['missing_tables'] ?? 0); ?></p>
        <table>
          <thead>
            <tr><th>Table</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php foreach (($tableCoverage['rows'] ?? []) as $item): ?>
              <tr>
                <td><code><?php echo td_h((string) $item['table']); ?></code></td>
                <td><?php echo !empty($item['in_target']) ? '<span class="ok">OK</span>' : '<span class="bad">MISSING</span>'; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if (is_array($plan)): ?>
      <div class="card">
        <h2>Schema Diff Summary</h2>
        <p><strong>Prefix scope:</strong> <code><?php echo td_h((string) ($plan['summary']['table_prefix'] ?? '')); ?></code></p>
        <p><strong>Master tables in scope:</strong> <?php echo (int) ($plan['summary']['source_tables_in_scope'] ?? 0); ?></p>
        <p><strong>Missing tables:</strong> <?php echo (int) ($plan['summary']['missing_tables'] ?? 0); ?></p>
        <p><strong>Missing columns:</strong> <?php echo (int) ($plan['summary']['missing_columns'] ?? 0); ?></p>
        <p><strong>Total operations:</strong> <?php echo (int) ($plan['summary']['operations'] ?? 0); ?></p>
      </div>

      <?php if (!empty($plan['operations'])): ?>
        <div class="card">
          <h2>Planned SQL (Additive Only)</h2>
          <table>
            <thead>
              <tr><th>#</th><th>Type</th><th>Table</th><th>Column</th><th>SQL</th></tr>
            </thead>
            <tbody>
              <?php foreach ($plan['operations'] as $i => $op): ?>
                <tr>
                  <td><?php echo (int) $i + 1; ?></td>
                  <td><?php echo td_h((string) ($op['type'] ?? '')); ?></td>
                  <td><?php echo td_h((string) ($op['table'] ?? '')); ?></td>
                  <td><?php echo td_h((string) ($op['column'] ?? '')); ?></td>
                  <td><code><?php echo td_h((string) ($op['sql'] ?? '')); ?></code></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($applied)): ?>
      <div class="card">
        <h2>Applied Changes</h2>
        <table>
          <thead>
            <tr><th>#</th><th>Type</th><th>Table</th><th>Column</th></tr>
          </thead>
          <tbody>
            <?php foreach ($applied as $row): ?>
              <tr>
                <td><?php echo (int) ($row['index'] ?? 0); ?></td>
                <td><?php echo td_h((string) ($row['type'] ?? '')); ?></td>
                <td><?php echo td_h((string) ($row['table'] ?? '')); ?></td>
                <td><?php echo td_h((string) ($row['column'] ?? '')); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if (!empty($seeded)): ?>
      <div class="card">
        <h2>Bootstrap Seed Results</h2>
        <table>
          <thead>
            <tr><th>Table</th><th>Status</th><th>Rows</th><th>Copied</th><th>Legacy</th><th>Reason</th></tr>
          </thead>
          <tbody>
            <?php foreach ($seeded as $row): ?>
              <tr>
                <td><code><?php echo td_h((string) ($row['table'] ?? '')); ?></code></td>
                <td><?php echo td_h((string) ($row['status'] ?? '')); ?></td>
                <td><?php echo (int) ($row['inserted'] ?? 0); ?></td>
                <td><?php echo (int) ($row['copied_values'] ?? 0); ?></td>
                <td><?php echo td_h((string) ($row['legacy_available'] ?? '')); ?></td>
                <td><?php echo td_h((string) ($row['reason'] ?? '')); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($embeddedMode): ?>
      </main>
    </div>
    <?php include __DIR__ . '/includes/footer-code.php'; ?>
  <?php endif; ?>
</body>
</html>
