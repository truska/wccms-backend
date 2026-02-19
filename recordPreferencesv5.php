<?php
require_once __DIR__ . '/includes/boot.php';
cms_require_login();
include __DIR__ . '/includes/header-code.php';
include __DIR__ . '/includes/header.php';

function cms_prefeditor_table_exists(PDO $pdo, string $table): bool {
  if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
    return false;
  }
  $stmt = $pdo->prepare('SHOW TABLES LIKE :table');
  $stmt->execute([':table' => $table]);
  return (bool) $stmt->fetchColumn();
}

function cms_prefeditor_pick_table(PDO $pdo, array $candidates): ?string {
  foreach ($candidates as $table) {
    if (cms_prefeditor_table_exists($pdo, $table)) {
      return $table;
    }
  }
  return null;
}

function cms_prefeditor_table_columns(PDO $pdo, string $table): array {
  if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
    return [];
  }
  $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
  return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function cms_prefeditor_has_column(array $columns, string $name): bool {
  foreach ($columns as $col) {
    if (strcasecmp((string) ($col['Field'] ?? ''), $name) === 0) {
      return true;
    }
  }
  return false;
}

function cms_prefeditor_field_input_type(string $type): string {
  $type = strtolower(trim($type));
  if ($type === '') {
    return 'text';
  }
  if (str_contains($type, 'textarea') || str_contains($type, 'editor') || str_contains($type, 'html')) {
    return 'textarea';
  }
  if (str_contains($type, 'colour') || str_contains($type, 'color')) {
    return 'color';
  }
  if (str_contains($type, 'date') && str_contains($type, 'time')) {
    return 'datetime-local';
  }
  if (str_contains($type, 'date')) {
    return 'date';
  }
  if (str_contains($type, 'time')) {
    return 'time';
  }
  if (str_contains($type, 'email')) {
    return 'email';
  }
  if (str_contains($type, 'tel') || str_contains($type, 'phone')) {
    return 'tel';
  }
  if (str_contains($type, 'url') || str_contains($type, 'link')) {
    return 'url';
  }
  if (str_contains($type, 'password')) {
    return 'password';
  }
  if (str_contains($type, 'number') || str_contains($type, 'int') || str_contains($type, 'decimal')) {
    return 'number';
  }
  if (str_contains($type, 'check')) {
    return 'checkbox';
  }
  if (str_contains($type, 'radio')) {
    return 'radio';
  }
  if (str_contains($type, 'select') || str_contains($type, 'dropdown')) {
    return 'select';
  }
  return 'text';
}

function cms_prefeditor_resolve_input(array $pref): string {
  $fieldTypeId = (int) ($pref['field'] ?? 0);
  $fieldTypeName = (string) ($pref['field_type'] ?? '');

  if ($fieldTypeId === 2) {
    return 'password';
  }
  if ($fieldTypeId === 3) {
    return 'radio';
  }
  if ($fieldTypeId === 4) {
    return 'checkbox';
  }
  if ($fieldTypeId === 5) {
    return 'color';
  }
  if ($fieldTypeId === 6) {
    return 'date';
  }
  if ($fieldTypeId === 13) {
    return 'time';
  }
  if ($fieldTypeId === 28) {
    return 'datetime-local';
  }
  if ($fieldTypeId === 16 || $fieldTypeId === 18) {
    return 'select';
  }

  return cms_prefeditor_field_input_type($fieldTypeName);
}

