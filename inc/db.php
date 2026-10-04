<?php
$cfg = require ROOT . '/config.local.php';
try {
    $pdo = new PDO(
        "mysql:host={$cfg['host']};dbname={$cfg['name']};charset=utf8mb4",
        $cfg['user'], $cfg['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('Datenbankverbindung fehlgeschlagen.');
}
$pdo->exec("SET time_zone = '" . date('P') . "'");

function q(string $sql, array $params = []): PDOStatement {
    global $pdo;
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st;
}
function row(string $sql, array $params = []): ?array { return q($sql, $params)->fetch() ?: null; }
function rows(string $sql, array $params = []): array { return q($sql, $params)->fetchAll(); }
function val(string $sql, array $params = []) { $r = q($sql, $params)->fetchColumn(); return $r === false ? null : $r; }
function insert_id(): int { global $pdo; return (int)$pdo->lastInsertId(); }

function setting(string $key, string $default = ''): string {
    static $cache = null;
    if ($cache === null) {
        $cache = array_column(rows('SELECT k, v FROM settings'), 'v', 'k');
    }
    return $cache[$key] ?? $default;
}
