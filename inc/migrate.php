<?php
// Führt ausstehende Datenbank-Migrationen (migrations/NNN_*.sql) automatisch aus.
function run_sql_file(PDO $pdo, string $file): void {
    foreach (preg_split('/;\s*\n/', file_get_contents($file)) as $stmt) {
        if (trim($stmt) !== '') $pdo->exec($stmt);
    }
}
function migrate(PDO $pdo): void {
    $cur = 1;
    try {
        $cur = (int)($pdo->query("SELECT v FROM settings WHERE k='schema_version'")->fetchColumn() ?: 1);
    } catch (PDOException $e) { return; }
    $files = glob(ROOT . '/migrations/*.sql') ?: [];
    sort($files);
    foreach ($files as $f) {
        $ver = (int)basename($f);
        if ($ver <= $cur) continue;
        $lock = $pdo->query("SELECT GET_LOCK('haustier_migrate', 10)")->fetchColumn();
        $cur2 = (int)($pdo->query("SELECT v FROM settings WHERE k='schema_version'")->fetchColumn() ?: 1);
        if ($lock && $ver > $cur2) {
            run_sql_file($pdo, $f);
            $pdo->prepare("INSERT INTO settings (k, v) VALUES ('schema_version', ?) ON DUPLICATE KEY UPDATE v = VALUES(v)")->execute([$ver]);
        }
        $pdo->query("SELECT RELEASE_LOCK('haustier_migrate')");
        $cur = $ver;
    }
}