function cms_prefeditor_current_role(PDO $pdo, ?array $user): int {
  $userId = (int) ($user['id'] ?? 0);
  if ($userId <= 0 || !cms_prefeditor_table_exists($pdo, 'cms_users')) {
    return 1;
  }
  $stmt = $pdo->prepare('SELECT userrole FROM cms_users WHERE id = :id LIMIT 1');
  $stmt->execute([':id' => $userId]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  return (int) ($row['userrole'] ?? 1);
}

$errors = [];
$saveMessage = null;
$saveError = null;
$rows = [];
$tabs = [];

if (!$DB_OK || !($pdo instanceof PDO)) {
  $errors[] = 'Database connection is unavailable.';
}

$prefTable = null;
$prefCatTable = null;
$fieldTable = null;
$prefColumns = [];
$userRole = 1;
$activeTab = trim((string) ($_POST['active_tab'] ?? $_GET['tab'] ?? ''));

if (!$errors) {
  $prefTable = cms_prefeditor_pick_table($pdo, ['cms_preference', 'cms_preferences']);
  $prefCatTable = cms_prefeditor_pick_table($pdo, ['cms_prefcat', 'cms_prefCat']);
  $fieldTable = cms_prefeditor_pick_table($pdo, ['cms_field']);

  if (!$prefTable) {
    $errors[] = 'Preference table was not found.';
  } else {
    $prefColumns = cms_prefeditor_table_columns($pdo, $prefTable);
  }

  $userRole = cms_prefeditor_current_role($pdo, $CMS_USER);
}

if (!$errors && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_row'])) {
  $rowId = (int) $_POST['save_row'];
  $labels = $_POST['label'] ?? [];
  $values = $_POST['value'] ?? [];
  $newLabel = (string) ($labels[$rowId] ?? '');
  $newValue = (string) ($values[$rowId] ?? '');

  if ($rowId <= 0) {
    $saveError = 'Invalid row selected.';
  } else {
    $filters = [];
    if (cms_prefeditor_has_column($prefColumns, 'archived')) {
      $filters[] = "p.archived = 0";
    }
    if (cms_prefeditor_has_column($prefColumns, 'userlevel')) {
      $filters[] = 'p.userlevel <= :role';
    }
    $whereSql = $filters ? (' AND ' . implode(' AND ', $filters)) : '';

    $checkSql = "SELECT p.id, p.name FROM `{$prefTable}` p WHERE p.id = :id{$whereSql} LIMIT 1";
    $checkStmt = $pdo->prepare($checkSql);
    $params = [':id' => $rowId];
    if (cms_prefeditor_has_column($prefColumns, 'userlevel')) {
      $params[':role'] = $userRole;
    }
    $checkStmt->execute($params);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
      $saveError = 'Row not found or not available for your user level.';
    } else {
      try {
        $upd = $pdo->prepare("UPDATE `{$prefTable}` SET label = :label, value = :value WHERE id = :id LIMIT 1");
        $upd->execute([
          ':label' => $newLabel,
          ':value' => $newValue,
          ':id' => $rowId,
        ]);
        cms_log_action('preferences_update', $prefTable, $rowId, null, 'Preferences', 'cms');
        $savedName = (string) ($existing['name'] ?? ('ID ' . $rowId));
        $savedValueLabel = ($newValue === '') ? '[blank]' : $newValue;
        $saveMessage = 'Saved: ' . $savedName . ' to ' . $savedValueLabel;
      } catch (PDOException $e) {
        $saveError = 'Failed to save row.';
      }
    }
  }
}

