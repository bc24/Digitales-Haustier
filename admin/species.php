<?php
require __DIR__ . '/_inc.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $id = (int)($_POST['id'] ?? 0);
    $do = $_POST['do'] ?? 'save';
    if ($do === 'delete') {
        if (val('SELECT COUNT(*) FROM pets WHERE species_id=?', [$id])) flash('Es existieren noch Tiere dieser Art. Deaktiviere die Art stattdessen.', 'err');
        else { q('DELETE FROM species WHERE id=?', [$id]); flash('Art gelöscht.'); }
    } else {
        $f = [
            trim($_POST['name'] ?? ''), trim($_POST['emoji'] ?? ''), mb_substr(trim($_POST['description'] ?? ''), 0, 255),
            mb_substr(trim($_POST['temperament'] ?? ''), 0, 40), preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#ffd6e0',
            mb_substr(trim($_POST['favorite_food'] ?? ''), 0, 40), max(0, min(100, (int)($_POST['base_affection'] ?? 30))),
            max(0, min(20, (float)($_POST['food_decay'] ?? 4))), max(0, min(20, (float)($_POST['fun_decay'] ?? 3))),
            max(0, min(20, (float)($_POST['clean_decay'] ?? 2))), max(0, min(20, (float)($_POST['energy_decay'] ?? 3))), isset($_POST['active']) ? 1 : 0,
        ];
        if ($f[0] === '' || $f[1] === '') flash('Name und Emoji sind Pflicht.', 'err');
        else {
            try {
                if ($id) { q('UPDATE species SET name=?, emoji=?, description=?, temperament=?, color=?, favorite_food=?, base_affection=?, food_decay=?, fun_decay=?, clean_decay=?, energy_decay=?, active=? WHERE id=?', [...$f, $id]); }
                else { q('INSERT INTO species (name, emoji, description, temperament, color, favorite_food, base_affection, food_decay, fun_decay, clean_decay, energy_decay, active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', $f); }
                flash('Gespeichert.');
            } catch (PDOException $ex) { flash('Speichern fehlgeschlagen (Name bereits vergeben?).', 'err'); }
        }
    }
    redirect('admin/species.php');
}
$list = rows('SELECT s.*, (SELECT COUNT(*) FROM pets WHERE species_id=s.id) AS pets FROM species s ORDER BY id');
$blank = ['id' => 0, 'name' => '', 'emoji' => '', 'description' => '', 'temperament' => 'Ausgeglichen', 'color' => '#ffd6e0', 'favorite_food' => 'Leckerli', 'base_affection' => 30, 'food_decay' => 4, 'fun_decay' => 3, 'clean_decay' => 2, 'energy_decay' => 3, 'active' => 1, 'pets' => 0];
admin_header('Arten', 'species');
echo '<p class="muted">Anfangszuneigung: Wie sehr das Tier einen neuen Besitzer mag (niedrig = misstrauisch). Verfall: Punkte pro Stunde.</p>';
foreach (array_merge([$blank], $list) as $s): ?>
<details class="card" <?= $s['id'] ? '' : 'open' ?>><summary><?= $s['id'] ? e($s['emoji'] . ' ' . $s['name']) . ' <span class="muted">(' . (int)$s['pets'] . ' Tiere)' . ($s['active'] ? '' : ' - inaktiv') . '</span>' : '<b>Neue Art anlegen</b>' ?></summary>
<form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $s['id'] ?>">
  <div class="two"><label>Name<input name="name" value="<?= e($s['name']) ?>" required maxlength="40"></label><label>Emoji<input name="emoji" value="<?= e($s['emoji']) ?>" required maxlength="16"></label></div>
  <label>Beschreibung<input name="description" value="<?= e($s['description']) ?>" maxlength="255"></label>
  <div class="two"><label>Temperament<input name="temperament" value="<?= e($s['temperament']) ?>" maxlength="40"></label><label>Lieblingsfutter<input name="favorite_food" value="<?= e($s['favorite_food']) ?>" maxlength="40"></label></div>
  <div class="four">
    <label>Farbe<input type="color" name="color" value="<?= e($s['color']) ?>"></label>
    <label>Anfangszuneigung<input type="number" name="base_affection" min="0" max="100" value="<?= (int)$s['base_affection'] ?>"></label>
    <label>Hunger/Std<input type="number" step="0.1" name="food_decay" value="<?= e($s['food_decay']) ?>"></label>
    <label>Langeweile/Std<input type="number" step="0.1" name="fun_decay" value="<?= e($s['fun_decay']) ?>"></label>
    <label>Schmutz/Std<input type="number" step="0.1" name="clean_decay" value="<?= e($s['clean_decay']) ?>"></label>
    <label>Müdigkeit/Std<input type="number" step="0.1" name="energy_decay" value="<?= e($s['energy_decay']) ?>"></label>
  </div>
  <label class="check"><input type="checkbox" name="active" <?= $s['active'] ? 'checked' : '' ?>> Aktiv (adoptierbar)</label>
  <div class="row"><button class="btn small" name="do" value="save">Speichern</button>
  <?php if ($s['id']): ?><button class="btn small danger" name="do" value="delete" onclick="return confirm('Art löschen?')">Löschen</button><?php endif; ?></div>
</form></details>
<?php endforeach;
page_footer();
