<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_login();
$uid = (int)$u['id'];
$items = rows('SELECT i.*, inv.qty FROM inventory inv JOIN items i ON i.id=inv.item_id WHERE inv.user_id=? AND inv.qty>0 ORDER BY i.kind, i.sort', [$uid]);
$labels = ['use' => 'Verbrauchsgegenstände', 'hat' => 'Kopfschmuck', 'room' => 'Zimmer', 'boost' => 'Spezial'];
page_header('Rucksack', 'inventory');
?>
<h1>Rucksack</h1>
<p class="muted">Verbrauchsgegenstände wendest du direkt auf der Seite deines Tieres an, Kopfschmuck und Zimmer ebenfalls (Bereich „Aussehen“). Streak-Schutz wird automatisch genutzt. Eilvermerke setzt du im <a href="<?= e(url('shelter.php')) ?>">Tierheim</a> ein.</p>
<?php if (!$items): ?><div class="card"><p class="muted">Dein Rucksack ist leer. <a href="<?= e(url('shop.php')) ?>">Zum Shop</a></p></div><?php endif; ?>
<?php foreach ($labels as $kind => $label): $list = array_filter($items, fn($i) => $i['kind'] === $kind); if (!$list) continue; ?>
  <h2><?= e($label) ?></h2>
  <div class="shop-grid"><?php foreach ($list as $it): ?>
    <div class="item"><?php if ($kind === 'room'): ?><div class="swatch" style="background:<?= e($it['color']) ?>"></div><?php endif; ?>
      <div class="ie"><?= e($it['emoji']) ?></div><h4><?= e($it['name']) ?></h4><?= rarity_chip($it['rarity']) ?>
      <p class="muted"><?= e($it['description']) ?></p><b>×<?= (int)$it['qty'] ?></b></div>
  <?php endforeach; ?></div>
<?php endforeach;
page_footer();
