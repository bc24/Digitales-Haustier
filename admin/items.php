<?php
require __DIR__ . '/_inc.php';
const KINDS = ['use' => 'Verbrauchsgegenstand', 'hat' => 'Kopfschmuck', 'room' => 'Zimmer', 'egg' => 'Ei', 'boost' => 'Spezial'];
const FX = ['food_val' => 'Sättigung', 'fun_val' => 'Spaß', 'clean_val' => 'Sauberkeit', 'energy_val' => 'Energie', 'health_val' => 'Gesundheit', 'aff_val' => 'Zuneigung'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['do'] ?? '') === 'delete') { q('DELETE FROM items WHERE id=?', [$id]); flash('Item gelöscht.'); }
    else {
        $kind = array_key_exists($_POST['kind'] ?? '', KINDS) ? $_POST['kind'] : 'use';
        $f = [$kind, mb_substr(trim($_POST['name'] ?? ''), 0, 40), mb_substr(trim($_POST['emoji'] ?? ''), 0, 16), mb_substr(trim($_POST['description'] ?? ''), 0, 160),
              max(0, min(1000000, (int)($_POST['price'] ?? 0))), array_key_exists($_POST['rarity'] ?? '', RARITY) ? $_POST['rarity'] : 'common',
              preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#ffffff'];
        foreach (array_keys(FX) as $c) $f[] = max(-100, min(100, (int)($_POST[$c] ?? 0)));
        $f[] = max(0, min(100000, (int)($_POST['hatch_minutes'] ?? 0)));
        $f[] = preg_match('/^\d+(,\d+){3}$/', $_POST['weights'] ?? '') ? $_POST['weights'] : '';
        $f[] = isset($_POST['active']) ? 1 : 0;
        if ($f[1] === '') flash('Name ist Pflicht.', 'err');
        elseif ($id) { q('UPDATE items SET kind=?, name=?, emoji=?, description=?, price=?, rarity=?, color=?, food_val=?, fun_val=?, clean_val=?, energy_val=?, health_val=?, aff_val=?, hatch_minutes=?, weights=?, active=? WHERE id=?', [...$f, $id]); flash('Gespeichert.'); }
        else { q('INSERT INTO items (kind, name, emoji, description, price, rarity, color, food_val, fun_val, clean_val, energy_val, health_val, aff_val, hatch_minutes, weights, active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', $f); flash('Item angelegt.'); }
    }
    redirect('admin/items.php');
}
$list = rows('SELECT * FROM items ORDER BY kind, sort, id');
$blank = ['id' => 0, 'kind' => 'use', 'name' => '', 'emoji' => '', 'description' => '', 'price' => 50, 'rarity' => 'common', 'color' => '#ffffff', 'food_val' => 0, 'fun_val' => 0, 'clean_val' => 0, 'energy_val' => 0, 'health_val' => 0, 'aff_val' => 0, 'hatch_minutes' => 0, 'weights' => '', 'active' => 1];
admin_header('Shop-Items', 'items');
echo '<p class="muted">Eier: Brutzeit in Minuten und Gewichte „gewöhnlich,selten,episch,legendär“ (z. B. 80,18,2,0). Zimmer: Farbe. Verbrauchsgegenstände: Effekte in Punkten. Die Namen „Streak-Schutz“, „Wärmelampe“, „Stall-Erweiterung“, „Apfel“, „Torte“, „Zauberkeks“, „Gourmet-Menü“ und „Standard-Ei“ werden vom Spiel referenziert, bitte nicht umbenennen.</p>';
foreach (array_merge([$blank], $list) as $it): ?>
<details class="card" <?= $it['id'] ? '' : 'open' ?>><summary><?= $it['id'] ? e($it['emoji'] . ' ' . $it['name']) . ' <span class="muted">(' . e(KINDS[$it['kind']]) . ', ' . (int)$it['price'] . ' Münzen)' . ($it['active'] ? '' : ' - inaktiv') . '</span>' : '<b>Neues Item</b>' ?></summary>
<form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $it['id'] ?>">
  <div class="two"><label>Name<input name="name" value="<?= e($it['name']) ?>" required maxlength="40"></label><label>Emoji<input name="emoji" value="<?= e($it['emoji']) ?>" maxlength="16"></label></div>
  <label>Beschreibung<input name="description" value="<?= e($it['description']) ?>" maxlength="160"></label>
  <div class="four">
    <label>Art<select name="kind"><?php foreach (KINDS as $k => $l): ?><option value="<?= $k ?>" <?= $it['kind'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    <label>Preis<input type="number" name="price" min="0" value="<?= (int)$it['price'] ?>"></label>
    <label>Seltenheit<select name="rarity"><?php foreach (RARITY as $k => $l): ?><option value="<?= $k ?>" <?= $it['rarity'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    <label>Farbe (Zimmer)<input type="color" name="color" value="<?= e($it['color']) ?>"></label>
  </div>
  <div class="four"><?php foreach (FX as $c => $l): ?><label><?= e($l) ?><input type="number" name="<?= $c ?>" value="<?= (int)$it[$c] ?>"></label><?php endforeach; ?>
    <label>Brutzeit (Min.)<input type="number" name="hatch_minutes" min="0" value="<?= (int)$it['hatch_minutes'] ?>"></label>
    <label>Gewichte<input name="weights" value="<?= e($it['weights']) ?>" placeholder="80,18,2,0"></label></div>
  <label class="check"><input type="checkbox" name="active" <?= $it['active'] ? 'checked' : '' ?>> Aktiv (im Shop sichtbar)</label>
  <div class="row"><button class="btn small">Speichern</button><?php if ($it['id']): ?><button class="btn small danger" name="do" value="delete" onclick="return confirm('Item löschen?')">Löschen</button><?php endif; ?></div>
</form></details>
<?php endforeach;
page_footer();
