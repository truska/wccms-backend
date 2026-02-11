<?php
declare(strict_types=1);

function cms_schema_sync_load_master_config(string $path): array {
  if (!is_file($path)) {
    throw new RuntimeException("Master DB config not found: {$path}");
  }

  $vars = [];
  $result = (static function (string $file, array &$captured) {
    $included = include $file;
    $captured = get_defined_vars();
    unset($captured['file'], $captured['captured'], $captured['included']);
    return $included;
  })($path, $vars);

  $config = [];
  if (is_array($result)) {
    $config = $result;
  } else {
    $config = $vars;
  }

  $host = (string) ($config['MASTER_DB_HOST'] ?? $config['DB_HOST'] ?? '');
  $name = (string) ($config['MASTER_DB_NAME'] ?? $config['DB_NAME'] ?? '');
  $user = (string) ($config['MASTER_DB_USER'] ?? $config['DB_USER'] ?? '');
  $pass = (string) ($config['MASTER_DB_PASS'] ?? $config['DB_PASS'] ?? '');

  if ($host === '' || $name === '' || $user === '') {
    throw new RuntimeException('Master DB config must provide host, name, and user.');
  }

  return [
    'DB_HOST' => $host,
    'DB_NAME' => $name,
    'DB_USER' => $user,
    'DB_PASS' => $pass,
  ];
}

function cms_schema_sync_connect(array $db): PDO {
  $dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=utf8mb4',
    $db['DB_HOST'] ?? '',
    $db['DB_NAME'] ?? ''
  );

  return new PDO($dsn, (string) ($db['DB_USER'] ?? ''), (string) ($db['DB_PASS'] ?? ''), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
}

function cms_schema_sync_tables(PDO $pdo): array {
  $tables = [];
  $rows = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
  foreach ($rows as $row) {
    $name = (string) ($row[0] ?? '');
    if ($name !== '') {
      $tables[$name] = true;
    }
  }
  return $tables;
}

function cms_schema_sync_filter_tables(array $tables, string $prefix = ''): array {
  if ($prefix === '') {
    return $tables;
  }

  $filtered = [];
  foreach ($tables as $table => $present) {
    if (str_starts_with((string) $table, $prefix)) {
      $filtered[$table] = (bool) $present;
    }
  }
  return $filtered;
}

function cms_schema_sync_show_create(PDO $pdo, string $table): string {
  $stmt = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`');
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!is_array($row) || !isset($row['Create Table'])) {
    throw new RuntimeException("Unable to read CREATE TABLE for {$table}");
  }
  return (string) $row['Create Table'];
}

function cms_schema_sync_parse_columns(string $createSql): array {
  $columns = [];
  $lines = preg_split('/\R/', $createSql) ?: [];

  foreach ($lines as $line) {
    $trim = trim($line);
    if ($trim === '' || $trim[0] === ')' || $trim[0] === '(') {
      continue;
    }
    if (preg_match('/^(PRIMARY|UNIQUE|KEY|INDEX|CONSTRAINT|FULLTEXT|SPATIAL|CHECK)\b/i', $trim)) {
      continue;
    }

    $normalized = rtrim($trim, ',');
    if (preg_match('/^`([^`]+)`\s+(.+)$/', $normalized, $m)) {
      $name = $m[1];
      $columns[$name] = '`' . $name . '` ' . $m[2];
    }
  }

  return $columns;
}

function cms_schema_sync_parse_referenced_tables(string $sql): array {
  $refs = [];
  if (preg_match_all('/\bREFERENCES\s+`?([A-Za-z0-9_]+)`?/i', $sql, $m)) {
    foreach (($m[1] ?? []) as $name) {
      $table = (string) $name;
      if ($table !== '') {
        $refs[$table] = true;
      }
    }
  }
  return array_keys($refs);
}

function cms_schema_sync_split_create_table_sql(string $createSql): array {
  $lines = preg_split('/\R/', $createSql) ?: [];
  $createLines = [];
  $foreignKeyDefs = [];

  foreach ($lines as $line) {
    $trimmed = trim($line);
    $normalized = rtrim($trimmed, ',');
    if (preg_match('/^CONSTRAINT\b.*\bFOREIGN KEY\b/i', $normalized)) {
      $foreignKeyDefs[] = $normalized;
      continue;
    }
    $createLines[] = $line;
  }

  $createWithoutFk = implode("\n", $createLines);
  // Fix dangling commas left before the closing parenthesis.
  while (true) {
    $updated = preg_replace('/,\s*(\R\s*\))/m', '$1', $createWithoutFk);
    if (!is_string($updated) || $updated === $createWithoutFk) {
      break;
    }
    $createWithoutFk = $updated;
  }

  return [
    'create_sql' => $createWithoutFk,
    'foreign_keys' => $foreignKeyDefs,
  ];
}

