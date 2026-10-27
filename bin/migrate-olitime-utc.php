<?php
/** Run from CLI in maintenance mode. Default is a read-only audit. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/includes/lib/oli_utc_migration.php';
$options = getopt('', ['apply', 'maintenance-confirmed', 'preserve-zero-dates', 'overrides:']);
$apply = isset($options['apply']);
try {
    require dirname(__DIR__, 3) . '/private/dbcon.php';
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new RuntimeException('Database connection unavailable.');
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET SESSION time_zone = '+00:00'");
    $overrides = isset($options['overrides'])
        ? json_decode(file_get_contents($options['overrides']), true, 512, JSON_THROW_ON_ERROR) : [];
    if (!is_array($overrides)) {
        throw new RuntimeException('Overrides must be a JSON object keyed by table.id.column.');
    }
    $tables = ['oli_medicine' => ['timegiven'], 'oli_times' => ['timefrom', 'timeto']];
    $rows = [];
    $originalRows = [];
    $issues = [];
    $usedOverrides = [];
    $already = 0;
    foreach ($tables as $table => $fields) {
        $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        $types = array_column($columns, 'Type', 'Field');
        foreach ($fields as $field) {
            if (($types[$field] ?? '') === 'timestamp') {
                ++$already;
            } elseif (($types[$field] ?? '') !== 'datetime') {
                throw new RuntimeException("Unexpected type for $table.$field; no changes made.");
            }
        }
        $rows[$table] = $pdo->query("SELECT * FROM `$table` ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        $originalRows[$table] = $rows[$table];
        foreach ($rows[$table] as &$row) {
            foreach ($fields as $field) {
                $key = "$table.{$row['id']}.$field";
                if ($row[$field] === '0000-00-00 00:00:00' && isset($options['preserve-zero-dates'])) {
                    echo "WARNING: preserving existing invalid zero date at $key\n";
                    continue;
                }
                $candidates = oli_utc_candidates((string) $row[$field]);
                if (isset($overrides[$key])) {
                    $chosen = (string) $overrides[$key];
                    if (!in_array($chosen, $candidates, true)) {
                        $issues[] = "$key: override is not a valid UTC candidate";
                        continue;
                    }
                    $usedOverrides[$key] = true;
                } elseif (count($candidates) !== 1) {
                    $issues[] = "$key: {$row[$field]} has " . count($candidates)
                        . ' UTC candidates: ' . implode(', ', $candidates);
                    continue;
                } else {
                    $chosen = $candidates[0];
                }
                // Portable TIMESTAMP range shared by supported MySQL versions.
                if ($chosen < '1970-01-01 00:00:01' || $chosen > '2038-01-19 03:14:07') {
                    $issues[] = "$key: outside the portable TIMESTAMP range";
                }
                $row[$field] = $chosen;
            }
        }
        unset($row);
        echo "$table: " . count($rows[$table]) . " records audited\n";
    }
    if ($already === 3) {
        echo "Already migrated; no changes made.\n";
        exit(0);
    }
    if ($already !== 0) {
        throw new RuntimeException('Mixed column types; manual review required.');
    }
    foreach (array_diff_key($overrides, $usedOverrides) as $key => $_) {
        $issues[] = "Unused override: $key";
    }
    // Verify both seasons; a fixed offset is insufficient for historical data.
    $zones = $pdo->query("SELECT
        CONVERT_TZ('2026-07-01 12:00:00','Europe/London','+00:00') AS summer,
        CONVERT_TZ('2026-12-01 12:00:00','Europe/London','+00:00') AS winter")->fetch(PDO::FETCH_ASSOC);
    $zoneReady = $zones['summer'] === '2026-07-01 11:00:00' && $zones['winter'] === '2026-12-01 12:00:00';
    echo $zoneReady ? "Named timezone data: ready\n" : "Named timezone data: MISSING (DBA must install before applying)\n";
    if ($issues) {
        throw new RuntimeException(implode("\n", $issues)
            . "\nResolve these before applying. Existing zero dates may be retained with --preserve-zero-dates. No changes made.");
    }
    if (!$apply) {
        echo "Audit only; no changes made. Use --apply --maintenance-confirmed during maintenance.\n";
        exit(0);
    }
    if (!isset($options['maintenance-confirmed']) || !$zoneReady) {
        throw new RuntimeException('Apply requires maintenance mode and working named timezone data.');
    }
    if (isset($options['preserve-zero-dates'])) {
        $modes = explode(',', (string) $pdo->query('SELECT @@session.sql_mode')->fetchColumn());
        $modes = array_diff($modes, ['NO_ZERO_DATE', 'NO_ZERO_IN_DATE']);
        $pdo->exec('SET SESSION sql_mode = ' . $pdo->quote(implode(',', $modes)));
    }
    // Prevent older server settings from implicitly adding ON UPDATE to the
    // first TIMESTAMP column (a drug's administration time must never move).
    $pdo->exec('SET SESSION explicit_defaults_for_timestamp = 1');
    // Triggers and foreign keys need a bespoke migration; never silently lose them.
    foreach (array_keys($tables) as $table) {
        $check = $pdo->prepare('SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
            WHERE (TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :source AND REFERENCED_TABLE_NAME IS NOT NULL)
               OR (REFERENCED_TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = :target)');
        $check->execute([':source' => $table, ':target' => $table]);
        $triggers = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE = ?');
        $triggers->execute([$table]);
        if ($check->fetchColumn() || $triggers->fetchColumn()) {
            throw new RuntimeException("$table has foreign keys or triggers; manual migration required.");
        }
    }
    $suffix = gmdate('Ymd_His') . '_' . bin2hex(random_bytes(3));
    $renames = [];
    foreach ($tables as $table => $fields) {
        $shadow = $table . '_utc_new_' . $suffix;
        $backup = $table . '_local_backup_' . $suffix;
        $pdo->exec("CREATE TABLE `$shadow` LIKE `$table`");
        $alter = [];
        foreach ($fields as $field) {
            $default = $field === 'timegiven' ? ' DEFAULT CURRENT_TIMESTAMP' : '';
            $alter[] = "MODIFY `$field` TIMESTAMP NOT NULL$default";
        }
        $pdo->exec("ALTER TABLE `$shadow` " . implode(', ', $alter));
        if ($rows[$table]) {
            $columns = array_keys($rows[$table][0]);
            $insert = $pdo->prepare("INSERT INTO `$shadow` (`" . implode('`,`', $columns)
                . '`) VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')');
            foreach ($rows[$table] as $row) {
                $insert->execute(array_values($row));
            }
        }
        // Verify every column, including UTC audit timestamps and record IDs.
        $copied = $pdo->query("SELECT * FROM `$shadow` ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        if ($copied != $rows[$table]) {
            throw new RuntimeException("Verification failed for $shadow. Original tables unchanged.");
        }
        $status = $pdo->query("SHOW TABLE STATUS WHERE Name = '$table'")->fetch(PDO::FETCH_ASSOC);
        $nextId = max(1, (int) $status['Auto_increment']);
        $pdo->exec("ALTER TABLE `$shadow` AUTO_INCREMENT = $nextId");
        $renames[] = "`$table` TO `$backup`, `$shadow` TO `$table`";
        echo "Prepared $shadow; original will be retained as $backup\n";
    }
    // One atomic swap: either both original tables remain, or both UTC copies go live.
    foreach (array_keys($tables) as $table) {
        if ($pdo->query("SELECT * FROM `$table` ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) != $originalRows[$table]) {
            throw new RuntimeException("$table changed during migration. Stop all writers and retry. Original tables unchanged.");
        }
    }
    $pdo->exec('RENAME TABLE ' . implode(', ', $renames));
    echo "Migration complete. Keep original backup tables until live checks pass.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
