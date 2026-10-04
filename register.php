<?php
require __DIR__ . '/inc/bootstrap.php';
if (current_user()) redirect('dashboard.php');
$err = ''; $v = ['username' => '', 'email' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $v['username'] = trim($_POST['username'] ?? '');
    $v['email'] = trim($_POST['email'] ?? '');
    $pw = $_POST['password'] ?? '';
    if (throttle_count('register', client_ip(), 60) >= 5) $err = 'Zu viele Registrierungen von dieser Adresse. Bitte versuche es später erneut.';
    elseif (setting('registration_open', '1') !== '1') $err = 'Die Registrierung ist derzeit geschlossen.';
    elseif (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $v['username'])) $err = 'Benutzername: 3-30 Zeichen (Buchstaben, Zahlen, _).';
    elseif (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $err = 'Ungültige E-Mail-Adresse.';
    elseif (strlen($pw) < 8) $err = 'Passwort: mindestens 8 Zeichen.';
    elseif ($pw !== ($_POST['password2'] ?? '')) $err = 'Die Passwörter stimmen nicht überein.';
    elseif (val('SELECT 1 FROM users WHERE username = ? OR email = ?', [$v['username'], $v['email']])) $err = 'Benutzername oder E-Mail ist bereits vergeben.';
    else {
        throttle_hit('register', client_ip());
        q('INSERT INTO users (username, email, password_hash, display_name, created_at, last_login) VALUES (?,?,?,?,NOW(),NOW())',
          [$v['username'], $v['email'], password_hash($pw, PASSWORD_DEFAULT), $v['username']]);
        session_regenerate_id(true);
        $_SESSION['uid'] = insert_id();
        flash('Willkommen! Adoptiere jetzt dein erstes Tier.');
        redirect('adopt.php');
    }
}
page_header('Registrieren');
?>
<div class="card narrow"><h1>Registrieren</h1>
<?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
<form method="post" class="form"><?= csrf_field() ?>
  <label>Benutzername<input name="username" value="<?= e($v['username']) ?>" required maxlength="30"></label>
  <label>E-Mail<input type="email" name="email" value="<?= e($v['email']) ?>" required></label>
  <label>Passwort (min. 8 Zeichen)<input type="password" name="password" required></label>
  <label>Passwort wiederholen<input type="password" name="password2" required></label>
  <button class="btn">Konto erstellen</button>
</form></div>
<?php page_footer();
