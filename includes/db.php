<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../private/dbcon.php';

function cms_db(): PDO
{
    global $pdo, $DB_OK, $DB_ERROR;

    if (!$DB_OK || !($pdo instanceof PDO)) {
        throw new RuntimeException('Database unavailable' . ($DB_ERROR ? ': ' . $DB_ERROR : '.'));
    }

    return $pdo;
}

function cms_db_query(string $sql, array $params = []): PDOStatement
{
    $pdo = cms_db();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function cms_db_fetch_one(string $sql, array $params = []): ?array
{
    $row = cms_db_query($sql, $params)->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function cms_db_fetch_all(string $sql, array $params = []): array
{
    return cms_db_query($sql, $params)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function cms_db_fetch_column(string $sql, array $params = [])
{
    return cms_db_query($sql, $params)->fetchColumn();
}

function cms_db_execute(string $sql, array $params = []): bool
{
    return cms_db_query($sql, $params)->rowCount() >= 0;
}

function cms_db_insert_id(): string
{
    return cms_db()->lastInsertId();
}

function cms_db_identifier(string $identifier): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
        throw new InvalidArgumentException('Unsafe database identifier: ' . $identifier);
    }

    return '`' . $identifier . '`';
}

function cms_db_like(string $value): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
}
