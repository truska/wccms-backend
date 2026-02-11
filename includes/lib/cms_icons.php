<?php
function cms_icons_table_exists(PDO $pdo, string $table): bool {
  if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
    return false;
  }
  $stmt = $pdo->prepare("SHOW TABLES LIKE :table");
  $stmt->execute([':table' => $table]);
  return (bool) $stmt->fetchColumn();
}

function cms_icons_table_columns(PDO $pdo, string $table): array {
  if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
    return [];
  }
  $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
  return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function cms_icons_pick_column(array $columns, array $candidates): ?string {
  $cols = array_map(static fn($col) => strtolower($col['Field'] ?? ''), $columns);
  foreach ($candidates as $candidate) {
    $idx = array_search(strtolower($candidate), $cols, true);
    if ($idx !== false) {
      return $columns[$idx]['Field'];
    }
  }
  return null;
}

function cms_icons_normalize_fa_token(string $value): string {
  $value = trim($value);
  if ($value === '') {
    return '';
  }
  return str_replace('_', '-', $value);
}

function cms_icons_build_fa_class(?string $family, ?string $style, ?string $code): ?string {
  $code = cms_icons_normalize_fa_token((string) $code);
  if ($code === '') {
    return null;
  }

  $family = cms_icons_normalize_fa_token((string) $family);
  $style = cms_icons_normalize_fa_token((string) $style);

  $classes = [];
  if ($family !== '') {
    $classes[] = str_starts_with($family, 'fa-') ? $family : 'fa-' . $family;
  }
  if ($style !== '') {
    $classes[] = str_starts_with($style, 'fa-') ? $style : 'fa-' . $style;
  }
  if (!$classes) {
    $classes[] = 'fa-solid';
  }

  $classes[] = str_starts_with($code, 'fa-') ? $code : 'fa-' . $code;

  return implode(' ', array_unique($classes));
}

function cms_icon_class(PDO $pdo, $iconId): ?string {
  static $cache = [];

  if (!cms_icons_table_exists($pdo, 'cms_icons')) {
    return null;
  }

  $iconId = is_string($iconId) ? trim($iconId) : $iconId;
  if ($iconId === null || $iconId === '') {
    return null;
  }

  if (is_string($iconId) && str_contains($iconId, ',')) {
    $parts = array_filter(array_map('trim', explode(',', $iconId)));
    $iconId = $parts[0] ?? '';
  }

  if (!is_numeric($iconId)) {
    return null;
  }

  $iconId = (int) $iconId;
  if (isset($cache[$iconId])) {
    return $cache[$iconId];
  }

  $cols = cms_icons_table_columns($pdo, 'cms_icons');
  $familyField = cms_icons_pick_column($cols, ['iconfamilyv7']);
  $styleField = cms_icons_pick_column($cols, ['iconstylev7', 'iocnstylev7']);
  $codeField = cms_icons_pick_column($cols, ['iconcodev7']);

  if (!$codeField) {
    $cache[$iconId] = null;
    return null;
  }

  $stmt = $pdo->prepare("SELECT * FROM cms_icons WHERE id = :id LIMIT 1");
  $stmt->execute([':id' => $iconId]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    $cache[$iconId] = null;
    return null;
  }

  $prefStyle = function_exists('cms_pref') ? cms_pref('prefFontawesomeStyle', '') : '';
  $prefFamily = function_exists('cms_pref') ? cms_pref('prefFontawesomeFamily', '') : '';

  $family = $prefFamily ?: ($familyField ? ($row[$familyField] ?? '') : '');
  $style = $prefStyle ?: ($styleField ? ($row[$styleField] ?? '') : '');
  $code = $row[$codeField] ?? '';

  $cache[$iconId] = cms_icons_build_fa_class($family, $style, $code);
  return $cache[$iconId];
}
