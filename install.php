<?php
require __DIR__ . '/inc/bootstrap.php';
$done = is_file(ROOT . '/config.local.php');
$err = '';
$v = ['host' => 'localhost', 'name' => 'haustier', 'user' => 'root', 'admin' => 'admin', 'email' => ''];

if (!$done && $_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($v as $k => $_) $v[$k] = trim($_POST[$k] ?? '');
    $dbpass = $_POST['pass'] ?? '';
    $apass = $_POST['admin_pass'] ?? '';
    if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $v['admin'])) $err = 'Admin-Benutzername: 3-30 Zeichen (Buchstaben, Zahlen, _).';
    elseif (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $err = 'Ungültige E-Mail.';
    elseif (strlen($apass) < 8) $err = 'Admin-Passwort: mindestens 8 Zeichen.';
    elseif (!preg_match('/^[A-Za-z0-9_]+$/', $v['name'])) $err = 'Ungültiger Datenbankname.';
    else {
        try {
            $pdo = new PDO("mysql:host={$v['host']};charset=utf8mb4", $v['user'], $dbpass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$v['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$v['name']}`");
            foreach (preg_split('/;\s*\n/', file_get_contents(ROOT . '/schema.sql')) as $stmt) {
                if (trim($stmt) !== '') $pdo->exec($stmt);
            }
            $st = $pdo->prepare('INSERT INTO users (username, email, password_hash, display_name, is_admin, created_at) VALUES (?,?,?,?,1,NOW())');
            $st->execute([$v['admin'], $v['email'], password_hash($apass, PASSWORD_DEFAULT), $v['admin']]);
            $cfg = "<?php\nreturn " . var_export(['host' => $v['host'], 'name' => $v['name'], 'user' => $v['user'], 'pass' => $dbpass], true) . ";\n";
            if (file_put_contents(ROOT . '/config.local.php', $cfg) === false) throw new RuntimeException('config.local.php konnte nicht geschrieben werden.');
            $done = true;
        } catch (Throwable $ex) {
            $err = 'Fehler: ' . $ex->getMessage();
        }
    }
}
?><!doctype html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installation – Digitales Haustier</title>
<link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>"></head>
<body><main class="wrap narrow">
<div class="card"><h1>Installation</h1>
<?php if ($done): ?>
  <div class="flash ok">Installation abgeschlossen.</div>
  <p>Lösche aus Sicherheitsgründen die Datei <code>install.php</code> vom Server.</p>
  <a class="btn" href="<?= e(url('login.php')) ?>">Zur Anmeldung</a>
<?php else: ?>
  <?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
  <form method="post" class="form">
    <h3>MySQL</h3>
    <label>Host<input name="host" value="<?= e($v['host']) ?>" required></label>
    <label>Datenbankname (wird bei Bedarf angelegt)<input name="name" value="<?= e($v['name']) ?>" required></label>
    <label>Benutzer<input name="user" value="<?= e($v['user']) ?>" required></label>
    <label>Passwort<input type="password" name="pass"></label>
    <h3>Admin-Konto</h3>
    <label>Benutzername<input name="admin" value="<?= e($v['admin']) ?>" required></label>
    <label>E-Mail<input type="email" name="email" value="<?= e($v['email']) ?>" required></label>
    <label>Passwort (min. 8 Zeichen)<input type="password" name="admin_pass" required></label>
    <button class="btn">Installieren</button>
  </form>
<?php endif; ?>
</div></main>
<footer class="foot"><div class="wrap">2026 by. <a href="https://Frank-Panzer.de" target="_blank" rel="noopener">Frank Panzer</a> - Entwickelt von <a href="https://panzerit.de" target="_blank" rel="noopener">Panzer IT</a></div></footer>
</body></html>
