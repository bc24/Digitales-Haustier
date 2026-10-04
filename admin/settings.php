<?php
require __DIR__ . '/_inc.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $vals = [
        'site_name' => mb_substr(trim($_POST['site_name'] ?? '') ?: 'Digitales Haustier', 0, 60),
        'max_pets' => (string)max(1, min(50, (int)($_POST['max_pets'] ?? 5))),
        'registration_open' => isset($_POST['registration_open']) ? '1' : '0',
        'playdate_cooldown_min' => (string)max(0, min(1440, (int)($_POST['playdate_cooldown_min'] ?? 30))),
    ];
    foreach ($vals as $k => $v) q('INSERT INTO settings (k, v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)', [$k, $v]);
    flash('Einstellungen gespeichert.');
    redirect('admin/settings.php');
}
admin_header('Einstellungen', 'settings');
?>
<div class="card"><form method="post" class="form"><?= csrf_field() ?>
  <label>Seitenname<input name="site_name" value="<?= e(setting('site_name')) ?>" maxlength="60"></label>
  <label>Max. Tiere pro Benutzer<input type="number" name="max_pets" min="1" max="50" value="<?= e(setting('max_pets', '5')) ?>"></label>
  <label>Pause zwischen Spieltreffen (Min.)<input type="number" name="playdate_cooldown_min" min="0" max="1440" value="<?= e(setting('playdate_cooldown_min', '30')) ?>"></label>
  <label class="check"><input type="checkbox" name="registration_open" <?= setting('registration_open', '1') === '1' ? 'checked' : '' ?>> Registrierung offen</label>
  <button class="btn">Speichern</button></form></div>
<?php page_footer();
