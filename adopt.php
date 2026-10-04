<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_login();
$max = (int)setting('max_pets', '5');
$count = (int)val('SELECT COUNT(*) FROM pets WHERE user_id = ?', [$u['id']]);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $name = trim($_POST['name'] ?? '');
    $s = row('SELECT * FROM species WHERE id = ? AND active = 1', [(int)($_POST['species'] ?? 0)]);
    if ($count >= $max) flash("Du kannst höchstens $max Tiere haben.", 'err');
    elseif (!$s) flash('Bitte wähle ein Tier aus.', 'err');
    elseif (mb_strlen($name) < 2 || mb_strlen($name) > 30) flash('Der Name muss 2-30 Zeichen lang sein.', 'err');
    else {
        q('INSERT INTO pets (user_id, species_id, name, affection, last_update, created_at) VALUES (?,?,?,?,NOW(),NOW())', [$u['id'], $s['id'], $name, $s['base_affection']]);
        $id = insert_id();
        log_activity((int)$u['id'], $id, "$name ({$s['name']}) ist bei dir eingezogen.");
        flash("$name ist bei dir eingezogen!");
        redirect('pet.php?id=' . $id);
    }
}
$species = rows('SELECT * FROM species WHERE active = 1 ORDER BY id');
page_header('Tier adoptieren', 'adopt');
?>
<h1>Tier adoptieren</h1>
<?php if ($count >= $max): ?><div class="flash err">Du hast bereits die maximale Anzahl von <?= $max ?> Tieren.</div><?php else: ?>
<form method="post" class="form-wide"><?= csrf_field() ?>
  <div class="grid pick">
  <?php foreach ($species as $i => $s): ?>
    <label class="pet-card pickable" style="--c:<?= e($s['color']) ?>">
      <input type="radio" name="species" value="<?= $s['id'] ?>" required <?= $i === 0 ? 'checked' : '' ?>>
      <div class="pet-emoji"><?= e($s['emoji']) ?></div>
      <h3><?= e($s['name']) ?></h3>
      <p class="muted"><?= e($s['description']) ?></p>
      <span class="chip"><?= e($s['temperament']) ?></span>
      <span class="chip">Lieblingsfutter: <?= e($s['favorite_food']) ?></span>
    </label>
  <?php endforeach; ?>
  </div>
  <div class="card row"><label class="grow">Name deines Tieres<input name="name" maxlength="30" required placeholder="z. B. Luna"></label><button class="btn">Adoptieren</button></div>
</form>
<?php endif;
page_footer();
