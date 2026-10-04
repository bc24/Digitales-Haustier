<?php
require __DIR__ . '/inc/bootstrap.php';
if (current_user()) redirect('dashboard.php');
$sent = false; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $email = trim($_POST['email'] ?? '');
    if (throttle_count('reset', client_ip(), 60) >= 5) {
        $err = 'Zu viele Anfragen. Bitte versuche es später erneut.';
    } else {
        throttle_hit('reset', client_ip());
        $user = row('SELECT id, username FROM users WHERE email = ? AND is_banned = 0', [$email]);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            q('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?,?, NOW() + INTERVAL 1 HOUR)', [$user['id'], hash('sha256', $token)]);
            send_mail($email, 'Passwort zurücksetzen',
                "Hallo {$user['username']},\n\nüber diesen Link kannst du ein neues Passwort setzen (1 Stunde gültig):\n" . site_base_url() . '/reset.php?token=' . $token . "\n\nWenn du das nicht angefordert hast, ignoriere diese E-Mail.");
        }
        $sent = true; // immer gleiche Antwort, damit keine Konten erraten werden können
    }
}
page_header('Passwort vergessen');
?>
<div class="card narrow"><h1>Passwort vergessen</h1>
<?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
<?php if ($sent): ?><div class="flash ok">Falls ein Konto mit dieser E-Mail existiert, haben wir einen Link zum Zurücksetzen gesendet.</div>
<?php else: ?>
<form method="post" class="form"><?= csrf_field() ?>
  <label>E-Mail-Adresse<input type="email" name="email" required autofocus></label>
  <button class="btn">Link anfordern</button>
</form><?php endif; ?></div>
<?php page_footer();
