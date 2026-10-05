<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_login();
$uid = (int)$u['id'];
$tabs = ['use' => 'Futter & Pflege', 'hat' => 'Kopfschmuck', 'room' => 'Zimmer', 'egg' => 'Tierheim', 'boost' => 'Spezial'];
$tab = $_GET['tab'] ?? 'use';
if (!isset($tabs[$tab])) $tab = 'use';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $it = row('SELECT * FROM items WHERE id=? AND active=1', [(int)($_POST['item'] ?? 0)]);
    $qty = max(1, min(20, (int)($_POST['qty'] ?? 1)));
    $back = 'shop.php?tab=' . ($it['kind'] ?? 'use');
    if (!$it) flash('Item nicht gefunden.', 'err');
    elseif (in_array($it['kind'], ['hat', 'room'], true) && item_qty($uid, (int)$it['id']) > 0) flash('Das besitzt du bereits.', 'err');
    elseif ($it['kind'] === 'egg' && (int)val('SELECT COUNT(*) FROM eggs WHERE user_id=?', [$uid]) >= 3) flash('Du hast schon 3 laufende Vermittlungen.', 'err');
    elseif ($it['name'] === 'Stall-Erweiterung' && (int)$u['extra_slots'] >= 5) flash('Maximale Erweiterung erreicht.', 'err');
    else {
        if (in_array($it['kind'], ['hat', 'room', 'egg'], true) || $it['name'] === 'Stall-Erweiterung') $qty = 1;
        $cost = (int)$it['price'] * $qty;
        if (!spend($uid, $cost)) flash('Nicht genug Münzen.', 'err');
        else {
            if ($it['kind'] === 'egg') { give_egg($uid, (int)$it['id']); flash($it['emoji'] . ' ' . $it['name'] . ' wurde eingereicht! Schau im Tierheim vorbei, sobald die Wartezeit um ist.'); }
            elseif ($it['name'] === 'Stall-Erweiterung') { q('UPDATE users SET extra_slots = extra_slots + 1 WHERE id=?', [$uid]); flash('Du hast einen Platz mehr für Tiere!'); }
            else { add_item($uid, (int)$it['id'], $qty); flash($qty . '× ' . $it['emoji'] . ' ' . $it['name'] . ' gekauft.'); }
            award($uid, 0, 2);
        }
    }
    redirect($back);
}
$items = rows('SELECT * FROM items WHERE kind=? AND active=1 ORDER BY price, sort', [$tab]);
$owned = array_column(rows('SELECT item_id, qty FROM inventory WHERE user_id=?', [$uid]), 'qty', 'item_id');
page_header('Shop', 'shop');
?>
<h1>Shop <span class="pill coin" style="font-size:1rem">🪙 <?= number_format((int)$u['coins'], 0, ',', '.') ?></span></h1>
<div class="tabs"><?php foreach ($tabs as $k => $l): ?><a class="<?= $k === $tab ? 'on' : '' ?>" href="?tab=<?= $k ?>"><?= e($l) ?></a><?php endforeach; ?></div>
<div class="shop-grid">
<?php foreach ($items as $it): $has = (int)($owned[$it['id']] ?? 0); $single = in_array($it['kind'], ['hat', 'room'], true); ?>
  <div class="item">
    <?php if ($it['kind'] === 'room'): ?><div class="swatch" style="background:<?= e($it['color']) ?>"></div><?php endif; ?>
    <div class="ie"><?= e($it['emoji']) ?></div>
    <h4><?= e($it['name']) ?></h4><div><?= rarity_chip($it['rarity']) ?></div>
    <p class="muted"><?= e($it['description']) ?></p>
    <?php if ($it['kind'] === 'use'): ?><p class="muted"><?php
      $fx = []; foreach (['food_val' => 'Sättigung', 'fun_val' => 'Spaß', 'clean_val' => 'Sauberkeit', 'energy_val' => 'Energie', 'health_val' => 'Gesundheit', 'aff_val' => 'Zuneigung'] as $c => $l) if ($it[$c]) $fx[] = "+{$it[$c]} $l";
      echo e(implode(', ', $fx)); ?></p><?php endif; ?>
    <?php if ($single && $has): ?><span class="chip">Im Besitz</span>
    <?php else: ?><form method="post"><?= csrf_field() ?><input type="hidden" name="item" value="<?= $it['id'] ?>">
      <?php if ($it['kind'] === 'use' || ($it['kind'] === 'boost' && $it['name'] !== 'Stall-Erweiterung')): ?><input type="number" name="qty" value="1" min="1" max="20" style="width:64px;margin-bottom:6px"><?php endif; ?>
      <button class="btn small" <?= (int)$u['coins'] < (int)$it['price'] ? 'style="opacity:.6"' : '' ?>>🪙 <?= (int)$it['price'] ?> kaufen</button></form><?php endif; ?>
    <?php if (!$single && $has): ?><p class="muted">Im Rucksack: <?= $has ?></p><?php endif; ?>
  </div>
<?php endforeach; ?>
</div>
<?php page_footer();
