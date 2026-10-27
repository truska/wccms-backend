<?php
require_once __DIR__ . '/includes/boot.php';
cms_require_login();
include __DIR__ . '/includes/header-code.php';
include __DIR__ . '/includes/header.php';

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

function cms_sort_column(PDO $pdo, string $table, array $candidates = ['sort', 'order', 'position']): ?string {
  $cols = cms_table_columns($pdo, $table);
  if (!$cols) {
    return null;
  }
  return cms_pick_column($cols, $candidates);
}

/**
 * Validate an identifier for safe use in SQL identifiers.
 */
function cms_safe_identifier(string $value): ?string {
  return preg_match('/^[A-Za-z0-9_]+$/', $value) ? $value : null;
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
  return $tableName ? cms_safe_identifier((string) $tableName) : null;
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
 * Fetch form fields for a form, in tab/sort order.
 */
function cms_get_form_fields(PDO $pdo, int $formId): array {
  if (!cms_table_exists($pdo, 'cms_form_field')) {
    return [];
  }
  $sortField = cms_sort_column($pdo, 'cms_form_field', ['sort', 'order']);
  $orderBySort = $sortField ? "`{$sortField}`" : 'id';
  $sql = "SELECT * FROM cms_form_field WHERE form = :form AND showonweb = 'Yes' AND archived = 0 ORDER BY tab ASC, {$orderBySort} ASC, id ASC";
  try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':form' => $formId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (PDOException $e) {
    return [];
  }
}

/**
 * Load active tab definitions for the CMS.
 */
function cms_get_tabs(PDO $pdo): array {
  if (!cms_table_exists($pdo, 'cms_tabs')) {
    return [];
  }
  $sql = "SELECT * FROM cms_tabs WHERE showonweb = 'Yes' ORDER BY sort ASC, id ASC";
  $stmt = $pdo->query($sql);
  return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

/**
 * Load field type definitions keyed by ID.
 */
function cms_get_field_types(PDO $pdo): array {
  if (!cms_table_exists($pdo, 'cms_field')) {
    return [];
  }
  $sql = "SELECT * FROM cms_field WHERE showonweb = 'Yes' AND archived = 0";
  $stmt = $pdo->query($sql);
  $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
  $map = [];
  foreach ($rows as $row) {
    $map[$row['id']] = $row;
  }
  return $map;
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

/**
 * Map a CMS field type label to an HTML input type.
 */
function cms_field_input_type(string $type): string {
  $type = strtolower(trim($type));
  if ($type === '') {
    return 'text';
  }
  if (str_contains($type, 'textarea')) {
    return 'textarea';
  }
  if (str_contains($type, 'colour') || str_contains($type, 'color')) {
    return 'color';
  }
  if (str_contains($type, 'date') && str_contains($type, 'time')) {
    return 'datetime-local';
  }
  if (str_contains($type, 'month')) {
    return 'month';
  }
  if (str_contains($type, 'week')) {
    return 'week';
  }
  if (str_contains($type, 'date')) {
    return 'date';
  }
  if (str_contains($type, 'range')) {
    return 'range';
  }
  if (str_contains($type, 'search')) {
    return 'search';
  }
  if (str_contains($type, 'tel') || str_contains($type, 'phone') || str_contains($type, 'telephone')) {
    return 'tel';
  }
  if (str_contains($type, 'hidden')) {
    return 'hidden';
  }
  if (str_contains($type, 'number') || str_contains($type, 'int') || str_contains($type, 'decimal')) {
    return 'number';
  }
  if (str_contains($type, 'email')) {
    return 'email';
  }
  if (str_contains($type, 'url')) {
    return 'url';
  }
  if (str_contains($type, 'select') || str_contains($type, 'dropdown') || str_contains($type, 'table')) {
    return 'select';
  }
  if (str_contains($type, 'checkbox') || str_contains($type, 'yesno') || str_contains($type, 'boolean')) {
    return 'checkbox';
  }
  return 'text';
}

function cms_oli_is_times_datetime_field(?string $table, string $fieldName): bool {
  return $table === 'oli_times' && in_array($fieldName, ['timefrom', 'timeto'], true);
}

function cms_oli_datetime_for_input($value): string {
  $value = trim((string) $value);
  if ($value === '') {
    return '';
  }
  if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})(?::\d{2})?$/', $value, $matches)) {
    return $matches[1] . 'T' . $matches[2];
  }
  return $value;
}

function cms_oli_datetime_now_for_input(array $field, string $fieldName = ''): string {
  // Let the browser initialise new OLITIME time fields from the user's local clock.
  return '';
}
function cms_oli_datetime_snap_for_mysql(string $value, array $field): string {
  $minutes = isset($field['step']) ? (float) $field['step'] : 0.0;
  if ($minutes <= 0 && (int) ($field['datatype'] ?? 0) === 20) {
    $minutes = 15.0;
  }
  if ($minutes <= 0) {
    return $value;
  }

  $ts = strtotime($value);
  if ($ts === false) {
    return $value;
  }

  $stepSeconds = max(60, (int) round($minutes * 60));
  $snapped = (int) round($ts / $stepSeconds) * $stepSeconds;
  return date('Y-m-d H:i:s', $snapped);
}

function cms_oli_datetime_for_mysql($value, ?array $field = null): string {
  $value = trim((string) $value);
  if ($value === '') {
    return '';
  }
  $value = str_replace('T', ' ', $value);
  if ($field !== null) {
    $value = cms_oli_datetime_snap_for_mysql($value, $field);
  }
  if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
    return $value . ':00';
  }
  return $value;
}
function cms_oli_times_range_error(array $data, ?array $existing = null): ?string {
  $start = trim((string) ($data['timefrom'] ?? ($existing['timefrom'] ?? '')));
  $end = trim((string) ($data['timeto'] ?? ($existing['timeto'] ?? '')));
  if ($start === '' || $end === '') {
    return 'Please enter both Start Time and Finish Time.';
  }

  $startTs = strtotime($start);
  $endTs = strtotime($end);
  if ($startTs === false || $endTs === false) {
    return 'Please enter valid Start Time and Finish Time values.';
  }

  if ($endTs <= $startTs) {
    return 'Finish Time must be after Start Time. Please correct the times before saving.';
  }

  return null;
}
function cms_oli_datetime_step_attr(array $field, string $inputType): ?string {
  if ($inputType !== 'datetime-local' || (int) ($field['field'] ?? 0) !== 28) {
    return isset($field['step']) ? (string) $field['step'] : null;
  }

  $minutes = isset($field['step']) ? (float) $field['step'] : 0.0;
  if ($minutes <= 0 && (int) ($field['datatype'] ?? 0) === 20) {
    $minutes = 15.0;
  }

  return $minutes > 0 ? (string) (int) round($minutes * 60) : null;
}

