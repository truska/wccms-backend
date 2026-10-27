<?php
require dirname(__DIR__) . '/includes/timezone.php';
require dirname(__DIR__) . '/includes/lib/oli_utc_migration.php';
$cases = [
    '2026-07-01 12:00:00' => ['2026-07-01 11:00:00'],
    '2026-12-01 12:00:00' => ['2026-12-01 12:00:00'],
    '2026-10-25 00:59:59' => ['2026-10-24 23:59:59'],
    '2026-10-25 01:30:00' => ['2026-10-25 00:30:00', '2026-10-25 01:30:00'],
    '2026-10-25 02:00:00' => ['2026-10-25 02:00:00'],
    '2026-03-29 01:30:00' => [],
    '2026-02-30 12:00:00' => [],
    '0000-00-00 00:00:00' => [],
];
foreach ($cases as $local => $expected) {
    if (oli_utc_candidates($local) !== $expected) {
        throw new RuntimeException('Failed UTC conversion: ' . $local);
    }
    foreach ($expected as $utc) {
        $display = (new DateTimeImmutable($utc, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('Europe/London'))->format('Y-m-d H:i:s');
        if ($display !== $local) {
            throw new RuntimeException('Failed local display round trip: ' . $local);
        }
    }
}
$from = new DateTimeImmutable('2026-10-25 00:30:00');
$to = new DateTimeImmutable('2026-10-25 02:30:00');
if ($to->getTimestamp() - $from->getTimestamp() !== 10800) {
    throw new RuntimeException('Autumn overnight shift must include the repeated hour.');
}
$from = new DateTimeImmutable('2026-03-29 00:30:00');
$to = new DateTimeImmutable('2026-03-29 02:30:00');
if ($to->getTimestamp() - $from->getTimestamp() !== 3600) {
    throw new RuntimeException('Spring overnight shift must exclude the skipped hour.');
}
echo "Timezone conversion, seasonal boundaries, ambiguous/nonexistent times and shift duration checks passed.\n";
