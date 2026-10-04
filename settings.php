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
<?php page_footer();