function cms_schema_sync_order_create_operations(array $createOpsByTable): array {
  if ($createOpsByTable === []) {
    return [];
  }

  $deps = [];
  $dependents = [];
  $inDegree = [];

  foreach ($createOpsByTable as $table => $op) {
    $table = (string) $table;
    $inDegree[$table] = 0;
    $deps[$table] = [];
  }

  foreach ($createOpsByTable as $table => $op) {
    $table = (string) $table;
    $sql = (string) ($op['sql'] ?? '');
    $refs = cms_schema_sync_parse_referenced_tables($sql);
    foreach ($refs as $refTable) {
      if ($refTable === $table || !isset($createOpsByTable[$refTable])) {
        continue;
      }
      if (isset($deps[$table][$refTable])) {
        continue;
      }
      $deps[$table][$refTable] = true;
      $inDegree[$table]++;
      $dependents[$refTable][] = $table;
    }
  }

  $queue = [];
  foreach ($inDegree as $table => $degree) {
    if ($degree === 0) {
      $queue[] = $table;
    }
  }
  sort($queue, SORT_STRING);

  $orderedTables = [];
  while ($queue !== []) {
    $table = array_shift($queue);
    $orderedTables[] = $table;

    foreach (($dependents[$table] ?? []) as $dependent) {
      $inDegree[$dependent]--;
      if ($inDegree[$dependent] === 0) {
        $queue[] = $dependent;
      }
    }
    sort($queue, SORT_STRING);
  }

  // If there is a cycle, append remaining tables deterministically.
  if (count($orderedTables) < count($createOpsByTable)) {
    $remaining = array_diff(array_keys($createOpsByTable), $orderedTables);
    sort($remaining, SORT_STRING);
    $orderedTables = array_merge($orderedTables, $remaining);
  }

  $ordered = [];
  foreach ($orderedTables as $table) {
    $ordered[] = $createOpsByTable[$table];
  }
  return $ordered;
}

function cms_schema_sync_plan(PDO $source, PDO $target, array $options = []): array {
  $createOpsByTable = [];
  $addColumnOps = [];
  $addForeignKeyOps = [];
  $prefix = (string) ($options['table_prefix'] ?? '');

  $sourceTables = cms_schema_sync_filter_tables(cms_schema_sync_tables($source), $prefix);
  $targetTables = cms_schema_sync_tables($target);

  $missingTables = 0;
  $missingColumns = 0;

  foreach (array_keys($sourceTables) as $table) {
    if (!isset($targetTables[$table])) {
      $createSql = cms_schema_sync_show_create($source, $table);
      $parts = cms_schema_sync_split_create_table_sql($createSql);
      $createOpsByTable[$table] = [
        'type' => 'create_table',
        'table' => $table,
        'column' => null,
        'sql' => ((string) ($parts['create_sql'] ?? $createSql)) . ';',
      ];
      foreach (($parts['foreign_keys'] ?? []) as $fkDef) {
        $fkSql = trim((string) $fkDef);
        if ($fkSql === '') {
          continue;
        }
        $addForeignKeyOps[] = [
          'type' => 'add_foreign_key',
          'table' => $table,
          'column' => null,
          'sql' => 'ALTER TABLE `' . str_replace('`', '``', $table) . '` ADD ' . $fkSql . ';',
        ];
      }
      $missingTables++;
      continue;
    }

    $sourceCreate = cms_schema_sync_show_create($source, $table);
    $targetCreate = cms_schema_sync_show_create($target, $table);
    $sourceCols = cms_schema_sync_parse_columns($sourceCreate);
    $targetCols = cms_schema_sync_parse_columns($targetCreate);

    foreach ($sourceCols as $column => $definition) {
      if (isset($targetCols[$column])) {
        continue;
      }
      $addColumnOps[] = [
        'type' => 'add_column',
        'table' => $table,
        'column' => $column,
        'sql' => 'ALTER TABLE `' . str_replace('`', '``', $table) . '` ADD COLUMN ' . $definition . ';',
      ];
      $missingColumns++;
    }
  }

  $operations = array_merge(
    cms_schema_sync_order_create_operations($createOpsByTable),
    $addColumnOps,
    $addForeignKeyOps
  );

  return [
    'summary' => [
      'table_prefix' => $prefix,
      'source_tables_in_scope' => count($sourceTables),
      'missing_tables' => $missingTables,
      'missing_columns' => $missingColumns,
      'operations' => count($operations),
    ],
    'operations' => $operations,
  ];
}

function cms_schema_sync_table_coverage(PDO $source, PDO $target, string $prefix = 'cms_'): array {
  $sourceTables = cms_schema_sync_filter_tables(cms_schema_sync_tables($source), $prefix);
  $targetTables = cms_schema_sync_tables($target);

  $rows = [];
  foreach (array_keys($sourceTables) as $table) {
    $rows[] = [
      'table' => $table,
      'in_target' => isset($targetTables[$table]),
    ];
  }

  usort($rows, static function (array $a, array $b): int {
    return strcmp((string) $a['table'], (string) $b['table']);
  });

  $missing = 0;
  foreach ($rows as $row) {
    if (empty($row['in_target'])) {
      $missing++;
    }
  }

  return [
    'summary' => [
      'table_prefix' => $prefix,
      'source_tables_in_scope' => count($rows),
      'missing_tables' => $missing,
    ],
    'rows' => $rows,
  ];
}

function cms_schema_sync_apply(PDO $target, array $operations): array {
  $applied = [];
  $fkWarnings = [];
  $target->exec('SET FOREIGN_KEY_CHECKS=0');
  try {
    foreach ($operations as $index => $operation) {
      $sql = (string) ($operation['sql'] ?? '');
      if ($sql === '') {
        continue;
      }

      try {
        $target->exec($sql);
      } catch (Throwable $e) {
        if (($operation['type'] ?? '') === 'add_foreign_key') {
          $fkWarnings[] = [
            'index' => $index + 1,
            'type' => 'add_foreign_key_skipped',
            'table' => $operation['table'] ?? '',
            'column' => null,
            'sql' => $sql,
            'error' => $e->getMessage(),
          ];
          continue;
        }
        throw $e;
      }

      $applied[] = [
        'index' => $index + 1,
        'type' => $operation['type'] ?? 'unknown',
        'table' => $operation['table'] ?? '',
        'column' => $operation['column'] ?? null,
        'sql' => $sql,
      ];
    }
  } finally {
    $target->exec('SET FOREIGN_KEY_CHECKS=1');
  }

  return array_merge($applied, $fkWarnings);
}