/**
 * Run a SELECT-only source SQL to build a value/label list.
 */
function cms_run_sourcesql(PDO $pdo, string $sql): array {
  $sql = trim($sql);
  if ($sql === '') {
    return [];
  }
  if (!preg_match('/^\s*select\s/i', $sql)) {
    return [];
  }
  try {
    $stmt = $pdo->query($sql);
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_NUM) : [];
  } catch (PDOException $e) {
    return [];
  }
  $options = [];
  foreach ($rows as $row) {
    if (!isset($row[0])) {
      continue;
    }
    $value = (string) $row[0];
    $label = isset($row[1]) ? (string) $row[1] : (string) $row[0];
    $options[] = ['value' => $value, 'label' => $label];
  }
  return $options;
}

/**
 * Load static options for a form field.
 */
function cms_form_field_options(PDO $pdo, int $formFieldId): array {
  if ($formFieldId <= 0) {
    return [];
  }
  if (!cms_table_exists($pdo, 'cms_form_field_options')) {
    return [];
  }
  $cols = cms_table_columns($pdo, 'cms_form_field_options');
  if (!$cols) {
    return [];
  }
  $nameField = cms_pick_column($cols, ['name', 'label', 'title']);
  $valueField = cms_pick_column($cols, ['value', 'val']);
  $formField = cms_pick_column($cols, ['form_field', 'formfield', 'form_field_id', 'formfield_id']);
  $showField = cms_pick_column($cols, ['showonweb', 'show_on_web']);
  $archivedField = cms_pick_column($cols, ['archived']);
  $sortField = cms_pick_column($cols, ['sort', 'order', 'position']);

  if (!$formField || !$valueField) {
    return [];
  }

  $sql = "SELECT * FROM cms_form_field_options WHERE `{$formField}` = :field";
  if ($archivedField) {
    $sql .= " AND `{$archivedField}` = 0";
  }
  if ($showField) {
    $sql .= " AND `{$showField}` = 'Yes'";
  }
  if ($sortField) {
    $sql .= " ORDER BY `{$sortField}` ASC, id ASC";
  } else {
    $sql .= " ORDER BY id ASC";
  }

  try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':field' => $formFieldId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (PDOException $e) {
    return [];
  }
  $options = [];
  foreach ($rows as $row) {
    if (!isset($row[$valueField])) {
      continue;
    }
    $options[] = [
      'value' => (string) $row[$valueField],
      'label' => ($nameField && isset($row[$nameField])) ? (string) $row[$nameField] : (string) $row[$valueField],
    ];
  }
  return $options;
}

/**
 * Read source SQL from either canonical or legacy column naming.
 */
function cms_field_source_sql(array $field): string {
  return trim((string) ($field['sourcesql'] ?? $field['soursesql'] ?? ''));
}

/**
 * Build select options from a linked table for field type 18.
 */
function cms_table_field_options(PDO $pdo, array $field, ?string $contentTable = null): array {
  $tableId = (int) ($field['table'] ?? 0);
  if ($tableId <= 0) {
    return [];
  }

  $tableName = cms_resolve_table_name($pdo, $tableId);
  if (!$tableName || !cms_table_exists($pdo, $tableName)) {
    return [];
  }

  $columns = cms_table_columns($pdo, $tableName);
  if (!$columns) {
    return [];
  }

  $idField = cms_pick_column($columns, ['id']);
  $labelField = cms_pick_column($columns, ['name', 'title', 'heading', 'label', 'slug', 'email']);
  if (!$idField && !$labelField) {
    return [];
  }
  if (!$idField) {
    $idField = $labelField;
  }
  if (!$labelField) {
    $labelField = $idField;
  }

  if (!preg_match('/^[A-Za-z0-9_]+$/', (string) $idField) || !preg_match('/^[A-Za-z0-9_]+$/', (string) $labelField)) {
    return [];
  }

  $where = [];
  if (cms_pick_column($columns, ['archived'])) {
    $where[] = '`archived` = 0';
  }
  if (cms_pick_column($columns, ['showonweb'])) {
    $where[] = "`showonweb` = 'Yes'";
  }

  $orderParts = [];
  if (cms_pick_column($columns, ['sort'])) {
    $orderParts[] = '`sort` ASC';
  }
  if ($labelField) {
    $orderParts[] = "`{$labelField}` ASC";
  }
  if ($idField) {
    $orderParts[] = "`{$idField}` ASC";
  }

  // Decide whether the edited field expects numeric IDs or text labels.
  $valueField = $idField;
  $targetFieldName = (string) ($field['name'] ?? '');
  $targetTableId = (int) ($field['table'] ?? 0);
  $targetTableName = $targetTableId ? cms_resolve_table_name($pdo, $targetTableId) : $contentTable;
  if ($targetFieldName !== '' && $targetTableName && cms_table_exists($pdo, $targetTableName)) {
    $targetColumns = cms_table_columns($pdo, $targetTableName);
    foreach ($targetColumns as $targetCol) {
      if (($targetCol['Field'] ?? '') !== $targetFieldName) {
        continue;
      }
      $colType = strtolower((string) ($targetCol['Type'] ?? ''));
      $isNumeric = (bool) preg_match('/^(tinyint|smallint|mediumint|int|bigint|decimal|float|double|real|numeric)/', $colType);
      if (!$isNumeric && $labelField) {
        $valueField = $labelField;
      }
      break;
    }
  }

  $sql = "SELECT `{$valueField}` AS value, `{$labelField}` AS label FROM `{$tableName}`";
  if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
  }
  if ($orderParts) {
    $sql .= ' ORDER BY ' . implode(', ', $orderParts);
  }
  $sql .= ' LIMIT 2000';

  try {
    $stmt = $pdo->query($sql);
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
  } catch (PDOException $e) {
    return [];
  }

  $options = [];
  foreach ($rows as $row) {
    if (!array_key_exists('value', $row)) {
      continue;
    }
    $value = (string) ($row['value'] ?? '');
    $label = (string) ($row['label'] ?? $value);
    if ($value === '' && $label === '') {
      continue;
    }
    $options[] = ['value' => $value, 'label' => $label];
  }
  return $options;
}

