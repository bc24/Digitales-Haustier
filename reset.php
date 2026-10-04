<?php
require __DIR__ . '/inc/bootstrap.php';
$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$rec = preg_match('/^[a-f0-9]{64}$/', $token)
    ? row('SELECT * FROM password_resets WHERE token_hash = ? AND used = 0 AND expires_at > NOW()', [hash('sha256', $token)]) : null;
$err = '';
if ($rec && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $pw = $_POST['password'] ?? '';
    if (strlen($pw) < 8) $err = 'Passwort: mindestens 8 Zeichen.';
    elseif ($pw !== ($_POST['password2'] ?? '')) $err = 'Die Passwörter stimmen nicht überein.';
    else {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $rec['user_id']]);
        q('UPDATE password_resets SET used = 1 WHERE user_id = ?', [$rec['user_id']]);
        flash('Passwort geändert. Du kannst dich jetzt anmelden.');
        redirect('login.php');
    }
}
page_header('Neues Passwort');
?>
<div class="card narrow"><h1>Neues Passwort</h1>
<?php if (!$rec): ?><div class="flash err">Der Link ist ungültig oder abgelaufen.</div><a href="<?= e(url('forgot.php')) ?>">Neuen Link anfordern</a>
<?php else: ?>
<?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
<form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
  <label>Neues Passwort (min. 8 Zeichen)<input type="password" name="password" required autofocus></label>
  <label>Passwort wiederholen<input type="password" name="password2" required></label>
  <button class="btn">Passwort speichern</button>
</form><?php endif; ?></div>
<?php page_footer();
