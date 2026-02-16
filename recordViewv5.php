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
 * Guess lookup key/label columns for a lookup table.
 */
function cms_guess_lookup_columns(PDO $pdo, string $table): array {
  $cols = cms_table_columns($pdo, $table);
  if (!$cols) {
    return [null, null];
  }

  $key = cms_pick_column($cols, ['id']);
  if (!$key) {
    $key = $cols[0]['Field'];
  }

  $label = cms_pick_column($cols, ['name', 'title', 'label', 'display', 'description']);
  if (!$label) {
    foreach ($cols as $col) {
      if ($col['Field'] !== $key) {
        $label = $col['Field'];
        break;
      }
    }
  }

  return [$key, $label];
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
  $nameField = cms_pick_column($cmsTableCols, ['tablename', 'table', 'table_name', 'name']);
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
 * Find a CMS form record by numeric ID or name/slug-like fields.
 */
function cms_find_form(PDO $pdo, $formKey): ?array {
  if (!cms_table_exists($pdo, 'cms_form')) {
    return null;
  }

  $columns = cms_table_columns($pdo, 'cms_form');
  if (!$columns) {
    return null;
  }

  $formKey = is_string($formKey) ? trim($formKey) : $formKey;
  if ($formKey === '' || $formKey === null) {
    return null;
  }

  if (is_numeric($formKey)) {
    $stmt = $pdo->prepare('SELECT * FROM cms_form WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int) $formKey]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  $searchable = [];
  foreach (['name', 'title', 'form_name', 'formname', 'table', 'tablename', 'table_name', 'slug'] as $candidate) {
    $field = cms_pick_column($columns, [$candidate]);
    if ($field) {
      $searchable[] = $field;
    }
  }

  if (!$searchable) {
    return null;
  }

  $wheres = [];
  foreach ($searchable as $field) {
    $wheres[] = "{$field} = :key";
  }

  $sql = 'SELECT * FROM cms_form WHERE ' . implode(' OR ', $wheres) . ' LIMIT 1';
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':key' => $formKey]);
  return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Resolve the content table for a form, supporting table name or table ID.
 */
function cms_resolve_content_table(PDO $pdo, array $form): ?string {
  $formColumns = cms_table_columns($pdo, 'cms_form');
  $tableIdField = cms_pick_column($formColumns, ['table_id', 'tableid', 'table']);
  $tableNameField = cms_pick_column($formColumns, ['tablename', 'table_name']);

  $raw = null;
  if ($tableNameField && !empty($form[$tableNameField])) {
    $raw = $form[$tableNameField];
  } elseif ($tableIdField && !empty($form[$tableIdField])) {
    $raw = $form[$tableIdField];
  }

  if (!$raw) {
    return null;
  }

  if (is_numeric($raw)) {
    if (!cms_table_exists($pdo, 'cms_table')) {
      return null;
    }
    $cmsTableCols = cms_table_columns($pdo, 'cms_table');
    $nameField = cms_pick_column($cmsTableCols, ['tablename', 'table', 'table_name', 'name']);
    if (!$nameField) {
      return null;
    }

    $stmt = $pdo->prepare("SELECT {$nameField} AS tablename FROM cms_table WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => (int) $raw]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $tableName = $row['tablename'] ?? null;
    return $tableName ? cms_safe_identifier($tableName) : null;
  }

  return cms_safe_identifier((string) $raw);
}

/**
 * Produce a human-readable title for the form.
 */
function cms_get_form_title(PDO $pdo, array $form): string {
  $columns = cms_table_columns($pdo, 'cms_form');
  $titleField = cms_pick_column($columns, ['title']);
  if ($titleField && !empty($form[$titleField])) {
    return (string) $form[$titleField];
  }
  $fallbackField = cms_pick_column($columns, ['name', 'form_name', 'formname']);
  if ($fallbackField && !empty($form[$fallbackField])) {
    return (string) $form[$fallbackField];
  }
  return 'Records';
}

/**
 * Fetch view list columns for a form (supports new table or legacy fields).
 */
function cms_get_form_view_columns(PDO $pdo, array $form, string $contentTable): array {
  $columns = [];

  if (cms_table_exists($pdo, 'cms_form_view_list')) {
    $viewCols = cms_table_columns($pdo, 'cms_form_view_list');
    $formIdField = cms_pick_column($viewCols, ['form_id', 'formid', 'cms_form_id']);
    $nameField = cms_pick_column($viewCols, ['name', 'col_name', 'field', 'col']);
    $labelField = cms_pick_column($viewCols, ['overridename', 'col_label', 'label', 'title']);
    $tableField = cms_pick_column($viewCols, ['tableID', 'table_id', 'col_table', 'table']);
    $typeField = cms_pick_column($viewCols, ['type', 'col_type', 'input_type']);
    $ruleField = cms_pick_column($viewCols, ['ruleid', 'rule_id', 'rule', 'datatype', 'col_datatype', 'data_type']);
    $sortField = cms_pick_column($viewCols, ['sort', 'col_order', 'position', 'order']);

    if ($formIdField && $nameField) {
      $orderBy = $sortField ? "{$sortField} ASC, id ASC" : "id ASC";
      $sql = "SELECT * FROM cms_form_view_list WHERE {$formIdField} = :form_id AND archived = 0 AND showonweb = 'Yes' ORDER BY {$orderBy}";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([':form_id' => $form['id'] ?? 0]);
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      foreach ($rows as $row) {
        $name = $row[$nameField] ?? null;
        if (!$name) {
          continue;
        }
        $lookupTable = null;
        if ($tableField && !empty($row[$tableField])) {
          $lookupTable = cms_resolve_table_name($pdo, $row[$tableField]);
        }
        $columns[] = [
          'name' => (string) $name,
          'label' => $labelField ? ($row[$labelField] ?? null) : null,
          'table' => $lookupTable,
          'type' => $typeField ? ($row[$typeField] ?? null) : null,
          'rule_id' => $ruleField ? ($row[$ruleField] ?? null) : null,
          'raw' => $row,
        ];
      }
    }
  }

  if ($columns) {
    return $columns;
  }

  $formColumns = cms_table_columns($pdo, 'cms_form');
  $colFields = [];
  foreach ($formColumns as $col) {
    if (preg_match('/^col(\d+)$/i', $col['Field'], $matches)) {
      $colFields[(int) $matches[1]] = $col['Field'];
    }
  }
  if (!$colFields) {
    return [];
  }
  ksort($colFields);

  foreach ($colFields as $index => $field) {
    $name = $form[$field] ?? null;
    if (!$name) {
      continue;
    }
    $nameField = "col{$index}name";
    $tableField = "col{$index}table";
    $typeField = "col{$index}type";
    $ruleField = "col{$index}ruleid";
    $legacyRuleField = "col{$index}datatype";
    $ruleValue = $form[$ruleField] ?? ($form[$legacyRuleField] ?? null);

    $columns[] = [
      'name' => (string) $name,
      'label' => $form[$nameField] ?? null,
      'table' => $form[$tableField] ?? null,
      'type' => $form[$typeField] ?? null,
      'rule_id' => $ruleValue,
      'raw' => $form,
    ];
  }

  return $columns;
}

/**
 * Load action buttons configured for a form.
 */
function cms_get_form_actions(PDO $pdo, array $form): array {
  if (!cms_table_exists($pdo, 'cms_form_actions') || !cms_table_exists($pdo, 'cms_actions')) {
    return [];
  }

  $formActionCols = cms_table_columns($pdo, 'cms_form_actions');
  $actionsCols = cms_table_columns($pdo, 'cms_actions');
  if (!$formActionCols || !$actionsCols) {
    return [];
  }

  $formIdField = cms_pick_column($formActionCols, ['form_id', 'formid', 'cms_form_id', 'form']);
  $actionIdField = cms_pick_column($formActionCols, ['action_id', 'actionid', 'cms_action_id', 'action']);
  $sortField = cms_pick_column($formActionCols, ['sort', 'order', 'position']);
  $showField = cms_pick_column($formActionCols, ['showonweb', 'show_on_web']);
  $archivedField = cms_pick_column($formActionCols, ['archived']);

  $labelField = cms_pick_column($actionsCols, ['label', 'name', 'title']);
  $slugField = cms_pick_column($actionsCols, ['slug', 'name']);
  $iconField = cms_pick_column($actionsCols, ['icon', 'icon_class']);
  $urlField = cms_pick_column($actionsCols, ['link_href', 'url', 'page', 'link']);
  $tooltipField = cms_pick_column($actionsCols, ['tooltip_text', 'tooltip', 'tooltiptext']);
  $confirmField = cms_pick_column($actionsCols, ['confirm', 'needs_confirm', 'require_confirm']);
  $confirmTextField = cms_pick_column($actionsCols, ['confirm_text', 'confirmtext', 'confirm_message', 'confirmmsg']);
  $bgField = cms_pick_column($actionsCols, ['btn_bg', 'button_bg', 'bg', 'bg_color', 'bgcolour', 'bgcolor']);
  $textField = cms_pick_column($actionsCols, ['btn_text', 'button_text', 'text_color', 'textcolour', 'color', 'colour']);
  $hoverBgField = cms_pick_column($actionsCols, ['btn_hover_bg', 'button_hover_bg', 'hover_bg', 'hover_bg_color', 'hoverbg', 'hoverbgcolor']);
  $hoverTextField = cms_pick_column($actionsCols, ['btn_hover_text', 'button_hover_text', 'hover_text_color', 'hover_text', 'hovercolor', 'hover_colour']);

  if (!$formIdField || !$actionIdField) {
    return [];
  }

  $sql = "SELECT a.* FROM cms_form_actions fa JOIN cms_actions a ON a.id = fa.`{$actionIdField}` WHERE fa.`{$formIdField}` = :form_id";
  if ($showField) {
    $sql .= " AND fa.`{$showField}` = 'Yes'";
  }
  if ($archivedField) {
    $sql .= " AND fa.`{$archivedField}` = 0";
  }
  if ($sortField) {
    $sql .= " ORDER BY fa.`{$sortField}` ASC, fa.id ASC";
  }

  $stmt = $pdo->prepare($sql);
  $stmt->execute([':form_id' => $form['id'] ?? 0]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $actions = [];

  foreach ($rows as $row) {
    $actions[] = [
      'label' => $labelField ? ($row[$labelField] ?? '') : ($row['name'] ?? ''),
      'slug' => $slugField ? ($row[$slugField] ?? '') : ($row['name'] ?? ''),
      'icon' => $iconField ? ($row[$iconField] ?? '') : '',
      'url' => $urlField ? ($row[$urlField] ?? '') : '',
      'tooltip' => $tooltipField ? ($row[$tooltipField] ?? '') : '',
      'confirm' => $confirmField ? ($row[$confirmField] ?? '') : '',
      'confirm_text' => $confirmTextField ? ($row[$confirmTextField] ?? '') : '',
      'bg' => $bgField ? ($row[$bgField] ?? '') : '',
      'text' => $textField ? ($row[$textField] ?? '') : '',
      'hover_bg' => $hoverBgField ? ($row[$hoverBgField] ?? '') : '',
      'hover_text' => $hoverTextField ? ($row[$hoverTextField] ?? '') : '',
    ];
  }

  return $actions;
}

/**
 * Get the number of records per page for a user (with sane fallback).
 */
function cms_get_user_records_to_show(PDO $pdo, ?int $userId): int {
  $fallback = 25;
  if (!$userId || !cms_table_exists($pdo, 'cms_users')) {
    return $fallback;
  }
  $stmt = $pdo->prepare('SELECT recordstoshow FROM cms_users WHERE id = :id LIMIT 1');
  $stmt->execute([':id' => $userId]);
  $value = $stmt->fetchColumn();
  if (!$value) {
    return $fallback;
  }
  $value = (int) $value;
  return $value > 0 ? $value : $fallback;
}

/**
 * Load display/formatting rules for view list rendering.
 */
function cms_get_view_rules(PDO $pdo): array {
  if (!cms_table_exists($pdo, 'cms_view_rules')) {
    return [];
  }
  $stmt = $pdo->query("SELECT * FROM cms_view_rules WHERE showonweb = 'Yes' AND archived = 0 ORDER BY id ASC");
  $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
  $rules = [];
  foreach ($rows as $row) {
    if (!isset($row['id'])) {
      continue;
    }
    $rules[(int) $row['id']] = $row;
  }
  return $rules;
}

/**
 * Allow a limited wrapper tag name.
 */
function cms_sanitize_tag(?string $tag): ?string {
  if ($tag === null) {
    return null;
  }
  $tag = strtolower(trim($tag));
  if ($tag === '') {
    return null;
  }
  return preg_match('/^[a-z][a-z0-9-]*$/', $tag) ? $tag : null;
}

/**
 * Clean a space-separated list of CSS classes.
 */
function cms_sanitize_class(?string $class): string {
  if ($class === null) {
    return '';
  }
  $class = trim($class);
  if ($class === '') {
    return '';
  }
  $parts = preg_split('/\s+/', $class);
  $clean = [];
  foreach ($parts as $part) {
    if ($part !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $part)) {
      $clean[] = $part;
    }
  }
  return implode(' ', $clean);
}

/**
 * Wrap an HTML fragment with the requested tag/class (if valid).
 */
function cms_apply_wrapper(string $html, ?string $tag, ?string $class): string {
  $tag = cms_sanitize_tag($tag);
  $class = cms_sanitize_class($class);
  if (!$tag && $class) {
    $tag = 'span';
  }
  if (!$tag) {
    return $html;
  }
  $attr = $class ? ' class="' . cms_h($class) . '"' : '';
  return "<{$tag}{$attr}>{$html}</{$tag}>";
}

/**
 * Render a value with optional formatting rules (links/dates/wrappers).
 */
function cms_render_rule_value($value, ?array $rule): string {
  if ($value === null) {
    return '';
  }
  $raw = (string) $value;
  if (!$rule) {
    return cms_h($raw);
  }

  $kind = strtolower(trim((string) ($rule['kind'] ?? 'text')));
  $format = (string) ($rule['format'] ?? '');
  $tag = $rule['wrapper_tag'] ?? null;
  $class = $rule['css_class'] ?? null;
  $html = '';

  if ($kind === 'link') {
    $trim = trim($raw);
    if ($trim === '') {
      return '';
    }
    $href = $format !== '' ? (str_contains($format, '{value}') ? str_replace('{value}', $trim, $format) : $format . $trim) : $trim;
    $hrefAttr = cms_h($href);
    $label = cms_h($trim);
    $target = trim((string) ($rule['target'] ?? ''));
    $rel = trim((string) ($rule['rel'] ?? ''));
    $linkClass = cms_sanitize_class($class);
    $classAttr = $linkClass !== '' ? ' class="' . cms_h($linkClass) . '"' : '';
    $attr = '';
    if ($target !== '') {
      $attr .= ' target="' . cms_h($target) . '"';
    }
    if ($rel !== '') {
      $attr .= ' rel="' . cms_h($rel) . '"';
    } elseif ($target === '_blank') {
      $attr .= ' rel="noopener noreferrer"';
    }
    $html = '<a href="' . $hrefAttr . '"' . $classAttr . $attr . '>' . $label . '</a>';
    return cms_apply_wrapper($html, $tag, $class);
  }

  if ($kind === 'date') {
    $trim = trim($raw);
    if ($trim === '') {
      return '';
    }
    $ts = strtotime($trim);
    if ($ts === false) {
      $html = cms_h($trim);
    } else {
      $fmt = $format !== '' ? $format : 'Y-m-d';
      $html = cms_h(date($fmt, $ts));
    }
    return cms_apply_wrapper($html, $tag, $class);
  }

  $text = $raw;
  if ($format !== '' && str_contains($format, '{value}')) {
    $text = str_replace('{value}', $raw, $format);
  } elseif ($format !== '') {
    $text = $format;
  }
  $html = cms_h($text);
  return cms_apply_wrapper($html, $tag, $class);
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

/**
 * Normalize an action key from labels/URLs (lowercase, underscore).
 */
function cms_action_key(string $value): string {
  $value = strtolower(trim($value));
  if ($value === '') {
    return '';
  }
  $value = preg_replace('/[^a-z0-9]+/', '_', $value);
  return trim($value, '_');
}

$errors = [];
$info = [];
$formKey = $_GET['frm'] ?? ($_GET['form_id'] ?? ($_GET['form'] ?? ''));

// Guard against missing DB connection early.
if (!$DB_OK || !($pdo instanceof PDO)) {
  $errors[] = 'Database connection is unavailable.';
}

$form = null;
$contentTable = null;
$viewColumns = [];
$actions = [];
$viewRules = [];

if (!$errors) {
  // Resolve form and target content table.
  $form = cms_find_form($pdo, $formKey);
  if (!$form) {
    $errors[] = 'Form not found. Provide a valid form id or name.';
  } else {
    $contentTable = cms_resolve_content_table($pdo, $form);
    if (!$contentTable) {
      $errors[] = 'Content table not resolved for this form.';
    } elseif (!cms_table_exists($pdo, $contentTable)) {
      $errors[] = 'Content table does not exist: ' . $contentTable;
    }
  }
}

if (!$errors && $contentTable) {
  // Handle actions that target a single record (delete/archive/show toggle).
  $actionRequest = strtolower(trim((string) ($_GET['act'] ?? '')));
  $showRequest = isset($_GET['show']) ? trim((string) $_GET['show']) : null;
  $actionRecordId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

  if (($actionRequest !== '' || $showRequest !== null) && $actionRecordId > 0) {
    $columns = cms_table_columns($pdo, $contentTable);
    $columnNames = array_map(static fn($col) => $col['Field'] ?? '', $columns);
    $sets = [];
    $params = [':id' => $actionRecordId];

    $applyShowOnWebNo = false;
    if (in_array($actionRequest, ['delete', 'archive'], true)) {
      if (in_array('archived', $columnNames, true)) {
        $sets[] = "`archived` = :archived";
        $params[':archived'] = 1;
        $applyShowOnWebNo = true;
      }
    } elseif (in_array($actionRequest, ['undelete', 'unarchive'], true)) {
      if (in_array('archived', $columnNames, true)) {
        $sets[] = "`archived` = :archived";
        $params[':archived'] = 0;
        $applyShowOnWebNo = true;
      }
    } elseif ($showRequest !== null) {
      if (in_array('showonweb', $columnNames, true)) {
        $current = strtolower($showRequest);
        if (!in_array($current, ['yes', 'no'], true)) {
          $stmt = $pdo->prepare("SELECT showonweb FROM `{$contentTable}` WHERE id = :id LIMIT 1");
          $stmt->execute([':id' => $actionRecordId]);
          $dbValue = $stmt->fetchColumn();
          $current = strtolower((string) $dbValue);
        }
        $next = ($current === 'yes') ? 'No' : 'Yes';
        $sets[] = "`showonweb` = :showonweb";
        $params[':showonweb'] = $next;
      }
    }

    if ($applyShowOnWebNo && in_array('showonweb', $columnNames, true)) {
      $sets[] = "`showonweb` = :showonweb";
      $params[':showonweb'] = 'No';
    }

    if ($sets) {
      try {
        $sql = "UPDATE `{$contentTable}` SET " . implode(', ', $sets) . " WHERE id = :id";
        $resolved = $sql;
        foreach ($params as $pKey => $pValue) {
          $quoted = $pdo->quote((string) $pValue);
          $resolved = str_replace($pKey, $quoted, $resolved);
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $logAction = $actionRequest !== '' ? ('record_action_' . $actionRequest) : 'record_action_showhide';
        cms_log_action($logAction, $contentTable, $actionRecordId, $resolved, $form['title'] ?? 'form', 'cms');
        header('Location: ' . $CMS_BASE_URL . '/recordViewv5.php?frm=' . urlencode((string) ($form['id'] ?? '')));
        exit;
      } catch (PDOException $e) {
        $errors[] = 'Failed to update record.';
      }
    }
  }

  // Load list columns, form actions, and display rules.
  $viewColumns = cms_get_form_view_columns($pdo, $form, $contentTable);
  if (!$viewColumns) {
    $errors[] = 'No view columns defined for this form.';
  }
  $actions = cms_get_form_actions($pdo, $form);
  $viewRules = cms_get_view_rules($pdo);
}

$formTitle = $form ? cms_get_form_title($pdo, $form) : 'Records';

$records = [];
$totalRecords = 0;
$page = 1;
$perPage = 25;
$sortColumn = 'id';
$sortDir = 'asc';
$filters = [];
$globalSearch = '';
$columnMeta = [];
$showArchived = false;
$showOnWebField = null;

if (!$errors && $contentTable) {
  // Build column metadata and SQL query parts.
  $contentCols = cms_table_columns($pdo, $contentTable);
  $idField = cms_pick_column($contentCols, ['id']);
  if (!$idField) {
    $idField = $contentCols[0]['Field'] ?? 'id';
  }
  $baseColumnNames = array_map(static fn($col) => $col['Field'] ?? '', $contentCols);
  $showOnWebField = cms_pick_column($contentCols, ['showonweb', 'show_on_web']);
  $archivedField = cms_pick_column($contentCols, ['archived']);
  $formColumns = cms_table_columns($pdo, 'cms_form');
  $showArchivedField = cms_pick_column($formColumns, ['showarchived', 'show_archived', 'show_archived_records']);
  if ($showArchivedField && isset($form[$showArchivedField])) {
    $showArchived = cms_is_yes($form[$showArchivedField]);
  }

  $viewColumns = array_values(array_filter($viewColumns, static function ($col) {
    return !empty($col['name']);
  }));

  foreach ($viewColumns as &$col) {
    $col['name'] = cms_safe_identifier($col['name']);
    $labelSource = $col['label'] ?: $col['name'];
    $col['label'] = ucwords(str_replace('_', ' ', (string) $labelSource));
    $col['type'] = $col['type'] ?: 'Search';
    $col['table'] = $col['table'] ? cms_safe_identifier((string) $col['table']) : null;
    $col['rule_id'] = $col['rule_id'] ?? null;
  }
  unset($col);

  $viewColumns = array_values(array_filter($viewColumns, static function ($col) {
    return !empty($col['name']);
  }));

  $filters = $_GET['f'] ?? [];
  if (!is_array($filters)) {
    $filters = [];
  }

  // Search/sort/paging inputs (with defaults).
  $globalSearch = trim((string) ($_GET['q'] ?? ''));
  $sortColumn = (string) ($_GET['sort'] ?? $idField);
  $sortDir = strtolower((string) ($_GET['dir'] ?? 'asc'));
  if (!in_array($sortDir, ['asc', 'desc'], true)) {
    $sortDir = 'asc';
  }

  $page = max(1, (int) ($_GET['page'] ?? 1));
  $perPage = cms_get_user_records_to_show($pdo, $CMS_USER['id'] ?? null);
  $perPage = max(1, min(200, $perPage));

  $selectParts = ["c.`{$idField}` AS __record_id"];
  if ($showOnWebField) {
    $selectParts[] = "c.`{$showOnWebField}` AS __showonweb";
  }
  $joins = [];
  $where = [];
  $params = [];
  $columnMeta = [];

  // Build SELECT columns and optional lookup joins.
  $aliasIndex = 1;
  foreach ($viewColumns as $col) {
    $name = $col['name'];
    if (!$name) {
      continue;
    }
    if (!in_array($name, $baseColumnNames, true)) {
      $errors[] = "Column not found in {$contentTable}: {$name}";
      continue;
    }
    $expr = "c.`{$name}`";
    $displayExpr = $expr;
    $lookupLabelField = null;

    if ($col['table']) {
      $lookupTable = $col['table'];
      if ($lookupTable && cms_table_exists($pdo, $lookupTable)) {
        $alias = 'l' . $aliasIndex++;
        [$lookupKey, $lookupLabel] = cms_guess_lookup_columns($pdo, $lookupTable);
        if ($lookupKey && $lookupLabel) {
          $joins[] = "LEFT JOIN `{$lookupTable}` {$alias} ON {$alias}.`{$lookupKey}` = c.`{$name}`";
          $displayExpr = "COALESCE({$alias}.`{$lookupLabel}`, c.`{$name}`)";
          $lookupLabelField = $lookupLabel;
        }
      }
    }

    $aliasName = $name . '__display';
    $rawAliasName = $name . '__raw';
    $selectParts[] = "{$displayExpr} AS `{$aliasName}`";
    $selectParts[] = "{$expr} AS `{$rawAliasName}`";
    $columnMeta[$name] = [
      'label' => $col['label'],
      'type' => $col['type'],
      'rule_id' => $col['rule_id'],
      'display' => $aliasName,
      'raw' => $rawAliasName,
      'expr' => $displayExpr,
      'raw_expr' => $expr,
      'lookup_label' => $lookupLabelField,
    ];
  }

  // Apply column filters.
  foreach ($filters as $field => $value) {
    if (!isset($columnMeta[$field])) {
      continue;
    }
    $value = trim((string) $value);
    if ($value === '') {
      continue;
    }

    $type = strtolower((string) ($columnMeta[$field]['type'] ?? 'search'));
    $expr = $columnMeta[$field]['expr'] ?? $columnMeta[$field]['raw_expr'];

    if ($type === 'select') {
      $expr = $columnMeta[$field]['raw_expr'] ?? $expr;
      $paramKey = ':f_' . $field;
      $where[] = "{$expr} = {$paramKey}";
      $params[$paramKey] = $value;
    } else {
      $paramKey = ':f_' . $field;
      $where[] = "{$expr} LIKE {$paramKey}";
      $params[$paramKey] = '%' . $value . '%';
    }
  }

  if ($archivedField && !$showArchived) {
    $where[] = "c.`{$archivedField}` = 0";
  }

  // Global search across visible columns.
  if ($globalSearch !== '') {
    $likes = [];
    foreach ($columnMeta as $field => $meta) {
      $paramKey = ':q_' . $field;
      $likes[] = ($meta['expr'] ?? $meta['raw_expr']) . " LIKE {$paramKey}";
      $params[$paramKey] = '%' . $globalSearch . '%';
    }
    if ($likes) {
      $where[] = '(' . implode(' OR ', $likes) . ')';
    }
  }

  // Determine sort expression (default to id).
  $sortExpr = "c.`{$idField}`";
  if (isset($columnMeta[$sortColumn])) {
    $sortExpr = $columnMeta[$sortColumn]['expr'] ?? $columnMeta[$sortColumn]['raw_expr'];
  }

  $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
  $joinSql = $joins ? (' ' . implode(' ', $joins)) : '';

  // Count total records for pagination.
  $countSql = "SELECT COUNT(*) FROM `{$contentTable}` c{$joinSql} {$whereSql}";
  $stmt = $pdo->prepare($countSql);
  foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
  }
  $stmt->execute();
  $totalRecords = (int) $stmt->fetchColumn();

  // Fetch paged data for the grid.
  $offset = ($page - 1) * $perPage;
  $dataSql = "SELECT " . implode(', ', $selectParts) . " FROM `{$contentTable}` c{$joinSql} {$whereSql} ORDER BY {$sortExpr} {$sortDir} LIMIT :limit OFFSET :offset";
  $stmt = $pdo->prepare($dataSql);
  foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
  }
  $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  $stmt->execute();
  $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<div class="cms-shell">
  <?php include __DIR__ . '/includes/menu.php'; ?>
  <main class="cms-content">
    <div class="cms-content-header">
      <div>
        <h1 class="h3 mb-1">Manage <?php echo cms_h($formTitle); ?><?php echo $contentTable ? ' · ' . cms_h($contentTable) : ''; ?></h1>
        <p class="text-muted mb-0">Table: <?php echo cms_h($contentTable ?? ''); ?></p>
      </div>
      <div>
        <a class="btn btn-primary" href="<?php echo $CMS_BASE_URL; ?>/recordNewv5.php?frm=<?php echo cms_h((string) ($form['id'] ?? '')); ?>">Add New</a>
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
        <form method="get" class="cms-table-controls">
          <?php if (!empty($_GET['frm'])): ?>
            <input type="hidden" name="frm" value="<?php echo cms_h((string) $_GET['frm']); ?>">
          <?php elseif (!empty($_GET['form_id'])): ?>
            <input type="hidden" name="form_id" value="<?php echo cms_h((string) $_GET['form_id']); ?>">
          <?php elseif (!empty($_GET['form'])): ?>
            <input type="hidden" name="form" value="<?php echo cms_h((string) $_GET['form']); ?>">
          <?php endif; ?>

          <div class="row g-3 align-items-end">
            <div class="col-sm-6 col-lg-4">
              <label class="form-label">Search</label>
              <input type="text" name="q" value="<?php echo cms_h($globalSearch); ?>" class="form-control" placeholder="Search all columns">
            </div>
            <div class="col-sm-6 col-lg-3">
              <label class="form-label">Sort</label>
              <div class="d-flex gap-2">
                <select name="sort" class="form-select">
                  <?php $currentSortLabel = $columnMeta[$sortColumn]['label'] ?? $sortColumn; ?>
                  <option value="<?php echo cms_h($sortColumn); ?>"><?php echo cms_h($currentSortLabel); ?></option>
                  <?php foreach ($columnMeta as $field => $meta): ?>
                    <?php if ($field === $sortColumn) { continue; } ?>
                    <option value="<?php echo cms_h($field); ?>"><?php echo cms_h($meta['label']); ?></option>
                  <?php endforeach; ?>
                </select>
                <select name="dir" class="form-select">
                  <option value="asc" <?php echo $sortDir === 'asc' ? 'selected' : ''; ?>>Asc</option>
                  <option value="desc" <?php echo $sortDir === 'desc' ? 'selected' : ''; ?>>Desc</option>
                </select>
              </div>
            </div>
            <div class="col-sm-6 col-lg-2 d-grid">
              <button class="btn btn-outline-primary" type="submit">Apply</button>
            </div>
          </div>

          <div class="table-responsive mt-4">
            <style>
              .cms-sort-link {
                color: inherit;
              }
              .cms-sort-link:hover {
                color: inherit;
              }
              .cms-sort-arrows {
                display: inline-flex;
                flex-direction: column;
                align-items: center;
                line-height: 0.72;
                font-size: 10px;
                color: #bcc2cc;
                min-width: 10px;
              }
              .cms-sort-arrows .is-active {
                color: #6a717d;
                font-weight: 700;
              }
            </style>
            <table class="table table-hover align-middle cms-table">
              <?php
              $sortBaseQuery = $_GET;
              $sortBaseQuery['page'] = 1;
              ?>
              <thead>
                <tr>
                  <?php
                  $idIsCurrentSort = ($sortColumn === $idField);
                  $idNextDir = ($idIsCurrentSort && $sortDir === 'asc') ? 'desc' : 'asc';
                  $idUpActive = ($idIsCurrentSort && $sortDir === 'asc');
                  $idDownActive = ($idIsCurrentSort && $sortDir === 'desc');
                  $idSortQuery = array_merge($sortBaseQuery, ['sort' => $idField, 'dir' => $idNextDir]);
                  ?>
                  <th>
                    <a class="cms-sort-link text-decoration-none d-flex align-items-center justify-content-between gap-2 w-100" href="?<?php echo cms_h(http_build_query($idSortQuery)); ?>">
                      <span>ID</span>
                      <span class="cms-sort-arrows" aria-hidden="true">
                        <span class="<?php echo $idUpActive ? 'is-active' : ''; ?>">&#8593;</span>
                        <span class="<?php echo $idDownActive ? 'is-active' : ''; ?>">&#8595;</span>
                      </span>
                    </a>
                  </th>
                  <?php foreach ($columnMeta as $field => $meta): ?>
                    <?php
                    $isCurrentSort = ($sortColumn === $field);
                    $nextDir = ($isCurrentSort && $sortDir === 'asc') ? 'desc' : 'asc';
                    $upActive = ($isCurrentSort && $sortDir === 'asc');
                    $downActive = ($isCurrentSort && $sortDir === 'desc');
                    $sortQuery = array_merge($sortBaseQuery, ['sort' => $field, 'dir' => $nextDir]);
                    ?>
                    <th>
                      <a class="cms-sort-link text-decoration-none d-flex align-items-center justify-content-between gap-2 w-100" href="?<?php echo cms_h(http_build_query($sortQuery)); ?>">
                        <span><?php echo cms_h($meta['label']); ?></span>
                        <span class="cms-sort-arrows" aria-hidden="true">
                          <span class="<?php echo $upActive ? 'is-active' : ''; ?>">&#8593;</span>
                          <span class="<?php echo $downActive ? 'is-active' : ''; ?>">&#8595;</span>
                        </span>
                      </a>
                    </th>
                  <?php endforeach; ?>
                  <th class="text-center">Action</th>
                </tr>
                <tr class="cms-table-filters">
                  <th></th>
                  <?php foreach ($columnMeta as $field => $meta): ?>
                    <th>
                      <?php $value = $filters[$field] ?? ''; ?>
                      <?php if (strtolower((string) $meta['type']) === 'select'): ?>
                        <select name="f[<?php echo cms_h($field); ?>]" class="form-select form-select-sm">
                          <option value="">All</option>
                          <?php
                          $options = [];
                          if ($contentTable) {
                            $expr = $meta['expr'] ?? $meta['raw_expr'];
                            $rawExpr = $meta['raw_expr'] ?? $expr;
                            $joinSqlForOptions = $joins ? (' ' . implode(' ', $joins)) : '';
                            $sql = "SELECT DISTINCT {$rawExpr} AS raw_value, {$expr} AS display_value FROM `{$contentTable}` c{$joinSqlForOptions} ORDER BY {$expr} ASC LIMIT 200";
                            try {
                              $stmt = $pdo->query($sql);
                              $options = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
                            } catch (Exception $e) {
                              $options = [];
                            }
                          }
                          ?>
                          <?php foreach ($options as $option): ?>
                            <?php
                            $rawOption = (string) ($option['raw_value'] ?? '');
                            $displayOption = (string) ($option['display_value'] ?? $rawOption);
                            if ($rawOption === '') { continue; }
                            $label = $displayOption;
                            if (!empty($meta['lookup_label']) && $displayOption !== '' && $displayOption !== $rawOption) {
                              $label = $displayOption . ' [' . $rawOption . ']';
                            }
                            ?>
                            <option value="<?php echo cms_h($rawOption); ?>" <?php echo ($rawOption === (string) $value) ? 'selected' : ''; ?>>
                              <?php echo cms_h($label); ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      <?php else: ?>
                        <input type="text" name="f[<?php echo cms_h($field); ?>]" value="<?php echo cms_h((string) $value); ?>" class="form-control form-control-sm" placeholder="Search">
                      <?php endif; ?>
                    </th>
                  <?php endforeach; ?>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$records): ?>
                  <tr>
                    <td colspan="<?php echo 3 + count($columnMeta); ?>" class="text-muted">No records found.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($records as $row): ?>
                    <tr>
                      <td><?php echo cms_h((string) $row['__record_id']); ?></td>
                      <?php foreach ($columnMeta as $field => $meta): ?>
                        <?php
                        $display = (string) ($row[$meta['display']] ?? '');
                        $rawValue = (string) ($row[$meta['raw']] ?? '');
                        if (!empty($meta['lookup_label']) && $display !== '' && $rawValue !== '' && $display !== $rawValue) {
                          $display = $display . ' [' . $rawValue . ']';
                        }
                        ?>
                        <?php $ruleId = (int) ($meta['rule_id'] ?? 0); ?>
                        <?php $rule = $ruleId && isset($viewRules[$ruleId]) ? $viewRules[$ruleId] : null; ?>
                        <td><?php echo cms_render_rule_value($display, $rule); ?></td>
                      <?php endforeach; ?>
                      <td class="text-center">
                        <?php if ($actions): ?>
                          <div class="cms-action-buttons">
                            <?php foreach ($actions as $action): ?>
                              <?php
                              $label = $action['label'] ?: $action['slug'];
                              $href = $action['url'] ? $action['url'] : '#';
                              $actionKey = cms_action_key((string) $action['url']);
                              if ($actionKey === '') {
                                $actionKey = cms_action_key((string) $action['slug']);
                              }
                              if ($actionKey === '') {
                                $actionKey = cms_action_key((string) $label);
                              }
                              if ($href === '#' || $href === '') {
                                $slug = strtolower((string) $action['slug']);
                                $labelLower = strtolower((string) $label);
                                if ($slug === 'edit' || $labelLower === 'edit') {
                                  $href = 'recordEditv5.php?frm=[frm]&id=[id]';
                                } elseif ($slug === 'copy' || $labelLower === 'copy') {
                                  $href = 'recordCopyv5.php?frm=[frm]&id=[id]';
                                } elseif ($actionKey !== '') {
                                  $href = 'recordActionv5.php?action=' . urlencode($actionKey) . '&frm=[frm]&id=[id]';
                                }
                              }
                              if ($href && !str_contains($href, '.php') && !str_contains($href, '/')) {
                                $href = 'recordActionv5.php?action=' . urlencode(cms_action_key($href)) . '&frm=[frm]&id=[id]';
                              }
                              if (str_contains($href, 'recordEditv4.php')) {
                                $href = str_replace('recordEditv4.php', 'recordEditv5.php', $href);
                              }
                              $currentShow = $row['__showonweb'] ?? ($row['showonweb'] ?? '');
                              $href = str_replace(
                                ['[frm]', '[id]', '[show]'],
                                [(string) ($form['id'] ?? ''), (string) $row['__record_id'], (string) $currentShow],
                                $href
                              );
                              if (str_contains($href, 'recordViewv5.php') && (str_contains($href, 'act=') || str_contains($href, 'show='))) {
                                $actionType = '';
                                if (preg_match('/act=([^&]+)/i', $href, $matches)) {
                                  $actionType = cms_action_key($matches[1]);
                                } elseif (preg_match('/show=([^&]+)/i', $href, $matches)) {
                                  $actionType = 'toggle_show';
                                }
                                if ($actionType !== '') {
                                  $href = 'recordActionv5.php?action=' . urlencode($actionType)
                                    . '&frm=' . urlencode((string) ($form['id'] ?? ''))
                                    . '&id=' . urlencode((string) $row['__record_id']);
                                }
                              }
                              $styleParts = [];
                              $bgRaw = (string) ($action['bg'] ?? '');
                              $bgMulti = ($bgRaw !== '' && (str_contains($bgRaw, ',') || str_contains($bgRaw, '|') || (str_contains($bgRaw, '#') && !str_starts_with($bgRaw, '#'))));
                              if ($bgRaw !== '' && !$bgMulti) {
                                $styleParts[] = '--cms-action-bg:' . $bgRaw;
                              }
                              if (!empty($action['text'])) {
                                $styleParts[] = '--cms-action-color:' . $action['text'];
                              }
                              if (!empty($action['hover_bg'])) {
                                $styleParts[] = '--cms-action-bg-hover:' . $action['hover_bg'];
                              }
                              if (!empty($action['hover_text'])) {
                                $styleParts[] = '--cms-action-color-hover:' . $action['hover_text'];
                              }
                              $iconRaw = trim((string) ($action['icon'] ?? ''));
                              if (str_contains($iconRaw, ',') || str_contains($iconRaw, '|')) {
                                $parts = preg_split('/[|,]+/', $iconRaw);
                                $stateOnRaw = trim($parts[0] ?? '');
                                $stateOffRaw = trim($parts[1] ?? '');
                                $stateOn = cms_icon_class($pdo, $stateOnRaw) ?: $stateOnRaw;
                                $stateOff = cms_icon_class($pdo, $stateOffRaw) ?: $stateOffRaw;
                                $iconClass = (isset($currentShow) && strtolower((string) $currentShow) === 'yes') ? $stateOn : $stateOff;
                              } else {
                                $iconClass = cms_icon_class($pdo, $iconRaw);
                                if (!$iconClass) {
                                  $iconClass = $iconRaw;
                                }
                              }
                              $hasIconClass = $iconClass !== '' && preg_match('/[a-zA-Z]/', $iconClass);
                              if (!empty($action['bg']) && (str_contains($action['bg'], ',') || str_contains($action['bg'], '|') || (str_contains($action['bg'], '#') && !str_starts_with($action['bg'], '#')))) {
                                $bgRaw = (string) $action['bg'];
                                if (str_contains($bgRaw, ',') || str_contains($bgRaw, '|')) {
                                  $bgParts = preg_split('/[|,]+/', $bgRaw);
                                } else {
                                  $splitPos = strpos($bgRaw, '#');
                                  $bgParts = $splitPos !== false ? [substr($bgRaw, 0, $splitPos), '#' . substr($bgRaw, $splitPos + 1)] : [$bgRaw];
                                }
                                $bgOn = trim($bgParts[0] ?? '');
                                $bgOff = trim($bgParts[1] ?? '');
                                $bgValue = (isset($currentShow) && strtolower((string) $currentShow) === 'yes') ? $bgOn : $bgOff;
                                if ($bgValue !== '') {
                                  $styleParts[] = '--cms-action-bg:' . $bgValue;
                                }
                              }
                              $styleAttr = $styleParts ? (' style="' . cms_h(implode(';', $styleParts)) . '"') : '';
                              $confirmRequired = cms_is_yes($action['confirm'] ?? '');
                              $confirmText = trim((string) ($action['confirm_text'] ?? ''));
                              $confirmAttr = $confirmRequired ? ' data-confirm="1"' : '';
                              $confirmMsgAttr = $confirmText !== '' ? ' data-confirm-text="' . cms_h($confirmText) . '"' : '';
                              $tooltipText = trim((string) ($action['tooltip'] ?? ''));
                              if ($tooltipText === '') {
                                $tooltipText = $label;
                              }
                              if (str_contains($tooltipText, ',') || str_contains($tooltipText, '|')) {
                                $parts = preg_split('/[|,]+/', $tooltipText);
                                $hideText = trim($parts[0] ?? '');
                                $showText = trim($parts[1] ?? '');
                                $isVisible = isset($currentShow) && strtolower((string) $currentShow) === 'yes';
                                $tooltipText = $isVisible ? $hideText : $showText;
                                if ($tooltipText === '') {
                                  $tooltipText = $label;
                                }
                              }
                              $tooltipAttr = $tooltipText !== '' ? ' data-bs-toggle="tooltip" title="' . cms_h($tooltipText) . '"' : '';
                              ?>
                              <a class="btn btn-sm cms-action-button" href="<?php echo cms_h($href); ?>"<?php echo $styleAttr; ?> data-action="<?php echo cms_h((string) $action['slug']); ?>" data-id="<?php echo cms_h((string) $row['__record_id']); ?>"<?php echo $confirmAttr . $confirmMsgAttr . $tooltipAttr; ?>>
                                <?php if ($hasIconClass): ?>
                                  <i class="<?php echo cms_h($iconClass); ?>"></i>
                                  <span class="visually-hidden"><?php echo cms_h($label); ?></span>
                                <?php else: ?>
                                  <?php echo cms_h($label); ?>
                                <?php endif; ?>
                              </a>
                            <?php endforeach; ?>
                          </div>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </form>

        <?php if ($totalRecords > 0): ?>
          <?php
          $totalPages = (int) ceil($totalRecords / $perPage);
          $pageStart = max(1, $page - 2);
          $pageEnd = min($totalPages, $page + 2);
          ?>
          <nav class="cms-pagination" aria-label="Records pagination">
            <ul class="pagination mb-0">
              <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])); ?>">Prev</a>
              </li>
              <?php for ($i = $pageStart; $i <= $pageEnd; $i++): ?>
                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                  <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                </li>
              <?php endfor; ?>
              <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => min($totalPages, $page + 1)])); ?>">Next</a>
              </li>
            </ul>
          </nav>
          <div class="text-muted mt-2">Showing <?php echo count($records); ?> of <?php echo $totalRecords; ?> records</div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </main>
</div>
<?php include __DIR__ . '/includes/footer-code.php'; ?>
