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
  $sql = "SELECT * FROM cms_form_field WHERE form = :form AND showonweb = 'Yes' AND archived = 0 ORDER BY tab ASC, sort ASC, id ASC";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':form' => $formId]);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

function cms_normalize_files(array $fileSpec): array {
  $files = [];
  if (is_array($fileSpec['name'])) {
    $count = count($fileSpec['name']);
    for ($i = 0; $i < $count; $i++) {
      $files[] = [
        'name' => $fileSpec['name'][$i] ?? '',
        'type' => $fileSpec['type'][$i] ?? '',
        'tmp_name' => $fileSpec['tmp_name'][$i] ?? '',
        'error' => $fileSpec['error'][$i] ?? UPLOAD_ERR_NO_FILE,
        'size' => $fileSpec['size'][$i] ?? 0,
      ];
    }
  } else {
    $files[] = $fileSpec;
  }
  return $files;
}

function cms_next_gallery_sort(PDO $pdo, int $formId, int $recordId): int {
  if (!cms_table_exists($pdo, 'gallery')) {
    return 0;
  }
  $stmt = $pdo->prepare('SELECT MAX(sort) AS max_sort FROM gallery WHERE form_id = :form AND record_id = :record');
  $stmt->execute([':form' => $formId, ':record' => $recordId]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  $maxSort = isset($row['max_sort']) ? (int) $row['max_sort'] : 0;
  return $maxSort + 1;
}

function cms_fetch_gallery_items(PDO $pdo, int $formId, int $recordId): array {
  if (!cms_table_exists($pdo, 'gallery')) {
    return [];
  }
  $stmt = $pdo->prepare('SELECT * FROM gallery WHERE form_id = :form AND record_id = :record AND archived = 0 ORDER BY sort ASC, id ASC');
  $stmt->execute([':form' => $formId, ':record' => $recordId]);
  return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
  if (str_contains($type, 'date')) {
    return 'date';
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

/**
 * Normalize yes/no style flags stored as Yes/No or 1/0.
 */
function cms_is_yes($value): bool {
  if (is_bool($value)) {
    return $value;
  }
  if (is_numeric($value)) {
    return (int) $value === 1;
  }
  $value = strtolower(trim((string) $value));
  return in_array($value, ['yes', 'y', 'true', '1'], true);
}

$formId = isset($_GET['frm']) ? (int) $_GET['frm'] : 0;
$recordId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$showDebugRole = (int) cms_pref('prefDebugUserRole', 4, 'cms');
$debugSql = [];
$errors = [];
$saveMessage = null;
$saveError = null;
$form = null;
$contentTable = null;
$record = null;
$fields = [];
$fieldTypes = [];
$tabs = [];
$fieldsByTab = [];

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
  // Load record data and field metadata.
  $record = cms_fetch_record($pdo, $contentTable, $recordId);
  if (!$record) {
    $errors[] = 'Record not found.';
  }
  $fields = cms_get_form_fields($pdo, $formId);
  $fieldTypes = cms_get_field_types($pdo);
  $tabs = cms_get_tabs($pdo);
  $galleryItems = cms_fetch_gallery_items($pdo, $formId, $recordId);
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
if ($postFormId !== $formId || $postRecordId !== $recordId) {
  $saveError = 'Invalid form submission.';
  $debugSql[] = 'POST frm/id mismatch. GET frm=' . $formId . ' id=' . $recordId . ' POST frm=' . $postFormId . ' id=' . $postRecordId;
} else {
    // Build update payloads per target table.
    $tablesUpdates = [];
    $tableColumns = [];
    $tablesTouched = [];
    $uploadUpdates = [];
    $uploadErrors = [];

    $galleryItems = cms_fetch_gallery_items($pdo, $formId, $recordId);
    $galleryIds = array_map(static fn($row) => (int) ($row['id'] ?? 0), $galleryItems);

    foreach ($fields as $field) {
      $fieldTypeId = (int) ($field['field'] ?? 0);
      if (!in_array($fieldTypeId, [21, 23], true)) {
        continue;
      }
      if (!cms_is_yes($field['showonweb'] ?? 'Yes') || (int) ($field['archived'] ?? 0) !== 0) {
        continue;
      }
      if (!cms_is_yes($field['allowedit'] ?? 'Yes')) {
        continue;
      }
      $inputKey = 'cms_file_' . (int) ($field['id'] ?? 0);
      if ($inputKey === 'cms_file_0' || empty($_FILES[$inputKey])) {
        continue;
      }
      $files = cms_normalize_files($_FILES[$inputKey]);
      foreach ($files as $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
          continue;
        }
        $fieldForUpload = $field;
        $hasSizes = ((int) ($field['xs_max_width'] ?? 0) > 0)
          || ((int) ($field['sm_max_width'] ?? 0) > 0)
          || ((int) ($field['md_max_width'] ?? 0) > 0)
          || ((int) ($field['lg_max_width'] ?? 0) > 0);
        if (($field['resize_status'] ?? 'No') !== 'Yes' && !$hasSizes) {
          $fieldForUpload['xs_max_width'] = 0;
          $fieldForUpload['sm_max_width'] = 0;
          $fieldForUpload['md_max_width'] = 0;
          $fieldForUpload['lg_max_width'] = 0;
        }
        $stored = cms_media_store_upload($file, $fieldForUpload, $record, $formId, $recordId, [
          'store_original' => true,
          'create_webp' => true,
        ]);
        if (!$stored['ok']) {
          foreach ($stored['errors'] as $err) {
            $uploadErrors[] = $err;
          }
          continue;
        }

        $debugSql[] = 'Upload stored: ' . $stored['filename']
          . ' sizes=[' . implode(',', $stored['sizes']) . ']'
          . ' master=' . ($stored['master_written'] ? 'yes' : 'no');

        $fieldName = $field['name'] ?? '';
        if ($fieldTypeId === 21 && $fieldName !== '') {
          $tableId = (int) ($field['table'] ?? 0);
          $targetTable = $tableId ? cms_resolve_table_name($pdo, $tableId) : $contentTable;
          if ($targetTable && cms_table_exists($pdo, $targetTable)) {
            if (!isset($tableColumns[$targetTable])) {
              $tableColumns[$targetTable] = cms_table_columns($pdo, $targetTable);
            }
            $columnNames = array_map(static fn($col) => $col['Field'] ?? '', $tableColumns[$targetTable]);
            if (in_array($fieldName, $columnNames, true)) {
              if (!isset($uploadUpdates[$targetTable])) {
                $uploadUpdates[$targetTable] = [];
              }
              $uploadUpdates[$targetTable][$fieldName] = $stored['filename'];
            }
          }
        }

        if ($fieldTypeId === 23 && cms_table_exists($pdo, 'gallery')) {
          $sort = cms_next_gallery_sort($pdo, $formId, $recordId);
          $folderName = $stored['mediatype'];
          if ($stored['folder'] !== '') {
            $folderName .= '/' . $stored['folder'];
          }
          $name = pathinfo($stored['filename'], PATHINFO_FILENAME);
          $stmt = $pdo->prepare('INSERT INTO gallery (record_id, form_id, form_name, name, alttag, caption, date, layout, image, folder_name, slug, category, keyword, sort, showonweb, archived) VALUES (:record, :form, :form_name, :name, :alttag, :caption, :date, :layout, :image, :folder, :slug, :category, :keyword, :sort, :showonweb, 0)');
          $stmt->execute([
            ':record' => $recordId,
            ':form' => $formId,
            ':form_name' => (string) ($form['name'] ?? $form['title'] ?? ''),
            ':name' => $name,
            ':alttag' => '',
            ':caption' => '',
            ':date' => date('Y-m-d'),
            ':layout' => 0,
            ':image' => $stored['filename'],
            ':folder' => $folderName,
            ':slug' => '',
            ':category' => '',
            ':keyword' => '',
            ':sort' => $sort,
            ':showonweb' => 'Yes',
          ]);
        }
      }
    }

    if ($galleryIds) {
      $altUpdates = $_POST['gallery_alttag'] ?? [];
      $captionUpdates = $_POST['gallery_caption'] ?? [];
      $showUpdates = $_POST['gallery_showonweb'] ?? [];
      $deleteUpdates = $_POST['gallery_delete'] ?? [];

      foreach ($galleryIds as $gid) {
        if ($gid <= 0) {
          continue;
        }
        if (isset($deleteUpdates[$gid]) && (string) $deleteUpdates[$gid] === '1') {
          $stmt = $pdo->prepare('UPDATE gallery SET archived = 1, showonweb = :show WHERE id = :id');
          $stmt->execute([':id' => $gid, ':show' => 'No']);
          continue;
        }

        $alt = isset($altUpdates[$gid]) ? trim((string) $altUpdates[$gid]) : '';
        $caption = isset($captionUpdates[$gid]) ? trim((string) $captionUpdates[$gid]) : '';
        $show = isset($showUpdates[$gid]) ? 'Yes' : 'No';

        $stmt = $pdo->prepare('UPDATE gallery SET alttag = :alt, caption = :caption, showonweb = :show WHERE id = :id');
        $stmt->execute([
          ':alt' => $alt,
          ':caption' => $caption,
          ':show' => $show,
          ':id' => $gid,
        ]);
      }

      if (!empty($_POST['gallery_order'])) {
        $order = array_filter(array_map('intval', explode(',', (string) $_POST['gallery_order'])));
        $sortPos = 1;
        foreach ($order as $gid) {
          if (!in_array($gid, $galleryIds, true)) {
            continue;
          }
          $stmt = $pdo->prepare('UPDATE gallery SET sort = :sort WHERE id = :id');
          $stmt->execute([':sort' => $sortPos, ':id' => $gid]);
          $sortPos++;
        }
      }
    }

    if ($uploadErrors) {
      $summary = array_slice($uploadErrors, 0, 3);
      $saveError = 'File upload failed: ' . implode(' ', $summary);
    }

    foreach ($fields as $field) {
      if (!cms_is_yes($field['showonweb'] ?? 'Yes') || (int) ($field['archived'] ?? 0) !== 0) {
        continue;
      }
      if (!cms_is_yes($field['allowedit'] ?? 'Yes')) {
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
      if (in_array($fieldTypeId, [21, 23], true)) {
        continue;
      }
      $typeRow = $fieldTypes[$fieldTypeId] ?? null;
      $typeName = $typeRow['type'] ?? '';
      $inputType = ($fieldTypeId === 16 || $fieldTypeId === 18) ? 'select' : cms_field_input_type($typeName);

      // Normalize checkbox values for unchecked states.
      $value = $_POST[$fieldName] ?? null;
      if ($fieldTypeId === 2) {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
          continue;
        }
        $value = password_hash($raw, PASSWORD_DEFAULT);
      }
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

    if ($uploadUpdates) {
      foreach ($uploadUpdates as $table => $data) {
        if (!isset($tablesUpdates[$table])) {
          $tablesUpdates[$table] = [];
        }
        $tablesUpdates[$table] = array_merge($tablesUpdates[$table], $data);
      }
    }

    if ($tablesUpdates) {
      try {
        // Apply updates table-by-table and log actions.
        $currentFormName = trim((string) ($form['name'] ?? $form['title'] ?? ''));
        foreach ($tablesUpdates as $table => $data) {
          if (!$data) {
            continue;
          }
          if (!isset($tableColumns[$table])) {
            $tableColumns[$table] = cms_table_columns($pdo, $table);
          }
          $columnNames = array_map(static fn($col) => $col['Field'] ?? '', $tableColumns[$table]);
          if (in_array('source_form_id', $columnNames, true) && !isset($data['source_form_id'])) {
            $data['source_form_id'] = $formId;
          }
          if (in_array('source_form_name', $columnNames, true) && !isset($data['source_form_name'])) {
            $data['source_form_name'] = $currentFormName;
          }
          $sets = [];
          $params = [':id' => $recordId];
          foreach ($data as $name => $val) {
            $paramBase = 'v_' . $table . '_' . $name;
            $paramBase = preg_replace('/[^a-zA-Z0-9_]/', '_', $paramBase);
            $placeholder = ':' . $paramBase;
            $sets[] = "`{$name}` = {$placeholder}";
            $params[$placeholder] = $val;
          }
          $sql = "UPDATE `{$table}` SET " . implode(', ', $sets) . " WHERE id = :id";
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
          cms_log_action('record_update', $table, $recordId, $resolved, $form['title'] ?? 'form', 'cms');
        }
        $saveMessage = 'Changes saved.';
        $record = cms_fetch_record($pdo, $contentTable, $recordId);
        $galleryItems = cms_fetch_gallery_items($pdo, $formId, $recordId);
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

$formTitle = $form['title'] ?? 'Form';
if (!isset($galleryItems)) {
  $galleryItems = cms_fetch_gallery_items($pdo, $formId, $recordId);
}
?>
<div class="cms-shell">
  <?php include __DIR__ . '/includes/menu.php'; ?>
  <main class="cms-content">
    <div class="cms-content-header">
      <div>
        <h1 class="h3 mb-1">Edit <?php echo cms_h($formTitle); ?></h1>
        <p class="text-muted mb-0">Table: <?php echo cms_h($contentTable ?? ''); ?> · Record: <?php echo cms_h((string) $recordId); ?></p>
      </div>
      <div>
        <a class="btn btn-outline-secondary" href="<?php echo $CMS_BASE_URL; ?>/recordViewv5.php?frm=<?php echo cms_h((string) ($form['id'] ?? $formId)); ?>">Back to list</a>
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
        <?php if ($tabs): ?>
          <ul class="nav nav-tabs cms-tabs" role="tablist">
            <?php foreach ($tabs as $index => $tab): ?>
              <?php $tabId = (int) ($tab['id'] ?? 0); ?>
              <?php if (empty($fieldsByTab[$tabId])) { continue; } ?>
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
        <form class="pt-4" method="post" enctype="multipart/form-data" data-form-id="<?php echo cms_h((string) $formId); ?>" data-record-id="<?php echo cms_h((string) $recordId); ?>" action="<?php echo $CMS_BASE_URL; ?>/recordEditv5.php?frm=<?php echo cms_h((string) ($form['id'] ?? $formId)); ?>&id=<?php echo cms_h((string) $recordId); ?>">
          <input type="hidden" name="frm" value="<?php echo cms_h((string) ($form['id'] ?? $formId)); ?>">
          <input type="hidden" name="id" value="<?php echo cms_h((string) ($record['id'] ?? $recordId)); ?>">
          <input type="hidden" name="active_tab" value="<?php echo cms_h((string) $activeTab); ?>">
          <?php if ($tabs): ?>
            <div class="tab-content">
              <?php foreach ($tabs as $index => $tab): ?>
                <?php $tabId = (int) ($tab['id'] ?? 0); ?>
                <?php $tabFields = $fieldsByTab[$tabId] ?? []; ?>
                <?php if (!$tabFields) { continue; } ?>
                <div class="tab-pane fade <?php echo $index === 0 ? 'show active' : ''; ?>" id="tab-pane-<?php echo $tabId; ?>" role="tabpanel">
                  <div class="row g-4">
                    <?php foreach ($tabFields as $field): ?>
                      <?php
                      $fieldName = $field['name'] ?? '';
                      $fieldLabel = $field['label'] ?? $field['title'] ?? $fieldName;
                      $fieldTypeId = (int) ($field['field'] ?? 0);
                      $typeRow = $fieldTypes[$fieldTypeId] ?? null;
                      $typeName = $typeRow['type'] ?? '';
                      if ($fieldTypeId === 2) {
                        $inputType = 'password';
                      } elseif ($fieldTypeId === 3) {
                        $inputType = 'radio';
                      } elseif ($fieldTypeId === 4) {
                        $inputType = 'checkbox';
                      } elseif ($fieldTypeId === 5) {
                        $inputType = 'color';
                      } elseif ($fieldTypeId === 6) {
                        $inputType = 'date';
                      } elseif ($fieldTypeId === 28) {
                        $inputType = 'datetime-local';
                      } elseif ($fieldTypeId === 13) {
                        $inputType = 'time';
                      } else {
                        $inputType = ($fieldTypeId === 16 || $fieldTypeId === 18) ? 'select' : cms_field_input_type($typeName);
                      }
                      $value = $fieldName && isset($record[$fieldName]) ? $record[$fieldName] : ($field['value'] ?? '');
                      $required = ($field['required'] ?? 'No') === 'Yes';
                      $allowEdit = ($field['allowedit'] ?? 'Yes') === 'Yes';
                      $placeholder = $field['placeholder'] ?? '';
                      $min = $field['min'] ?? null;
                      $max = $field['max'] ?? null;
                      $step = $field['step'] ?? null;
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
                        <?php if ($fieldTypeId === 21): ?>
                        <?php
                          $accept = cms_media_accept_attr($field['file_ext'] ?? '');
                          $inputName = 'cms_file_' . (int) ($field['id'] ?? 0);
                        ?>
                        <input class="form-control" type="file" id="field-<?php echo cms_h($fieldName); ?>" name="<?php echo cms_h($inputName); ?>" <?php echo $accept ? 'accept="' . cms_h($accept) . '"' : ''; ?> <?php echo $allowEdit ? '' : 'disabled'; ?>>
                        <?php if ($fieldTypeId === 21 && $fieldName && !empty($record[$fieldName])): ?>
                          <div class="form-text">Current: <?php echo cms_h((string) $record[$fieldName]); ?></div>
                        <?php endif; ?>
                      <?php elseif ($fieldTypeId === 23): ?>
                        <?php
                          $accept = cms_media_accept_attr($field['file_ext'] ?? '');
                          $inputName = 'cms_file_' . (int) ($field['id'] ?? 0);
                          $galleryOrder = implode(',', array_map(static fn($row) => (int) ($row['id'] ?? 0), $galleryItems));
                        ?>
                        <div class="cms-gallery-upload">
                          <input class="form-control" type="file" id="field-<?php echo cms_h($fieldName); ?>" name="<?php echo cms_h($inputName . '[]'); ?>" multiple <?php echo $accept ? 'accept="' . cms_h($accept) . '"' : ''; ?> <?php echo $allowEdit ? '' : 'disabled'; ?>>
                          <div class="form-text">Drag or select multiple images, then click save.</div>
                        </div>
                        <input type="hidden" name="gallery_order" value="<?php echo cms_h($galleryOrder); ?>">
                        <div class="cms-gallery-list" data-gallery>
                          <?php foreach ($galleryItems as $item): ?>
                            <?php
                              $folderName = (string) ($item['folder_name'] ?? '');
                              $parts = array_values(array_filter(explode('/', trim($folderName, '/'))));
                              $mediatype = $parts[0] ?? 'images';
                              $folder = implode('/', array_slice($parts, 1));
                              $thumbUrl = cms_media_url($mediatype, $folder, (string) ($item['image'] ?? ''), 'xs', true);
                            ?>
                            <div class="cms-gallery-item" draggable="true" data-id="<?php echo (int) ($item['id'] ?? 0); ?>">
                              <div class="cms-gallery-thumb">
                                <img src="<?php echo cms_h($thumbUrl); ?>" alt="">
                              </div>
                              <div class="cms-gallery-fields">
                                <label class="form-label">Alt</label>
                                <input class="form-control" type="text" name="gallery_alttag[<?php echo (int) ($item['id'] ?? 0); ?>]" value="<?php echo cms_h((string) ($item['alttag'] ?? '')); ?>">
                                <label class="form-label mt-2">Caption</label>
                                <input class="form-control" type="text" name="gallery_caption[<?php echo (int) ($item['id'] ?? 0); ?>]" value="<?php echo cms_h((string) ($item['caption'] ?? '')); ?>">
                                <div class="form-check form-switch mt-2">
                                  <input class="form-check-input" type="checkbox" id="gallery-show-<?php echo (int) ($item['id'] ?? 0); ?>" name="gallery_showonweb[<?php echo (int) ($item['id'] ?? 0); ?>]" value="Yes" <?php echo (string) ($item['showonweb'] ?? 'Yes') === 'Yes' ? 'checked' : ''; ?>>
                                  <label class="form-check-label" for="gallery-show-<?php echo (int) ($item['id'] ?? 0); ?>">Show on Web</label>
                                </div>
                                <div class="cms-gallery-meta">Filename: <?php echo cms_h((string) ($item['image'] ?? '')); ?></div>
                              </div>
                              <div class="cms-gallery-actions">
                                <button class="btn btn-link text-danger" type="submit" name="gallery_delete[<?php echo (int) ($item['id'] ?? 0); ?>]" value="1" onclick="return confirm('Delete this image?')">
                                  <i class="fa-solid fa-trash"></i>
                                </button>
                              </div>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      <?php elseif ($inputType === 'radio'): ?>
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
                $fieldName = $field['name'] ?? '';
                $fieldLabel = $field['label'] ?? $field['title'] ?? $fieldName;
                $fieldTypeId = (int) ($field['field'] ?? 0);
                $typeRow = $fieldTypes[$fieldTypeId] ?? null;
                $typeName = $typeRow['type'] ?? '';
                if ($fieldTypeId === 2) {
                  $inputType = 'password';
                } elseif ($fieldTypeId === 3) {
                  $inputType = 'radio';
                } elseif ($fieldTypeId === 4) {
                  $inputType = 'checkbox';
                } elseif ($fieldTypeId === 5) {
                  $inputType = 'color';
                } elseif ($fieldTypeId === 6) {
                  $inputType = 'date';
                } elseif ($fieldTypeId === 28) {
                  $inputType = 'datetime-local';
                } elseif ($fieldTypeId === 13) {
                  $inputType = 'time';
                } else {
                  $inputType = ($fieldTypeId === 16 || $fieldTypeId === 18) ? 'select' : cms_field_input_type($typeName);
                }
                $value = $fieldName && isset($record[$fieldName]) ? $record[$fieldName] : ($field['value'] ?? '');
                $required = ($field['required'] ?? 'No') === 'Yes';
                $allowEdit = ($field['allowedit'] ?? 'Yes') === 'Yes';
                $placeholder = $field['placeholder'] ?? '';
                $comment = $field['comment'] ?? '';
                $tooltip = $field['tooltip'] ?? '';
                $datalistRaw = $field['datalist'] ?? '';
                $sourceSql = cms_field_source_sql($field);
                $min = $field['min'] ?? null;
                $max = $field['max'] ?? null;
                $step = $field['step'] ?? null;
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
                  <?php if ($fieldTypeId === 21): ?>
                    <?php
                      $accept = cms_media_accept_attr($field['file_ext'] ?? '');
                      $inputName = 'cms_file_' . (int) ($field['id'] ?? 0);
                    ?>
                    <input class="form-control" type="file" id="field-<?php echo cms_h($fieldName); ?>" name="<?php echo cms_h($inputName); ?>" <?php echo $accept ? 'accept="' . cms_h($accept) . '"' : ''; ?> <?php echo $allowEdit ? '' : 'disabled'; ?>>
                    <?php if ($fieldTypeId === 21 && $fieldName && !empty($record[$fieldName])): ?>
                      <div class="form-text">Current: <?php echo cms_h((string) $record[$fieldName]); ?></div>
                    <?php endif; ?>
                  <?php elseif ($fieldTypeId === 23): ?>
                    <?php
                      $accept = cms_media_accept_attr($field['file_ext'] ?? '');
                      $inputName = 'cms_file_' . (int) ($field['id'] ?? 0);
                      $galleryOrder = implode(',', array_map(static fn($row) => (int) ($row['id'] ?? 0), $galleryItems));
                    ?>
                    <div class="cms-gallery-upload">
                      <input class="form-control" type="file" id="field-<?php echo cms_h($fieldName); ?>" name="<?php echo cms_h($inputName . '[]'); ?>" multiple <?php echo $accept ? 'accept="' . cms_h($accept) . '"' : ''; ?> <?php echo $allowEdit ? '' : 'disabled'; ?>>
                      <div class="form-text">Drag or select multiple images, then click save.</div>
                    </div>
                    <input type="hidden" name="gallery_order" value="<?php echo cms_h($galleryOrder); ?>">
                    <div class="cms-gallery-list" data-gallery>
                      <?php foreach ($galleryItems as $item): ?>
                        <?php
                          $folderName = (string) ($item['folder_name'] ?? '');
                          $parts = array_values(array_filter(explode('/', trim($folderName, '/'))));
                          $mediatype = $parts[0] ?? 'images';
                          $folder = implode('/', array_slice($parts, 1));
                          $thumbUrl = cms_media_url($mediatype, $folder, (string) ($item['image'] ?? ''), 'xs', true);
                        ?>
                        <div class="cms-gallery-item" draggable="true" data-id="<?php echo (int) ($item['id'] ?? 0); ?>">
                          <div class="cms-gallery-thumb">
                            <img src="<?php echo cms_h($thumbUrl); ?>" alt="">
                          </div>
                          <div class="cms-gallery-fields">
                            <label class="form-label">Alt</label>
                            <input class="form-control" type="text" name="gallery_alttag[<?php echo (int) ($item['id'] ?? 0); ?>]" value="<?php echo cms_h((string) ($item['alttag'] ?? '')); ?>">
                            <label class="form-label mt-2">Caption</label>
                            <input class="form-control" type="text" name="gallery_caption[<?php echo (int) ($item['id'] ?? 0); ?>]" value="<?php echo cms_h((string) ($item['caption'] ?? '')); ?>">
                            <div class="form-check form-switch mt-2">
                              <input class="form-check-input" type="checkbox" id="gallery-show-<?php echo (int) ($item['id'] ?? 0); ?>" name="gallery_showonweb[<?php echo (int) ($item['id'] ?? 0); ?>]" value="Yes" <?php echo (string) ($item['showonweb'] ?? 'Yes') === 'Yes' ? 'checked' : ''; ?>>
                              <label class="form-check-label" for="gallery-show-<?php echo (int) ($item['id'] ?? 0); ?>">Show on Web</label>
                            </div>
                            <div class="cms-gallery-meta">Filename: <?php echo cms_h((string) ($item['image'] ?? '')); ?></div>
                          </div>
                          <div class="cms-gallery-actions">
                            <button class="btn btn-link text-danger" type="submit" name="gallery_delete[<?php echo (int) ($item['id'] ?? 0); ?>]" value="1" onclick="return confirm('Delete this image?')">
                              <i class="fa-solid fa-trash"></i>
                            </button>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php elseif ($inputType === 'radio'): ?>
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
        <?php if (!empty($debugSql) && (($CMS_USER['userrole'] ?? 1) >= $showDebugRole)): ?>
          <div class="alert alert-info mt-4">
            <strong>Debug</strong>
            <pre class="bg-light border rounded p-3 mb-0 cms-debug-pre"><?php echo cms_h(implode("\n", $debugSql)); ?></pre>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </main>
</div>
<?php include __DIR__ . '/includes/footer-code.php'; ?>