if (!$errors) {
  $select = [
    'p.id',
    'p.name',
    'p.label',
    'p.value',
    'p.prefCat',
    'p.field',
    'p.sort',
    'p.comment',
    'p.placeholder',
    'p.required',
    'p.max',
    'p.min',
    'p.step',
  ];

  if ($prefCatTable) {
    $select[] = 'pc.name AS prefcat_name';
  } else {
    $select[] = 'NULL AS prefcat_name';
  }

  if ($fieldTable) {
    $select[] = 'f.type AS field_type';
    $select[] = 'f.title AS field_title';
  } else {
    $select[] = "'' AS field_type";
    $select[] = "'' AS field_title";
  }

  $joins = [];
  if ($prefCatTable) {
    $joins[] = "LEFT JOIN `{$prefCatTable}` pc ON pc.id = p.prefCat";
  }
  if ($fieldTable) {
    $joins[] = "LEFT JOIN `{$fieldTable}` f ON f.id = p.field";
  }

  $where = [];
  $params = [];
  if (cms_prefeditor_has_column($prefColumns, 'archived')) {
    $where[] = 'p.archived = 0';
  }
  if (cms_prefeditor_has_column($prefColumns, 'userlevel')) {
    $where[] = 'p.userlevel <= :role';
    $params[':role'] = $userRole;
  }

  $sql = 'SELECT ' . implode(', ', $select) . " FROM `{$prefTable}` p";
  if ($joins) {
    $sql .= ' ' . implode(' ', $joins);
  }
  if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
  }
  $sql .= ' ORDER BY p.prefCat ASC, p.sort ASC, p.id ASC';

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

  foreach ($rows as $row) {
    $catId = (int) ($row['prefCat'] ?? 0);
    $tabId = 'cat-' . $catId;
    if (!isset($tabs[$tabId])) {
      $catName = trim((string) ($row['prefcat_name'] ?? ''));
      if ($catName === '') {
        $catName = $catId > 0 ? ('Category ' . $catId) : 'General';
      }
      $tabs[$tabId] = [
        'id' => $catId,
        'name' => $catName,
        'rows' => [],
      ];
    }
    $tabs[$tabId]['rows'][] = $row;
  }
}

