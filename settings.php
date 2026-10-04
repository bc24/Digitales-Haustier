<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_login();
const AVATARS = ['🙂','😎','🤓','🥳','😺','🐶','🦊','🐼','🐸','🦁','🐯','🐵','🦄','🐙','🌸','⭐'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    if (($_POST['do'] ?? '') === 'profile') {
        $dn = trim($_POST['display_name'] ?? '');
        $bio = mb_substr(trim($_POST['bio'] ?? ''), 0, 500);
        $av = in_array($_POST['avatar'] ?? '', AVATARS, true) ? $_POST['avatar'] : $u['avatar'];
        if (mb_strlen($dn) < 2 || mb_strlen($dn) > 60) flash('Anzeigename: 2-60 Zeichen.', 'err');
        else { q('UPDATE users SET display_name=?, bio=?, avatar=? WHERE id=?', [$dn, $bio, $av, $u['id']]); flash('Profil gespeichert.'); }
    } elseif (($_POST['do'] ?? '') === 'password') {
        if (!password_verify($_POST['old'] ?? '', $u['password_hash'])) flash('Aktuelles Passwort falsch.', 'err');
        elseif (strlen($_POST['new'] ?? '') < 8) flash('Neues Passwort: mindestens 8 Zeichen.', 'err');
        else { q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($_POST['new'], PASSWORD_DEFAULT), $u['id']]); flash('Passwort geändert.'); }
    } elseif (($_POST['do'] ?? '') === 'delete') {
        if (!password_verify($_POST['pw'] ?? '', $u['password_hash'])) { flash('Passwort falsch. Konto nicht gelöscht.', 'err'); redirect('settings.php'); }
        if ($u['is_admin'] && (int)val('SELECT COUNT(*) FROM users WHERE is_admin=1') < 2) { flash('Du bist der einzige Admin und kannst dein Konto nicht löschen.', 'err'); redirect('settings.php'); }
        q('DELETE FROM users WHERE id=?', [$u['id']]);
        $_SESSION = []; session_destroy(); session_start();
        flash('Dein Konto und alle zugehörigen Daten wurden gelöscht.');
        redirect('index.php');
    }
    redirect('settings.php');
}
page_header('Einstellungen', 'me');
?>
<h1>Profil bearbeiten</h1>
<div class="card"><form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="do" value="profile">
  <label>Anzeigename<input name="display_name" value="<?= e($u['display_name']) ?>" required maxlength="60"></label>
  <label>Über mich<textarea name="bio" rows="4" maxlength="500"><?= e($u['bio']) ?></textarea></label>
  <div>Avatar<div class="chips"><?php foreach (AVATARS as $a): ?><label class="av"><input type="radio" name="avatar" value="<?= e($a) ?>" <?= $u['avatar'] === $a ? 'checked' : '' ?>><span><?= e($a) ?></span></label><?php endforeach; ?></div></div>
  <button class="btn">Speichern</button></form></div>
<h2>Passwort ändern</h2>
<div class="card"><form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="do" value="password">
  <label>Aktuelles Passwort<input type="password" name="old" required></label>
  <label>Neues Passwort<input type="password" name="new" required></label>
  <button class="btn">Ändern</button></form></div>
<h2>Konto löschen</h2>
<div class="card"><p class="muted">Löscht dein Konto, dein Profil, deine Tiere und deine Freundschaften endgültig.</p>
<form method="post" class="row" onsubmit="return confirm('Konto endgültig löschen?')"><?= csrf_field() ?><input type="hidden" name="do" value="delete">
  <input type="password" name="pw" placeholder="Passwort zur Bestätigung" required class="grow"><button class="btn danger">Konto löschen</button></form></div>
<?php page_footer();
