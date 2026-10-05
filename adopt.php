<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_login();
$uid = (int)$u['id'];
$max = max_pets_for($u);
$count = (int)val('SELECT COUNT(*) FROM pets WHERE user_id = ?', [$uid]);
$first = $count === 0 && stat_get($uid, 'adopt') === 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $name = trim($_POST['name'] ?? '');
    $s = row("SELECT * FROM species WHERE id = ? AND active = 1 AND rarity = 'common'", [(int)($_POST['species'] ?? 0)]);
    $price = $first ? 0 : (int)($s['price'] ?? 0);
    if ($count >= $max) flash("Du kannst höchstens $max Tiere haben.", 'err');
    elseif (!$s) flash('Bitte wähle ein adoptierbares Tier aus. Seltene Tiere schlüpfen aus Eiern.', 'err');
    elseif (mb_strlen($name) < 2 || mb_strlen($name) > 30) flash('Der Name muss 2-30 Zeichen lang sein.', 'err');
    elseif ($price > 0 && !spend($uid, $price)) flash('Nicht genug Münzen.', 'err');
    else {
        $id = create_pet($uid, $s, $name);
        log_activity($uid, $id, "$name ({$s['name']}) ist bei dir eingezogen.");
        flash("$name ist bei dir eingezogen!");
        redirect('pet.php?id=' . $id);
    }
}
$species = rows('SELECT * FROM species WHERE active = 1 ORDER BY FIELD(rarity,\'common\',\'rare\',\'epic\',\'legendary\'), id');
page_header('Tier adoptieren', 'adopt');
?>
<h1>Tier adoptieren</h1>
<p class="muted"><?= $first ? 'Dein erstes Tier ist kostenlos!' : 'Gewöhnliche Tiere kosten Münzen. Seltene, epische und legendäre Tiere schlüpfen aus Eiern (<a href="' . e(url('shop.php?tab=egg')) . '">Eier-Shop</a>).' ?></p>
<?php if ($count >= $max): ?><div class="flash err">Du hast bereits die maximale Anzahl von <?= $max ?> Tieren. Eine <a href="<?= e(url('shop.php?tab=boost')) ?>">Stall-Erweiterung</a> schafft Platz.</div><?php else: ?>
<form method="post" class="form-wide"><?= csrf_field() ?>
  <div class="grid pick">
  <?php $sel = false; foreach ($species as $s): $ok = $s['rarity'] === 'common'; ?>
    <label class="pet-card pickable" style="--c:<?= e($s['color']) ?>;<?= $ok ? '' : 'opacity:.55;cursor:not-allowed' ?>">
      <input type="radio" name="species" value="<?= $s['id'] ?>" <?= $ok ? 'required' : 'disabled' ?> <?= $ok && !$sel ? 'checked' : '' ?>><?php if ($ok) $sel = true; ?>
      <div class="pet-emoji"><?= e($s['emoji']) ?></div>
      <h3><?= e($s['name']) ?></h3>
      <p class="muted"><?= e($s['description']) ?></p>
      <span class="chip"><?= e($s['temperament']) ?></span><?= rarity_chip($s['rarity']) ?>
      <span class="chip"><?= $ok ? ($first ? 'Gratis' : '🪙 ' . (int)$s['price']) : 'Nur aus Eiern' ?></span>
    </label>
  <?php endforeach; ?>
  </div>
  <div class="card row"><label class="grow">Name deines Tieres<input name="name" maxlength="30" required placeholder="z. B. Luna"></label><button class="btn">Adoptieren</button></div>
</form>
<?php endif;
page_footer();