if (!$tabs) {
  $activeTab = '';
} elseif (!isset($tabs[$activeTab])) {
  $activeTab = (string) array_key_first($tabs);
}
?>
<div class="cms-shell">
  <?php include __DIR__ . '/includes/menu.php'; ?>
  <main class="cms-content">
    <div class="cms-content-header">
      <div>
        <h1 class="h3 mb-1">Preferences Editor</h1>
        <p class="text-muted mb-0">Edit `cms_preferences` labels and values by category.</p>
      </div>
    </div>

    <div class="cms-card">
      <?php if ($saveMessage): ?>
        <div class="alert alert-success"><?php echo cms_h($saveMessage); ?></div>
      <?php endif; ?>
      <?php if ($saveError): ?>
        <div class="alert alert-danger"><?php echo cms_h($saveError); ?></div>
      <?php endif; ?>
      <?php if ($errors): ?>
        <div class="alert alert-danger mb-0">
          <?php foreach ($errors as $error): ?>
            <div><?php echo cms_h($error); ?></div>
          <?php endforeach; ?>
        </div>
      <?php elseif (!$tabs): ?>
        <div class="alert alert-warning mb-0">No preference rows available for your user level.</div>
      <?php else: ?>
        <ul class="nav nav-tabs cms-tabs" role="tablist">
          <?php foreach ($tabs as $tabKey => $tab): ?>
            <li class="nav-item" role="presentation">
              <button class="nav-link <?php echo $tabKey === $activeTab ? 'active' : ''; ?>" id="tab-<?php echo cms_h($tabKey); ?>" data-bs-toggle="tab" data-bs-target="#tab-pane-<?php echo cms_h($tabKey); ?>" type="button" role="tab">
                <?php echo cms_h($tab['name']); ?>
              </button>
            </li>
          <?php endforeach; ?>
        </ul>

        <div class="tab-content pt-4">
          <?php foreach ($tabs as $tabKey => $tab): ?>
            <div class="tab-pane fade <?php echo $tabKey === $activeTab ? 'show active' : ''; ?>" id="tab-pane-<?php echo cms_h($tabKey); ?>" role="tabpanel">
              <form method="post" action="<?php echo $CMS_BASE_URL; ?>/recordPreferencesv5.php">
                <input type="hidden" name="active_tab" value="<?php echo cms_h($tabKey); ?>">
                <div class="table-responsive">
                  <table class="table table-striped table-hover cms-table align-middle">
                    <thead>
                      <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Label</th>
                        <th>Value</th>
                        <th class="text-center">Info</th>
                        <th class="text-end">Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($tab['rows'] as $row): ?>
                        <?php
                          $rowId = (int) ($row['id'] ?? 0);
                          $inputType = cms_prefeditor_resolve_input($row);
                          $placeholder = (string) ($row['placeholder'] ?? '');
                          $min = isset($row['min']) ? (string) $row['min'] : '';
                          $max = isset($row['max']) ? (string) $row['max'] : '';
                          $step = isset($row['step']) ? (string) $row['step'] : '';
                          $comment = trim((string) ($row['comment'] ?? ''));
                          $valueRaw = (string) ($row['value'] ?? '');
                        ?>
                        <tr>
                          <td><?php echo cms_h((string) $rowId); ?></td>
                          <td>
                            <span class="fw-semibold"><?php echo cms_h((string) ($row['name'] ?? '')); ?></span>
                          </td>
                          <td style="min-width: 260px;">
                            <input class="form-control" type="text" name="label[<?php echo $rowId; ?>]" value="<?php echo cms_h((string) ($row['label'] ?? '')); ?>">
                          </td>
                          <td style="min-width: 320px;">
                            <?php if ($inputType === 'textarea'): ?>
                              <textarea class="form-control" name="value[<?php echo $rowId; ?>]" rows="3" placeholder="<?php echo cms_h($placeholder); ?>"><?php echo cms_h($valueRaw); ?></textarea>
                            <?php elseif ($inputType === 'checkbox'): ?>
                              <?php $isYesNo = in_array(strtolower($valueRaw), ['yes', 'no'], true); ?>
                              <select class="form-select" name="value[<?php echo $rowId; ?>]">
                                <?php if ($isYesNo): ?>
                                  <option value="Yes" <?php echo strtolower($valueRaw) === 'yes' ? 'selected' : ''; ?>>Yes</option>
                                  <option value="No" <?php echo strtolower($valueRaw) === 'no' ? 'selected' : ''; ?>>No</option>
                                <?php else: ?>
                                  <option value="1" <?php echo ($valueRaw === '1' || strtolower($valueRaw) === 'true') ? 'selected' : ''; ?>>1</option>
                                  <option value="0" <?php echo ($valueRaw === '0' || strtolower($valueRaw) === 'false') ? 'selected' : ''; ?>>0</option>
                                <?php endif; ?>
                              </select>
                            <?php elseif ($inputType === 'select' || $inputType === 'radio'): ?>
                              <input class="form-control" type="text" name="value[<?php echo $rowId; ?>]" value="<?php echo cms_h($valueRaw); ?>" placeholder="<?php echo cms_h($placeholder); ?>">
                            <?php else: ?>
                              <input class="form-control" type="<?php echo cms_h($inputType); ?>" name="value[<?php echo $rowId; ?>]" value="<?php echo cms_h($valueRaw); ?>" placeholder="<?php echo cms_h($placeholder); ?>"
                                <?php echo $min !== '' ? 'min="' . cms_h($min) . '"' : ''; ?>
                                <?php echo $max !== '' ? 'max="' . cms_h($max) . '"' : ''; ?>
                                <?php echo $step !== '' ? 'step="' . cms_h($step) . '"' : ''; ?>>
                            <?php endif; ?>
                          </td>
                          <td class="text-center">
                            <?php if ($comment !== ''): ?>
                              <span class="cms-tooltip-icon" data-bs-toggle="tooltip" title="<?php echo cms_h($comment); ?>">
                                <i class="fa-solid fa-circle-info"></i>
                              </span>
                            <?php endif; ?>
                          </td>
                          <td class="text-end">
                            <button type="submit" class="btn btn-sm btn-primary" name="save_row" value="<?php echo $rowId; ?>">
                              <i class="fa-solid fa-floppy-disk me-1"></i> Save
                            </button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>
<script>
  (() => {
    const hiddenInputs = document.querySelectorAll('input[name="active_tab"]');
    const tabButtons = document.querySelectorAll('[data-bs-toggle="tab"]');
    tabButtons.forEach((button) => {
      button.addEventListener('shown.bs.tab', () => {
        const target = (button.getAttribute('data-bs-target') || '').replace('#tab-pane-', '');
        hiddenInputs.forEach((input) => {
          input.value = target;
        });
      });
    });
  })();
</script>
<?php include __DIR__ . '/includes/footer-code.php'; ?>