/**
 * Unified select/radio option resolution with robust fallbacks.
 */
function cms_field_choice_options(PDO $pdo, array $field, int $fieldTypeId, string $sourceSql, ?string $contentTable = null): array {
  if ($fieldTypeId === 17) {
    return [
      ['value' => 'Yes', 'label' => 'Yes'],
      ['value' => 'No', 'label' => 'No'],
    ];
  }

  $options = [];

  // Prefer explicit source SQL when configured.
  if ($sourceSql !== '') {
    $options = cms_run_sourcesql($pdo, $sourceSql);
  }

  // Static option list support.
  if (!$options && $fieldTypeId === 16) {
    $options = cms_form_field_options($pdo, (int) ($field['id'] ?? 0));
  }

  // Table-linked select support.
  if (!$options && $fieldTypeId === 18) {
    $options = cms_table_field_options($pdo, $field, $contentTable);
  }

  // Fallback: still allow static options on non-16 types if configured.
  if (!$options) {
    $options = cms_form_field_options($pdo, (int) ($field['id'] ?? 0));
  }

  return $options;
}

/**
 * Map field layout class to grid column class (currently fixed width).
 */
function cms_field_column_class(?string $class): string {
  return 'col-12';
}

/**
 * Map field width settings to CSS width classes.
 */
function cms_field_width_class(?string $class): string {
  $class = strtolower(trim((string) $class));
  if ($class === '') {
    return 'cms-field-width-xl';
  }
  $class = str_replace(['_', '-'], '', $class);
  if ($class === 'xl' || $class === 'full' || $class === '100') {
    return 'cms-field-width-xl';
  }
  if ($class === 'lg' || $class === 'large' || $class === '75') {
    return 'cms-field-width-lg';
  }
  if ($class === 'md' || $class === 'medium' || $class === '50') {
    return 'cms-field-width-md';
  }
  if ($class === 'sm' || $class === 'small' || $class === '25') {
    return 'cms-field-width-sm';
  }
  if ($class === 'xs' || $class === '10') {
    return 'cms-field-width-xs';
  }
  return 'cms-field-width-xl';
}

$formId = isset($_GET['frm']) ? (int) $_GET['frm'] : 0;
$recordId = 0;
$showDebugRole = (int) cms_pref('prefDebugUserRole', 4, 'cms');
$debugSql = [];
$errors = [];
$saveMessage = null;
$saveError = null;
$form = null;
$contentTable = null;
$record = [];
$fields = [];
$fieldTypes = [];
$tabs = [];
$fieldsByTab = [];
$tabsWithFields = false;

// Guard against missing DB connection early.
if (!$DB_OK || !($pdo instanceof PDO)) {
  $errors[] = 'Database connection is unavailable.';
}

if (!$errors) {
  // Resolve form configuration.
  $form = cms_get_form($pdo, $formId);
  if (!$form) {
    $errors[] = 'Form not found.';
  }
}

if (!$errors && $form) {
  // Resolve content table for the form.
  $contentTable = cms_resolve_table_name($pdo, $form['table'] ?? null);
  if (!$contentTable) {
    $errors[] = 'Content table not resolved for this form.';
  }
}

if (!$errors && $contentTable) {
  // Load field metadata.
  $fields = cms_get_form_fields($pdo, $formId);
  $fieldTypes = cms_get_field_types($pdo);
  $tabs = cms_get_tabs($pdo);
}

