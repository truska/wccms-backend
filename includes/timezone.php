<?php
/**
 * Forms and reports use UK wall-clock times. MySQL TIMESTAMP columns store
 * UTC internally and use the named session zone to convert at the boundary.
 */
date_default_timezone_set('Europe/London');

function cms_configure_database_timezone(PDO $connection): void
{
    try {
        $connection->exec("SET SESSION time_zone = 'Europe/London'");
        return;
    } catch (PDOException $error) {
        if ((int) ($error->errorInfo[1] ?? 0) !== 1298) {
            throw $error;
        }
    }
    // Transitional fallback for an unmigrated installation only. A fixed
    // offset cannot correctly render historical TIMESTAMP values across DST.
    $statement = $connection->query("SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE = 'timestamp'
          AND ((TABLE_NAME = 'oli_medicine' AND COLUMN_NAME = 'timegiven')
            OR (TABLE_NAME = 'oli_times' AND COLUMN_NAME IN ('timefrom', 'timeto')))");
    if ((int) $statement->fetchColumn() > 0) {
        throw new RuntimeException('OLITIME requires MySQL Europe/London timezone data after UTC migration.');
    }
    $offset = (new DateTimeImmutable('now', new DateTimeZone('Europe/London')))->format('P');
    $connection->exec('SET SESSION time_zone = ' . $connection->quote($offset));
}
