<?php
require_once __DIR__ . '/includes/boot.php';
cms_require_login();

/**
 * Confirm a table exists and reject unsafe table names.
 */
function cms_table_exists(PDO $pdo, string $table): bool {
  if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
    return false;
  }
  $stmt = $pdo->prepare("SHOW TABLES LIKE :table");
  $stmt->execute([':table' => $table]);
  return (bool) $stmt->fetchColumn();
}

/**
 * Fetch columns for a given table (empty array if invalid or missing).
 */
function cms_table_columns(PDO $pdo, string $table): array {
  if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
    return [];
  }
  $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
  return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

/**
 * Find the first matching column by candidate names (case-insensitive).
 */
function cms_pick_column(array $columns, array $candidates): ?string {
  $cols = array_map(static fn($col) => strtolower($col['Field'] ?? ''), $columns);
  foreach ($candidates as $candidate) {
    $idx = array_search(strtolower($candidate), $cols, true);
    if ($idx !== false) {
      return $columns[$idx]['Field'];
    }
  }
  return null;
}

/**
 * Resolve a table name from the cms_table registry using an ID.
 */
function cms_resolve_table_name(PDO $pdo, $tableId): ?string {
  if (!cms_table_exists($pdo, 'cms_table')) {
    return null;
  }
  if (!is_numeric($tableId)) {
    return null;
  }
  $cmsTableCols = cms_table_columns($pdo, 'cms_table');
  $nameField = cms_pick_column($cmsTableCols, ['name', 'tablename', 'table', 'table_name']);
  if (!$nameField) {
    return null;
  }
  $stmt = $pdo->prepare("SELECT {$nameField} AS tablename FROM cms_table WHERE id = :id LIMIT 1");
  $stmt->execute([':id' => (int) $tableId]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  $tableName = $row['tablename'] ?? null;
  return $tableName ? (string) $tableName : null;
}

/**
 * Fetch the form configuration for a given form ID.
 */
function cms_get_form(PDO $pdo, int $formId): ?array {
  if ($formId <= 0 || !cms_table_exists($pdo, 'cms_form')) {
    return null;
  }
  $stmt = $pdo->prepare('SELECT * FROM cms_form WHERE id = :id LIMIT 1');
  $stmt->execute([':id' => $formId]);
  return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Fetch a single record from a content table.
 */
function cms_fetch_record(PDO $pdo, string $table, int $recordId): ?array {
  if ($recordId <= 0) {
    return null;
  }
  if (!cms_table_exists($pdo, $table)) {
    return null;
  }
  $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE id = :id LIMIT 1");
  $stmt->execute([':id' => $recordId]);
  return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$errors = [];
$action = strtolower(trim((string) ($_GET['action'] ?? '')));
$formId = isset($_GET['frm']) ? (int) $_GET['frm'] : 0;
$recordId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$DB_OK || !($pdo instanceof PDO)) {
  $errors[] = 'Database connection is unavailable.';
}

$form = null;
$contentTable = null;
$record = null;

if (!$errors) {
  $form = cms_get_form($pdo, $formId);
  if (!$form) {
    $errors[] = 'Form not found.';
  }
}

if (!$errors && $form) {
  $contentTable = cms_resolve_table_name($pdo, $form['table'] ?? null);
  if (!$contentTable) {
    $errors[] = 'Content table not resolved for this form.';
  }
}

if (!$errors && $contentTable) {
  $record = cms_fetch_record($pdo, $contentTable, $recordId);
  if (!$record) {
    $errors[] = 'Record not found.';
  }
}

if (!$errors && $contentTable && $record) {
  $columns = cms_table_columns($pdo, $contentTable);
  $columnNames = array_map(static fn($col) => $col['Field'] ?? '', $columns);
  $sets = [];
  $params = [':id' => $recordId];

  $applyShowOnWebNo = false;
  if (in_array($action, ['archive', 'delete'], true)) {
    if (in_array('archived', $columnNames, true)) {
      $sets[] = "`archived` = :archived";
      $params[':archived'] = 1;
      $applyShowOnWebNo = true;
    }
  } elseif (in_array($action, ['unarchive', 'undelete'], true)) {
    if (in_array('archived', $columnNames, true)) {
      $sets[] = "`archived` = :archived";
      $params[':archived'] = 0;
      $applyShowOnWebNo = true;
    }
  } elseif (in_array($action, ['toggle_show', 'toggle', 'showhide'], true)) {
    if (in_array('showonweb', $columnNames, true)) {
      $current = $record['showonweb'] ?? 'No';
      $next = strtolower((string) $current) === 'yes' ? 'No' : 'Yes';
      $sets[] = "`showonweb` = :showonweb";
      $params[':showonweb'] = $next;
    }
  } else {
    $errors[] = 'Unknown action.';
  }

  if ($applyShowOnWebNo && in_array('showonweb', $columnNames, true)) {
    $sets[] = "`showonweb` = :showonweb";
    $params[':showonweb'] = 'No';
  }

  if (!$errors && $sets) {
    try {
      $sql = "UPDATE `{$contentTable}` SET " . implode(', ', $sets) . " WHERE id = :id";
      $resolved = $sql;
      foreach ($params as $pKey => $pValue) {
        $quoted = $pdo->quote((string) $pValue);
        $resolved = str_replace($pKey, $quoted, $resolved);
      }
      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);
      cms_log_action('record_action_' . $action, $contentTable, $recordId, $resolved, $form['title'] ?? 'form', 'cms');
      header('Location: ' . $CMS_BASE_URL . '/recordViewv5.php?frm=' . urlencode((string) $formId));
      exit;
    } catch (PDOException $e) {
      $errors[] = 'Failed to update record.';
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
      <div>
        <h1 class="h3 mb-1">Record Action</h1>
        <p class="text-muted mb-0">Unable to complete the action.</p>
      </div>
      <div>
        <a class="btn btn-outline-secondary" href="<?php echo $CMS_BASE_URL; ?>/recordViewv5.php?frm=<?php echo cms_h((string) $formId); ?>">Back to list</a>
      </div>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
          <div><?php echo cms_h($error); ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
</div>
<?php include __DIR__ . '/includes/footer-code.php'; ?>
