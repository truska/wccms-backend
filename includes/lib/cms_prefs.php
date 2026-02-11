<?php
function cms_preferences_table(): ?string {
  global $pdo, $DB_OK;

  static $table = null;
  if ($table !== null) {
    return $table;
  }

  if (!$DB_OK || !($pdo instanceof PDO)) {
    $table = null;
    return $table;
  }

  try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'cms_preferences'");
    if ($stmt && $stmt->fetchColumn()) {
      $table = 'cms_preferences';
      return $table;
    }

    $stmt = $pdo->query("SHOW TABLES LIKE 'preferences'");
    if ($stmt && $stmt->fetchColumn()) {
      $table = 'preferences';
      return $table;
    }
  } catch (PDOException $e) {
    $table = null;
    return $table;
  }

  $table = null;
  return $table;
}

function cms_load_preferences(string $scope = 'web'): array {
  global $pdo, $DB_OK;

  static $cache = [
    'web' => null,
    'cms' => null,
  ];

  $scope = ($scope === 'cms') ? 'cms' : 'web';

  if (is_array($cache[$scope])) {
    return $cache[$scope];
  }

  if (!$DB_OK || !($pdo instanceof PDO)) {
    $cache[$scope] = [];
    return $cache[$scope];
  }

  $table = cms_preferences_table();
  if (!$table) {
    $cache[$scope] = [];
    return $cache[$scope];
  }

  $column = ($scope === 'cms') ? 'showoncms' : 'showonweb';
  try {
    $sql = "SELECT * FROM {$table} WHERE archived = 0 AND {$column} = 'Yes' ORDER BY sort ASC, id ASC";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (PDOException $e) {
    $cache[$scope] = [];
    return $cache[$scope];
  }

  $data = [];
  foreach ($rows as $row) {
    $data[$row['name']] = $row;
  }

  $cache[$scope] = $data;
  return $cache[$scope];
}

function cms_pref(string $name, $default = null, string $scope = 'web') {
  $prefs = cms_load_preferences($scope);
  if (!isset($prefs[$name])) {
    return $default;
  }

  return $prefs[$name]['value'] ?? $default;
}

function cms_tel_data(
  string $telPref = 'prefTel1',
  string $intCodePref = 'prefTelIntCode',
  string $defaultDisplay = ''
): array {
  $display = (string) cms_pref($telPref, $defaultDisplay);
  $displayTrim = ltrim($display);
  $isInternational = ($displayTrim !== '' && $displayTrim[0] === '+');
  $digits = preg_replace('/\s+/', '', $display);
  $intl = (string) cms_pref($intCodePref, '');
  $dial = '';

  if ($digits !== '') {
    if ($isInternational) {
      $dial = $digits;
    } else {
      $dial = ($intl !== '') ? $intl . substr($digits, 1) : $digits;
    }
  }

  return [
    'display' => $display,
    'dial' => $dial,
  ];
}

function cms_h(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function cms_base_url(string $path = ''): string {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? '';
  $base = $host ? $scheme . '://' . $host : '';
  if ($path === '') {
    return $base;
  }
  return rtrim($base, '/') . '/' . ltrim($path, '/');
}