if ($DB_OK && $pdo instanceof PDO && isset($CMS_USER['id'])) {
  // Hydrate current user's role for debug access control.
  try {
    $stmt = $pdo->prepare('SELECT userrole FROM cms_users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int) $CMS_USER['id']]);
    $roleValue = $stmt->fetchColumn();
    $CMS_USER['userrole'] = $roleValue !== false && $roleValue !== null ? (int) $roleValue : 1;
  } catch (PDOException $e) {
    $CMS_USER['userrole'] = 1;
  }
}

if (!$errors && $_SERVER['REQUEST_METHOD'] === 'POST') {
  // Reload dependencies if needed for POST handling.
  if (!$form) {
    $form = cms_get_form($pdo, $formId);
  }
  if (!$contentTable && $form) {
    $contentTable = cms_resolve_table_name($pdo, $form['table'] ?? null);
  }
  if (!$fields) {
    $fields = cms_get_form_fields($pdo, $formId);
  }
  if (!$fieldTypes) {
    $fieldTypes = cms_get_field_types($pdo);
  }

  if (!$contentTable) {
    $saveError = 'Content table not resolved.';
  }

  $postFormId = isset($_POST['frm']) ? (int) $_POST['frm'] : 0;
  $postRecordId = isset($_POST['id']) ? (int) $_POST['id'] : 0;

  if ($formId === 0 && $postFormId > 0) {
    $formId = $postFormId;
  }
  if ($recordId === 0 && $postRecordId > 0) {
    $recordId = $postRecordId;
  }

  // Hard guard against tampered form submission.
  if ($postFormId !== $formId) {
    $saveError = 'Invalid form submission.';
    $debugSql[] = 'POST frm mismatch. GET frm=' . $formId . ' POST frm=' . $postFormId;
  } else {
    // Build insert payloads per target table.
    $tablesUpdates = [];
    $tableColumns = [];
    $tablesTouched = [];

    foreach ($fields as $field) {
      if (($field['showonweb'] ?? 'Yes') !== 'Yes' || (int) ($field['archived'] ?? 0) !== 0) {
        continue;
      }
      if (($field['showadd'] ?? 'Yes') !== 'Yes') {
        continue;
      }
      if (($field['allowedit'] ?? 'Yes') !== 'Yes') {
        continue;
      }

      $fieldName = $field['name'] ?? '';
      if ($fieldName === '') {
        continue;
      }

      $tableId = (int) ($field['table'] ?? 0);
      $targetTable = $tableId ? cms_resolve_table_name($pdo, $tableId) : $contentTable;
      if (!$targetTable || !cms_table_exists($pdo, $targetTable)) {
        continue;
      }

      if (!isset($tableColumns[$targetTable])) {
        $tableColumns[$targetTable] = cms_table_columns($pdo, $targetTable);
      }
      $columnNames = array_map(static fn($col) => $col['Field'] ?? '', $tableColumns[$targetTable]);
      if (!in_array($fieldName, $columnNames, true)) {
        continue;
      }

      $fieldTypeId = (int) ($field['field'] ?? 0);
      $typeRow = $fieldTypes[$fieldTypeId] ?? null;
      $typeName = $typeRow['type'] ?? '';
      $inputType = ($fieldTypeId === 16 || $fieldTypeId === 18) ? 'select' : cms_field_input_type($typeName);
      if ($fieldTypeId === 17) {
        $inputType = 'radio';
      }

      $value = $_POST[$fieldName] ?? null;
      if (cms_oli_is_times_datetime_field($targetTable, $fieldName)) {
        $value = cms_oli_datetime_for_mysql($value, $field);
      }
      // Hash password fields when provided; otherwise fall back to an auto-generated password.
      if ($fieldTypeId === 2) {
        $raw = trim((string) ($value ?? ''));
        if ($raw !== '') {
          $value = password_hash($raw, PASSWORD_DEFAULT);
        } else {
          $raw = bin2hex(random_bytes(8));
          $value = password_hash($raw, PASSWORD_DEFAULT);
        }
      }
      // Normalize checkbox values for unchecked states.
      if ($inputType === 'checkbox') {
        if (isset($_POST[$fieldName])) {
          $value = $_POST[$fieldName];
        } else {
          $current = $record[$fieldName] ?? null;
          if ($current === 'Yes' || $current === 'No') {
            $value = 'No';
          } else {
            $value = 0;
          }
        }
      }

      if (!isset($tablesUpdates[$targetTable])) {
        $tablesUpdates[$targetTable] = [];
      }
      $tablesUpdates[$targetTable][$fieldName] = $value;
    }

    $oliTimesRangeError = isset($tablesUpdates['oli_times']) ? cms_oli_times_range_error($tablesUpdates['oli_times'], null) : null;
    if ($oliTimesRangeError) {
      $saveError = $oliTimesRangeError;
    }

    if ($tablesUpdates && !$oliTimesRangeError) {
      try {
        // Insert records table-by-table and log actions.
        $currentFormName = trim((string) ($form['name'] ?? $form['title'] ?? ''));
        foreach ($tablesUpdates as $table => $data) {
          if (!$data) {
            continue;
          }
          $sets = [];
          $params = [];
          $columns = $tableColumns[$table] ?? [];
          $columnNames = array_map(static fn($col) => $col['Field'] ?? '', $columns);
          if (in_array('source_form_id', $columnNames, true) && !isset($data['source_form_id'])) {
            $data['source_form_id'] = $formId;
          }
          if (in_array('source_form_name', $columnNames, true) && !isset($data['source_form_name'])) {
            $data['source_form_name'] = $currentFormName;
          }
          if (in_array('showonweb', $columnNames, true) && !isset($data['showonweb'])) {
            $data['showonweb'] = 'Yes';
          }
          if (in_array('password', $columnNames, true) && !isset($data['password'])) {
            $raw = bin2hex(random_bytes(8));
            $data['password'] = password_hash($raw, PASSWORD_DEFAULT);
          }
          foreach ($data as $name => $val) {
            $paramBase = 'v_' . $table . '_' . $name;
            $paramBase = preg_replace('/[^a-zA-Z0-9_]/', '_', $paramBase);
            $placeholder = ':' . $paramBase;
            $sets[] = "`{$name}` = {$placeholder}";
            $params[$placeholder] = $val;
          }
          $columnsSql = [];
          $valuesSql = [];
          foreach ($data as $name => $val) {
            $paramBase = 'v_' . $table . '_' . $name;
            $paramBase = preg_replace('/[^a-zA-Z0-9_]/', '_', $paramBase);
            $placeholder = ':' . $paramBase;
            $columnsSql[] = "`{$name}`";
            $valuesSql[] = $placeholder;
          }
          $sql = "INSERT INTO `{$table}` (" . implode(', ', $columnsSql) . ") VALUES (" . implode(', ', $valuesSql) . ")";
          $resolved = $sql;
          foreach ($params as $pKey => $pValue) {
            $quoted = $pdo->quote((string) $pValue);
            $resolved = str_replace($pKey, $quoted, $resolved);
          }
          $debugSql[] = $sql;
          $debugSql[] = $resolved;
          $stmt = $pdo->prepare($sql);
          $stmt->execute($params);
          $tablesTouched[] = $table;
          $recordId = (int) $pdo->lastInsertId();
          cms_log_action('record_create', $table, $recordId, $resolved, $form['title'] ?? 'form', 'cms');
        }
        // Redirect to edit view after creation.
        header('Location: ' . $CMS_BASE_URL . '/recordEditOliv5.php?frm=' . urlencode((string) $formId) . '&id=' . urlencode((string) $recordId));
        exit;
      } catch (PDOException $e) {
        $saveError = 'Failed to save changes.';
        $debugSql[] = $e->getMessage();
      }
    }
  }
}

if (!$errors && $fields) {
  // Group fields by tab for rendering.
  foreach ($fields as $field) {
    $tabId = (int) ($field['tab'] ?? 0);
    if (!isset($fieldsByTab[$tabId])) {
      $fieldsByTab[$tabId] = [];
    }
    $fieldsByTab[$tabId][] = $field;
  }
}

if ($tabs) {
  // Check if any tabs have visible add fields.
  foreach ($tabs as $tab) {
    $tabId = (int) ($tab['id'] ?? 0);
    $tabFields = $fieldsByTab[$tabId] ?? [];
    $tabFields = array_values(array_filter($tabFields, static function ($field) {
      return ($field['showadd'] ?? 'Yes') === 'Yes' && (int) ($field['archived'] ?? 0) === 0;
    }));
    if ($tabFields) {
      $tabsWithFields = true;
      break;
    }
  }
}

$formTitle = $form['title'] ?? 'Form';
?>
<div class="cms-shell">
  <?php include __DIR__ . '/includes/menu.php'; ?>
  <main class="cms-content">
    <div class="cms-content-header">
      <div>
        <h1 class="h3 mb-1">Add <?php echo cms_h($formTitle); ?></h1>
        <p class="text-muted mb-0">Table: <?php echo cms_h($contentTable ?? ''); ?></p>
      </div>
      <div>
        <a class="btn btn-outline-secondary" href="<?php echo $CMS_BASE_URL; ?>/recordViewOliv5.php?frm=<?php echo cms_h((string) ($form['id'] ?? $formId)); ?>">Back to list</a>
      </div>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
          <div><?php echo cms_h($error); ?></div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="cms-card">
        <?php if ($saveMessage): ?>
          <div class="alert alert-success"><?php echo cms_h($saveMessage); ?></div>
        <?php endif; ?>
        <?php if ($saveError): ?>
          <div class="alert alert-danger"><?php echo cms_h($saveError); ?></div>
        <?php endif; ?>
        <?php if ($tabs && $tabsWithFields): ?>
          <ul class="nav nav-tabs cms-tabs" role="tablist">
          <?php foreach ($tabs as $index => $tab): ?>
            <?php $tabId = (int) ($tab['id'] ?? 0); ?>
            <?php
            $tabFields = $fieldsByTab[$tabId] ?? [];
            $tabFields = array_values(array_filter($tabFields, static function ($field) {
              return ($field['showadd'] ?? 'Yes') === 'Yes' && (int) ($field['archived'] ?? 0) === 0;
            }));
            if (!$tabFields) { continue; }
            ?>
            <?php $tabIcon = cms_icon_class($pdo, $tab['icon'] ?? null); ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $index === 0 ? 'active' : ''; ?>" id="tab-<?php echo $tabId; ?>" data-bs-toggle="tab" data-bs-target="#tab-pane-<?php echo $tabId; ?>" type="button" role="tab">
                  <?php if ($tabIcon): ?>
                    <i class="<?php echo cms_h($tabIcon); ?> me-1"></i>
                  <?php endif; ?>
                  <?php echo cms_h($tab['name'] ?? 'Tab'); ?>
                </button>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <?php $activeTab = $_POST['active_tab'] ?? ''; ?>
        <form class="pt-4" method="post" data-form-id="<?php echo cms_h((string) $formId); ?>" data-record-id="new" action="<?php echo $CMS_BASE_URL; ?>/recordNewOliv5.php?frm=<?php echo cms_h((string) ($form['id'] ?? $formId)); ?>">
          <input type="hidden" name="frm" value="<?php echo cms_h((string) ($form['id'] ?? $formId)); ?>">
          <input type="hidden" name="active_tab" value="<?php echo cms_h((string) $activeTab); ?>">
          <?php if ($tabs && $tabsWithFields): ?>
            <div class="tab-content">
              <?php foreach ($tabs as $index => $tab): ?>
                <?php $tabId = (int) ($tab['id'] ?? 0); ?>
                <?php
                $tabFields = $fieldsByTab[$tabId] ?? [];
                $tabFields = array_values(array_filter($tabFields, static function ($field) {
                  return ($field['showadd'] ?? 'Yes') === 'Yes' && (int) ($field['archived'] ?? 0) === 0;
                }));
                if (!$tabFields) { continue; }
                ?>
                <div class="tab-pane fade <?php echo $index === 0 ? 'show active' : ''; ?>" id="tab-pane-<?php echo $tabId; ?>" role="tabpanel">
                  <div class="row g-4">
                    <?php foreach ($tabFields as $field): ?>
                      <?php
                      if (($field['showadd'] ?? 'Yes') !== 'Yes' || (int) ($field['archived'] ?? 0) !== 0) {
                        continue;
                      }
                      $fieldName = $field['name'] ?? '';
                      if ($fieldName === '') {
                        continue;
                      }
                      $fieldName = $field['name'] ?? '';
                      $fieldLabel = $field['label'] ?? $field['title'] ?? $fieldName;
                      $fieldTypeId = (int) ($field['field'] ?? 0);
                      $typeRow = $fieldTypes[$fieldTypeId] ?? null;
                      $typeName = $typeRow['type'] ?? '';
                      if ($fieldTypeId === 2) {
                        $inputType = 'password';
                      } elseif ($fieldTypeId === 3 || $fieldTypeId === 17) {
                        $inputType = 'radio';
                      } elseif ($fieldTypeId === 4) {
                        $inputType = 'checkbox';
                      } elseif ($fieldTypeId === 5) {
                        $inputType = 'color';
                      } elseif ($fieldTypeId === 6) {
                        $inputType = 'date';
                      } elseif ($fieldTypeId === 7) {
                        $inputType = 'email';
                      } elseif ($fieldTypeId === 28) {
                        $inputType = 'datetime-local';
                      } elseif ($fieldTypeId === 13) {
                        $inputType = 'time';
                      } else {
                        $inputType = ($fieldTypeId === 16 || $fieldTypeId === 18) ? 'select' : cms_field_input_type($typeName);
                      }
                      $value = $fieldName && isset($_POST[$fieldName]) ? $_POST[$fieldName] : ($field['value'] ?? '');
                      if (cms_oli_is_times_datetime_field($contentTable, $fieldName)) {
                        $value = cms_oli_datetime_for_input($value);
                        if ($value === '') {
                          $value = cms_oli_datetime_now_for_input($field, $fieldName);
                        }
                      }
                      $required = ($field['required'] ?? 'No') === 'Yes';
                      $allowEdit = ($field['allowedit'] ?? 'Yes') === 'Yes';
                      $placeholder = $field['placeholder'] ?? '';
                      $min = $field['min'] ?? null;
                      $max = $field['max'] ?? null;
                      $step = cms_oli_datetime_step_attr($field, $inputType);
                      $tooltip = $field['tooltip'] ?? '';
                      $comment = $field['comment'] ?? '';
                      $datalistRaw = $field['datalist'] ?? '';
                      $sourceSql = cms_field_source_sql($field);
                      ?>
                      <?php $colClass = cms_field_column_class($field['class'] ?? ''); ?>
                      <div class="<?php echo cms_h($colClass); ?>">
                        <label class="form-label d-flex align-items-center gap-2" for="field-<?php echo cms_h($fieldName); ?>">
                          <span><?php echo cms_h($fieldLabel); ?></span>
                          <?php if ($tooltip): ?>
                            <span class="cms-tooltip-icon" data-bs-toggle="tooltip" title="<?php echo cms_h($tooltip); ?>">
                              <i class="fa-solid fa-circle-info"></i>
                            </span>
                          <?php endif; ?>
                        </label>
                      <?php if ($inputType === 'radio'): ?>
                        <?php
                            $options = cms_field_choice_options($pdo, $field, $fieldTypeId, (string) $sourceSql, $contentTable);
                        ?>
                        <div class="d-flex flex-column gap-2">
                          <?php foreach ($options as $option): ?>
                            <?php $optionId = 'field-' . $field['id'] . '-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $option['value']); ?>
                            <div class="form-check">
                              <input class="form-check-input" type="radio" id="<?php echo cms_h($optionId); ?>" name="<?php echo cms_h($fieldName); ?>" value="<?php echo cms_h($option['value']); ?>" <?php echo ((string) $option['value'] === (string) $value) ? 'checked' : ''; ?> <?php echo $required ? 'required' : ''; ?> <?php echo $allowEdit ? '' : 'disabled'; ?>>
                              <label class="form-check-label" for="<?php echo cms_h($optionId); ?>"><?php echo cms_h($option['label']); ?></label>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      <?php elseif ($inputType === 'textarea'): ?>
                        <?php $widthClass = cms_field_width_class($field['class'] ?? ''); ?>
                        <?php $textareaClass = trim('form-control ' . $widthClass . ($fieldTypeId === 19 ? ' cms-tinymce' : '')); ?>
                        <?php $rows = (int) ($field['row'] ?? 5); ?>
                        <textarea class="<?php echo cms_h($textareaClass); ?>" id="field-<?php echo cms_h($fieldName); ?>" name="<?php echo cms_h($fieldName); ?>" rows="<?php echo $rows > 0 ? $rows : 5; ?>" placeholder="<?php echo cms_h($placeholder); ?>" <?php echo $required ? 'required' : ''; ?> <?php echo $allowEdit ? '' : 'disabled'; ?>><?php echo cms_h((string) $value); ?></textarea>
                        <?php elseif ($inputType === 'select'): ?>
                          <?php $options = cms_field_choice_options($pdo, $field, $fieldTypeId, (string) $sourceSql, $contentTable); ?>
                          <?php $widthClass = cms_field_width_class($field['class'] ?? ''); ?>
                          <select class="form-select <?php echo cms_h($widthClass); ?>" id="field-<?php echo cms_h($fieldName); ?>" name="<?php echo cms_h($fieldName); ?>" <?php echo $required ? 'required' : ''; ?> <?php echo $allowEdit ? '' : 'disabled'; ?>>
                            <option value="">Select...</option>
                            <?php foreach ($options as $option): ?>
                              <option value="<?php echo cms_h($option['value']); ?>" <?php echo ((string) $option['value'] === (string) $value) ? 'selected' : ''; ?>>
                                <?php echo cms_h($option['label']); ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        <?php elseif ($inputType === 'checkbox'): ?>
                          <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="field-<?php echo cms_h($fieldName); ?>" name="<?php echo cms_h($fieldName); ?>" value="1" <?php echo ((string) $value === '1' || (string) $value === 'Yes') ? 'checked' : ''; ?> <?php echo $allowEdit ? '' : 'disabled'; ?>>
                            <label class="form-check-label" for="field-<?php echo cms_h($fieldName); ?>"><?php echo cms_h($fieldLabel); ?></label>
                          </div>
                        <?php else: ?>
                          <?php $widthClass = cms_field_width_class($field['class'] ?? ''); ?>
                          <?php $datalistId = $datalistRaw ? ('datalist-' . $field['id']) : ''; ?>
                          <input class="form-control <?php echo cms_h($widthClass); ?>" type="<?php echo cms_h($inputType); ?>" id="field-<?php echo cms_h($fieldName); ?>" name="<?php echo cms_h($fieldName); ?>" value="<?php echo cms_h((string) $value); ?>" placeholder="<?php echo cms_h($placeholder); ?>" <?php echo $required ? 'required' : ''; ?> <?php echo $allowEdit ? '' : 'disabled'; ?>
                            <?php echo $min !== null ? 'min="' . cms_h((string) $min) . '"' : ''; ?>
                            <?php echo $max !== null ? 'max="' . cms_h((string) $max) . '"' : ''; ?>
                            <?php echo $step !== null ? 'step="' . cms_h((string) $step) . '"' : ''; ?>
                            <?php echo $datalistId ? 'list="' . cms_h($datalistId) . '"' : ''; ?>
                          >
                          <?php if ($datalistId): ?>
                            <datalist id="<?php echo cms_h($datalistId); ?>">
                              <?php foreach (array_filter(array_map('trim', explode(',', (string) $datalistRaw))) as $item): ?>
                                <option value="<?php echo cms_h($item); ?>" label="<?php echo cms_h($item); ?>"></option>
                              <?php endforeach; ?>
                            </datalist>
                          <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($comment): ?>
                          <div class="form-text"><?php echo cms_h($comment); ?></div>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="row g-4">
              <?php foreach ($fields as $field): ?>
                <?php
                if (($field['showadd'] ?? 'Yes') !== 'Yes' || (int) ($field['archived'] ?? 0) !== 0) {
                  continue;
                }
                $fieldName = $field['name'] ?? '';
                $fieldLabel = $field['label'] ?? $field['title'] ?? $fieldName;
                $fieldTypeId = (int) ($field['field'] ?? 0);
                $typeRow = $fieldTypes[$fieldTypeId] ?? null;
                $typeName = $typeRow['type'] ?? '';
                if ($fieldTypeId === 2) {
                  $inputType = 'password';
                } elseif ($fieldTypeId === 3 || $fieldTypeId === 17) {
                  $inputType = 'radio';
                } elseif ($fieldTypeId === 4) {
                  $inputType = 'checkbox';
                } elseif ($fieldTypeId === 5) {
                  $inputType = 'color';
                } elseif ($fieldTypeId === 6) {
                  $inputType = 'date';
                } elseif ($fieldTypeId === 7) {
                  $inputType = 'email';
                } elseif ($fieldTypeId === 28) {
                  $inputType = 'datetime-local';
                } elseif ($fieldTypeId === 13) {
                  $inputType = 'time';
                } else {
                  $inputType = ($fieldTypeId === 16 || $fieldTypeId === 18) ? 'select' : cms_field_input_type($typeName);
                }
                $value = $fieldName && isset($_POST[$fieldName]) ? $_POST[$fieldName] : ($field['value'] ?? '');
                if (cms_oli_is_times_datetime_field($contentTable, $fieldName)) {
                  $value = cms_oli_datetime_for_input($value);
                  if ($value === '') {
                    $value = cms_oli_datetime_now_for_input($field, $fieldName);
                  }
                }
                $required = ($field['required'] ?? 'No') === 'Yes';
                $allowEdit = ($field['allowedit'] ?? 'Yes') === 'Yes';
                $placeholder = $field['placeholder'] ?? '';
                $comment = $field['comment'] ?? '';
                $tooltip = $field['tooltip'] ?? '';
                $datalistRaw = $field['datalist'] ?? '';
                $sourceSql = cms_field_source_sql($field);
                $min = $field['min'] ?? null;
                $max = $field['max'] ?? null;
                $step = cms_oli_datetime_step_attr($field, $inputType);
                ?>
                <?php $colClass = cms_field_column_class($field['class'] ?? ''); ?>
                <div class="<?php echo cms_h($colClass); ?>">
                  <label class="form-label d-flex align-items-center gap-2" for="field-<?php echo cms_h($fieldName); ?>">
                    <span><?php echo cms_h($fieldLabel); ?></span>
                    <?php if ($tooltip): ?>
                      <span class="cms-tooltip-icon" data-bs-toggle="tooltip" title="<?php echo cms_h($tooltip); ?>">
                        <i class="fa-solid fa-circle-info"></i>
                      </span>
                    <?php endif; ?>
                  </label>
                  <?php if ($inputType === 'radio'): ?>
                    <?php
                        $options = cms_field_choice_options($pdo, $field, $fieldTypeId, (string) $sourceSql, $contentTable);
                    ?>
                    <div class="d-flex flex-column gap-2">
                      <?php foreach ($options as $option): ?>
                        <?php $optionId = 'field-' . $field['id'] . '-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $option['value']); ?>
                        <div class="form-check">
                          <input class="form-check-input" type="radio" id="<?php echo cms_h($optionId); ?>" name="<?php echo cms_h($fieldName); ?>" value="<?php echo cms_h($option['value']); ?>" <?php echo ((string) $option['value'] === (string) $value) ? 'checked' : ''; ?> <?php echo $required ? 'required' : ''; ?> <?php echo $allowEdit ? '' : 'disabled'; ?>>
                          <label class="form-check-label" for="<?php echo cms_h($optionId); ?>"><?php echo cms_h($option['label']); ?></label>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php elseif ($inputType === 'textarea'): ?>
                    <?php $widthClass = cms_field_width_class($field['class'] ?? ''); ?>
                    <?php $textareaClass = trim('form-control ' . $widthClass . ($fieldTypeId === 19 ? ' cms-tinymce' : '')); ?>
                    <?php $rows = (int) ($field['row'] ?? 5); ?>
                    <textarea class="<?php echo cms_h($textareaClass); ?>" id="field-<?php echo cms_h($fieldName); ?>" name="<?php echo cms_h($fieldName); ?>" rows="<?php echo $rows > 0 ? $rows : 5; ?>" placeholder="<?php echo cms_h($placeholder); ?>" <?php echo $required ? 'required' : ''; ?> <?php echo $allowEdit ? '' : 'disabled'; ?>><?php echo cms_h((string) $value); ?></textarea>
                  <?php elseif ($inputType === 'select'): ?>
                    <?php $options = cms_field_choice_options($pdo, $field, $fieldTypeId, (string) $sourceSql, $contentTable); ?>
                    <?php $widthClass = cms_field_width_class($field['class'] ?? ''); ?>
                    <select class="form-select <?php echo cms_h($widthClass); ?>" id="field-<?php echo cms_h($fieldName); ?>" name="<?php echo cms_h($fieldName); ?>" <?php echo $required ? 'required' : ''; ?> <?php echo $allowEdit ? '' : 'disabled'; ?>>
                      <option value="">Select...</option>
                      <?php foreach ($options as $option): ?>
                        <option value="<?php echo cms_h($option['value']); ?>" <?php echo ((string) $option['value'] === (string) $value) ? 'selected' : ''; ?>>
                          <?php echo cms_h($option['label']); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  <?php elseif ($inputType === 'checkbox'): ?>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="field-<?php echo cms_h($fieldName); ?>" name="<?php echo cms_h($fieldName); ?>" value="1" <?php echo ((string) $value === '1' || (string) $value === 'Yes') ? 'checked' : ''; ?> <?php echo $allowEdit ? '' : 'disabled'; ?>>
                      <label class="form-check-label" for="field-<?php echo cms_h($fieldName); ?>"><?php echo cms_h($fieldLabel); ?></label>
                    </div>
                  <?php else: ?>
                    <?php $widthClass = cms_field_width_class($field['class'] ?? ''); ?>
                    <?php $datalistId = $datalistRaw ? ('datalist-' . $field['id']) : ''; ?>
                    <input class="form-control <?php echo cms_h($widthClass); ?>" type="<?php echo cms_h($inputType); ?>" id="field-<?php echo cms_h($fieldName); ?>" name="<?php echo cms_h($fieldName); ?>" value="<?php echo cms_h((string) $value); ?>" placeholder="<?php echo cms_h($placeholder); ?>" <?php echo $required ? 'required' : ''; ?> <?php echo $allowEdit ? '' : 'disabled'; ?>
                      <?php echo $min !== null ? 'min="' . cms_h((string) $min) . '"' : ''; ?>
                      <?php echo $max !== null ? 'max="' . cms_h((string) $max) . '"' : ''; ?>
                      <?php echo $step !== null ? 'step="' . cms_h((string) $step) . '"' : ''; ?>
                      <?php echo $datalistId ? 'list="' . cms_h($datalistId) . '"' : ''; ?>
                    >
                    <?php if ($datalistId): ?>
                      <datalist id="<?php echo cms_h($datalistId); ?>">
                        <?php foreach (array_filter(array_map('trim', explode(',', (string) $datalistRaw))) as $item): ?>
                          <option value="<?php echo cms_h($item); ?>" label="<?php echo cms_h($item); ?>"></option>
                        <?php endforeach; ?>
                      </datalist>
                    <?php endif; ?>
                  <?php endif; ?>
                  <?php if ($comment): ?>
                    <div class="form-text"><?php echo cms_h($comment); ?></div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <button type="submit" class="btn cms-save-button">
            <i class="fa-solid fa-floppy-disk me-1"></i> Save
          </button>
        </form>
        <?php if (($CMS_USER['userrole'] ?? 1) >= $showDebugRole): ?>
          <div class="alert alert-warning mt-4 d-none cms-client-debug" aria-live="polite">
            <strong>Client Debug</strong>
            <pre class="bg-light border rounded p-3 mb-0 cms-client-debug-pre"></pre>
          </div>
        <?php endif; ?>
        <?php if (!empty($debugSql) && (($CMS_USER['userrole'] ?? 1) >= $showDebugRole)): ?>
          <div class="alert alert-info mt-4">
            <strong>Debug</strong>
            <pre class="bg-light border rounded p-3 mb-0"><?php echo cms_h(implode("\n", $debugSql)); ?></pre>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </main>
</div>
<style>
.cms-oli-datetime {
  display: grid;
  grid-template-columns: minmax(9.5rem, 12rem) 4.5rem 4.5rem;
  gap: 0.5rem;
  align-items: center;
  max-width: 22rem;
}
.cms-oli-datetime .cms-oli-datetime-date,
.cms-oli-datetime .cms-oli-datetime-select {
  width: 100%;
  min-width: 0;
}
.cms-oli-datetime .cms-oli-datetime-select {
  text-align: center;
  padding-left: 0.5rem;
  padding-right: 1.75rem;
}
@media (max-width: 420px) {
  .cms-oli-datetime {
    grid-template-columns: minmax(8.5rem, 1fr) 4.25rem 4.25rem;
    max-width: 100%;
  }
}
</style>
<script>
(function () {
  function pad(value) {
    return String(value).padStart(2, '0');
  }

  function formatLocal(parts) {
    return parts.date + 'T' + pad(parts.hour) + ':' + pad(parts.minute);
  }

  function parseLocal(value) {
    const match = String(value || '').match(/^(\d{4}-\d{2}-\d{2})T(\d{2}):(\d{2})/);
    if (match) {
      return { date: match[1], hour: parseInt(match[2], 10), minute: parseInt(match[3], 10) };
    }
    const now = new Date();
    return {
      date: now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()),
      hour: now.getHours(),
      minute: now.getMinutes()
    };
  }

  function snapMinute(minute, stepMinutes) {
    return Math.round(minute / stepMinutes) * stepMinutes % 60;
  }

  function buildSelect(values, selected) {
    const select = document.createElement('select');
    select.className = 'form-select cms-oli-datetime-select';
    values.forEach(function (value) {
      const option = document.createElement('option');
      option.value = pad(value);
      option.textContent = pad(value);
      if (value === selected) {
        option.selected = true;
      }
      select.appendChild(option);
    });
    return select;
  }

  const oliDateTimeControls = {};

  function addDays(dateValue, days) {
    const parts = String(dateValue || '').split('-').map(Number);
    if (parts.length !== 3 || parts.some(Number.isNaN)) {
      return dateValue;
    }
    const date = new Date(parts[0], parts[1] - 1, parts[2]);
    date.setDate(date.getDate() + days);
    return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
  }

  function isNightType() {
    const typeField = document.querySelector('[name="type"]');
    return typeField && String(typeField.value) === '2';
  }

  function syncEndDateToStart() {
    const start = oliDateTimeControls.timefrom;
    const end = oliDateTimeControls.timeto;
    if (!start || !end || !start.date.value) {
      return;
    }
    end.date.value = addDays(start.date.value, isNightType() ? 1 : 0);
    end.sync();
  }

  function enhanceDatetimeInput(input) {
    const stepSeconds = parseInt(input.getAttribute('step') || '0', 10);
    if (!stepSeconds || stepSeconds < 60 || input.dataset.oliDatetimeEnhanced === '1') {
      return;
    }

    const stepMinutes = Math.max(1, Math.round(stepSeconds / 60));
    const minuteValues = [];
    for (let minute = 0; minute < 60; minute += stepMinutes) {
      minuteValues.push(minute);
    }
    if (minuteValues.length >= 60) {
      return;
    }

    const parts = parseLocal(input.value);
    if (input.name === 'timeto' && !input.value) {
      parts.minute = 0;
    } else {
      parts.minute = snapMinute(parts.minute, stepMinutes);
    }
    input.value = formatLocal(parts);

    const date = document.createElement('input');
    date.type = 'date';
    date.className = input.className + ' cms-oli-datetime-date';
    date.value = parts.date;
    if (input.required) {
      date.required = true;
    }
    if (input.disabled) {
      date.disabled = true;
    }

    const hour = buildSelect(Array.from({ length: 24 }, function (_, index) { return index; }), parts.hour);
    const minute = buildSelect(minuteValues, parts.minute);
    if (input.disabled) {
      hour.disabled = true;
      minute.disabled = true;
    }

    const wrap = document.createElement('div');
    wrap.className = 'cms-oli-datetime';
    wrap.appendChild(date);
    wrap.appendChild(hour);
    wrap.appendChild(minute);

    function sync() {
      input.value = date.value + 'T' + hour.value + ':' + minute.value;
    }

    date.addEventListener('change', function () {
      sync();
      if (input.name === 'timefrom') {
        syncEndDateToStart();
      }
    });
    hour.addEventListener('change', sync);
    minute.addEventListener('change', sync);

    oliDateTimeControls[input.name] = { date: date, hour: hour, minute: minute, sync: sync };

    input.type = 'hidden';
    input.dataset.oliDatetimeEnhanced = '1';
    input.insertAdjacentElement('afterend', wrap);
    sync();
  }

  document.querySelectorAll('input[type="datetime-local"][step]').forEach(enhanceDatetimeInput);
  const typeField = document.querySelector('[name="type"]');
  if (typeField) {
    typeField.addEventListener('change', syncEndDateToStart);
  }
  syncEndDateToStart();
})();
</script>
<?php include __DIR__ . '/includes/footer-code.php'; ?>
