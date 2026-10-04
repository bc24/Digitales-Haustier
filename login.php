<?php
require __DIR__ . '/inc/bootstrap.php';
if (current_user()) redirect('dashboard.php');
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $login = trim($_POST['login'] ?? '');
    $ip = client_ip();
    if (throttle_count('login_ip', $ip, 15) >= 20 || throttle_count('login_user', $login, 15) >= 5) {
        $err = 'Zu viele fehlgeschlagene Versuche. Bitte warte 15 Minuten.';
    } elseif (!($user = row('SELECT * FROM users WHERE username = ? OR email = ?', [$login, $login])) || !password_verify($_POST['password'] ?? '', $user['password_hash'])) {
        throttle_hit('login_ip', $ip); throttle_hit('login_user', $login);
        $err = 'Anmeldedaten falsch.';
    }
    elseif ($user['is_banned']) $err = 'Dieses Konto wurde gesperrt.';
    else {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$user['id'];
        q('UPDATE users SET last_login = NOW() WHERE id = ?', [$user['id']]);
        redirect('dashboard.php');
    }
}
page_header('Anmelden');
?>
<div class="card narrow"><h1>Anmelden</h1>
<?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
<form method="post" class="form"><?= csrf_field() ?>
  <label>Benutzername oder E-Mail<input name="login" required autofocus></label>
  <label>Passwort<input type="password" name="password" required></label>
  <button class="btn">Anmelden</button>
</form>
<p class="muted"><a href="<?= e(url('forgot.php')) ?>">Passwort vergessen?</a></p>
<p class="muted">Noch kein Konto? <a href="<?= e(url('register.php')) ?>">Registrieren</a></p></div>
<?php page_footer();
