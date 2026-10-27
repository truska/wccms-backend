<?php
// CLI integration check: temporary tables only, no application records changed.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__, 3) . '/private/dbcon.php';
require dirname(__DIR__) . '/includes/lib/oli_utc_migration.php';
if (!$pdo instanceof PDO) {
    throw new RuntimeException('Database unavailable.');
}
$pdo->exec("SET SESSION time_zone = '+00:00'");
$pdo->exec('SET SESSION explicit_defaults_for_timestamp = 1');
$pdo->exec('CREATE TEMPORARY TABLE oli_timezone_test (id INT PRIMARY KEY, timefrom TIMESTAMP NOT NULL, timeto TIMESTAMP NOT NULL)');
$insert = $pdo->prepare('INSERT INTO oli_timezone_test VALUES (?, ?, ?)');
$cases = [
    [1, '2026-07-01 08:00:00', '2026-07-01 16:00:00', 28800],
    [2, '2026-10-25 00:30:00', '2026-10-25 02:30:00', 10800],
    [3, '2026-03-29 00:30:00', '2026-03-29 02:30:00', 3600],
];
foreach ($cases as [$id, $from, $to, $seconds]) {
    $fromUtc = oli_utc_candidates($from)[0];
    $toUtc = oli_utc_candidates($to)[0];
    $insert->execute([$id, $fromUtc, $toUtc]);
    $row = $pdo->query("SELECT timefrom, timeto, UNIX_TIMESTAMP(timeto) - UNIX_TIMESTAMP(timefrom) AS elapsed FROM oli_timezone_test WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
    if ($row['timefrom'] !== $fromUtc || $row['timeto'] !== $toUtc || (int) $row['elapsed'] !== $seconds) {
        throw new RuntimeException("TIMESTAMP storage/duration failed for case $id");
    }
}
$pdo->exec("SET SESSION time_zone = '+01:00'");
if ($pdo->query('SELECT timefrom FROM oli_timezone_test WHERE id = 1')->fetchColumn() !== '2026-07-01 08:00:00') {
    throw new RuntimeException('Summer local display failed.');
}
$pdo->exec("SET SESSION time_zone = '+00:00'");
echo "Database UTC storage, local display and DST elapsed duration checks passed (temporary table only).\n";
$hasLondon = $pdo->query("SELECT CONVERT_TZ('2026-07-01 12:00:00','Europe/London','+00:00')")->fetchColumn();
if ($hasLondon === '2026-07-01 11:00:00') {
    $pdo->exec("SET SESSION time_zone = 'Europe/London'");
    foreach ($cases as [$id, $from, $to, $seconds]) {
        $row = $pdo->query("SELECT timefrom, timeto, UNIX_TIMESTAMP(timeto) - UNIX_TIMESTAMP(timefrom) AS elapsed FROM oli_timezone_test WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
        if ($row['timefrom'] !== $from || $row['timeto'] !== $to || (int) $row['elapsed'] !== $seconds) {
            throw new RuntimeException("Named-zone round trip failed for case $id");
        }
    }
    echo "Named Europe/London historical display checks passed.\n";
} else {
    echo "Named-zone checks skipped: install database timezone data before migration.\n";
}
